<?php

namespace App\Domain\Content;

use App\Domain\Admin\AdminAuditService;
use App\Domain\Admin\AdminOrderingState;
use App\Models\CustomPageSetting;
use App\Models\HomePresentationSetting;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final class HomePresentationEditorialService
{
    public function __construct(private readonly AdminAuditService $audit) {}

    /** @return array<string, mixed> */
    public static function defaults(): array
    {
        return [
            HomeTemplate::Artwork->value => [
                'show_details' => true,
                'show_gallery_link' => false,
                'show_navigation' => false,
                'enable_viewer' => true,
            ],
            HomeTemplate::UnderConstruction->value => [
                'public_site_gate' => false,
                'image_viewer_enabled' => false,
                'components' => [
                    [
                        'type' => 'image',
                        'media_asset_id' => null,
                        'image_decorative' => true,
                    ],
                    [
                        'type' => 'text',
                        'title' => 'Under construction',
                        'body' => 'The website is currently being updated.',
                    ],
                ],
            ],
            HomeTemplate::Custom->value => [
                'image_viewer_enabled' => false,
                'components' => [],
            ],
        ];
    }

    /** @return array<string, mixed> */
    public function configuration(HomePresentationSetting $settings): array
    {
        $defaults = self::defaults();
        $stored = $settings->configuration();

        $artwork = is_array($stored[HomeTemplate::Artwork->value] ?? null)
            ? $stored[HomeTemplate::Artwork->value]
            : [];
        $construction = is_array($stored[HomeTemplate::UnderConstruction->value] ?? null)
            ? $stored[HomeTemplate::UnderConstruction->value]
            : [];
        $custom = is_array($stored[HomeTemplate::Custom->value] ?? null)
            ? $stored[HomeTemplate::Custom->value]
            : [];
        unset($artwork['pool_rule'], $artwork['pool_year'], $artwork['manual_include_ids']);

        return [
            HomeTemplate::Artwork->value => array_replace($defaults[HomeTemplate::Artwork->value], $artwork),
            HomeTemplate::UnderConstruction->value => array_replace($defaults[HomeTemplate::UnderConstruction->value], $construction),
            HomeTemplate::Custom->value => array_replace($defaults[HomeTemplate::Custom->value], $custom),
        ];
    }

    /** @param array<string, mixed> $input */
    public function updateSettings(HomePresentationSetting $settings, HomeTemplate $template, array $input): bool
    {
        return DB::transaction(function () use ($settings, $template, $input): bool {
            $fresh = $this->locked($settings);
            $configuration = $this->configuration($fresh);
            $originalConfiguration = $configuration;
            $originalTemplate = $fresh->template();

            if (in_array($template, [HomeTemplate::UnderConstruction, HomeTemplate::Custom], true)
                && array_key_exists('image_viewer_enabled', $input)) {
                if (! is_bool($input['image_viewer_enabled'])) {
                    throw ValidationException::withMessages(['image_viewer_enabled' => 'Choose whether Home images can be enlarged.']);
                }
                $configuration[$template->value]['image_viewer_enabled'] = $input['image_viewer_enabled'];
            }
            if (array_key_exists('public_site_gate', $input) && $input['public_site_gate'] !== null) {
                $configuration[HomeTemplate::UnderConstruction->value]['public_site_gate'] = (bool) $input['public_site_gate'];
            }

            $this->validateConfiguration($configuration);

            if ($originalTemplate === $template && $originalConfiguration === $configuration) {
                return false;
            }

            $fresh->setAttribute('template', $template->value);
            $fresh->setAttribute('configuration', $configuration);

            return $this->save($fresh);
        });
    }

    /** @param array<string, mixed> $component */
    public function addComponent(HomePresentationSetting $settings, HomeTemplate $mode, array $component): bool
    {
        return $this->mutateComponents($settings, $mode, function (array $components) use ($component): array {
            $components[] = $component;

            return $components;
        });
    }

    /** @param array<string, mixed> $component */
    public function updateComponent(
        HomePresentationSetting $settings,
        HomeTemplate $mode,
        int $index,
        string $expectedType,
        array $component,
        ?array $expectedComponent = null,
    ): bool {
        return $this->mutateComponents($settings, $mode, function (array $components) use ($index, $expectedType, $component, $expectedComponent): array {
            $this->assertTarget($components, $index, $expectedType);
            if ($expectedComponent !== null && AdminOrderingState::fingerprints([$components[$index]]) !== AdminOrderingState::fingerprints([$expectedComponent])) {
                throw ValidationException::withMessages(['component' => 'This Home component changed. Reopen the editor.']);
            }
            if (($component['type'] ?? null) !== $expectedType) {
                throw ValidationException::withMessages([
                    'component' => 'The component type changed while it was being edited.',
                ]);
            }

            if ($expectedType === 'list') {
                // Compare using the shared List representation so opening a
                // legacy empty Details field does not create a false change.
                $existingList = new CustomPageSetting(['blocks' => [$components[$index]]]);
                $editedList = new CustomPageSetting(['blocks' => [$component]]);
                if ($existingList->normalizedBlocks() === $editedList->normalizedBlocks()) {
                    return $components;
                }
            }

            $components[$index] = $component;

            return $components;
        });
    }

    /**
     * The canonical component type conversion is common to Home and Custom
     * Pages; this service retains Home's transactional and audit boundary.
     */
    public function convertComponent(
        HomePresentationSetting $settings,
        HomeTemplate $mode,
        int $index,
        string $expectedType,
        string $targetType,
    ): bool {
        return $this->mutateComponents($settings, $mode, function (array $components) use ($index, $expectedType, $targetType): array {
            $this->assertTarget($components, $index, $expectedType);
            if ($expectedType === $targetType) {
                return $components;
            }
            $components[$index] = EditorialComponentConversion::convert($components[$index], $targetType);

            return $components;
        });
    }

    /** @param array<string,mixed> $component */
    public function conversionLosesContent(array $component, string $targetType): bool
    {
        return EditorialComponentConversion::losesContent($component, $targetType);
    }

    public function setComponentPublished(
        HomePresentationSetting $settings, HomeTemplate $mode, int $index, string $expectedType, bool $published,
    ): bool {
        return $this->mutateComponents($settings, $mode, function (array $components) use ($index, $expectedType, $published): array {
            $this->assertTarget($components, $index, $expectedType);
            $components[$index]['published'] = $published;

            return $components;
        });
    }

    /**
     * Ordered children share one transactional mutation path with the Home
     * component sequence and Custom Page validation. Contact targets use
     * stable type keys; List entries use explicit order indices.
     *
     * @param  array<string,mixed>  $payload
     */
    public function mutateChild(
        HomePresentationSetting $settings, HomeTemplate $mode,
        int $index, string $expectedType, string $operation,
        int|string|null $childKey = null, array $payload = [],
        ?string $expectedChildType = null, ?array $expectedChild = null,
    ): bool {
        if (! in_array($expectedType, ['list', 'contact', 'image'], true)
            || ! in_array($operation, ['add', 'update', 'publish', 'move_up', 'move_down', 'reposition', 'delete', 'convert'], true)) {
            throw ValidationException::withMessages(['component' => 'Unsupported Home child action.']);
        }

        return $this->mutateComponents($settings, $mode, function (array $components) use ($index, $expectedType, $operation, $childKey, $payload, $expectedChildType, $expectedChild): array {
            $this->assertTarget($components, $index, $expectedType);
            $field = $expectedType === 'list' ? 'items' : 'children';
            $children = $components[$index][$field] ?? [];
            $children = is_array($children) && array_is_list($children) ? $children : [];

            if ($operation === 'add') {
                if ($expectedType === 'contact'
                    && (! in_array($payload['type'] ?? null, CustomPageSetting::CONTACT_CHILD_TYPES, true)
                        || in_array($payload['type'], array_column($children, 'type'), true))) {
                    throw ValidationException::withMessages(['component' => 'That Contact item already exists or is not supported.']);
                }
                if ($expectedType === 'image'
                    && (! in_array($payload['type'] ?? null, CustomPageSetting::IMAGE_CHILD_TYPES, true)
                        || ! ($components[$index]['image_decorative'] ?? false))) {
                    throw ValidationException::withMessages(['component' => 'Only decorative Images can contain supported child components.']);
                }
                $children[] = $payload;
            } else {
                $position = in_array($expectedType, ['list', 'image'], true)
                    ? (is_int($childKey) ? $childKey : -1)
                    : array_search($childKey, array_column($children, 'type'), true);
                if (! is_int($position) || ! isset($children[$position])) {
                    throw ValidationException::withMessages(['component' => 'This Home child changed. Reload and try again.']);
                }
                if ($expectedChildType !== null && ($children[$position]['type'] ?? null) !== $expectedChildType) {
                    throw ValidationException::withMessages(['component' => 'This child changed type. Reload and try again.']);
                }
                if ($expectedChild !== null
                    && AdminOrderingState::fingerprints([$children[$position]]) !== AdminOrderingState::fingerprints([$expectedChild])) {
                    throw ValidationException::withMessages(['component' => 'This child changed. Reopen the editor.']);
                }
                if ($operation === 'update') {
                    if ($expectedType === 'image') {
                        if (($payload['type'] ?? null) !== ($children[$position]['type'] ?? null)) {
                            throw ValidationException::withMessages([
                                'component' => 'Changing an Image child type requires its dedicated conversion action.',
                            ]);
                        }
                        $children[$position] = $payload;
                    } else {
                        $children[$position] = [
                            ...$payload,
                            'published' => CustomPageSetting::listItemPublished($children[$position]),
                        ];
                        if ($expectedType === 'contact') {
                            $children[$position]['type'] = $childKey;
                        }
                    }
                } elseif ($operation === 'convert') {
                    if ($expectedType !== 'image') {
                        throw ValidationException::withMessages(['component' => 'Only Image children support type conversion.']);
                    }
                    $children[$position] = EditorialComponentConversion::convert(
                        $children[$position], (string) ($payload['type'] ?? ''), insideImage: true,
                    );
                } elseif ($operation === 'publish') {
                    $children[$position]['published'] = ($payload['published'] ?? false) === true;
                } elseif ($operation === 'delete') {
                    CustomPageSetting::assertComponentDeletable($children[$position]);
                    array_splice($children, $position, 1);
                } elseif ($operation === 'reposition') {
                    $moved = array_splice($children, $position, 1);
                    $newPosition = max(0, min((int) ($payload['position'] ?? 0), count($children)));
                    array_splice($children, $newPosition, 0, $moved);
                } else {
                    $target = $position + ($operation === 'move_up' ? -1 : 1);
                    if (isset($children[$target])) {
                        [$children[$position], $children[$target]] = [$children[$target], $children[$position]];
                    }
                }
            }

            $components[$index][$field] = $children;

            return $components;
        }, ordering: str_starts_with($operation, 'move_') || $operation === 'reposition');
    }

    /**
     * Move one edited root component into a decorative Image. The canonical
     * CustomPageSetting validator enforces nested List/Contact restrictions.
     *
     * @param array<string,mixed> $edited
     * @param array<string,mixed>|null $expectedComponent
     */
    public function updateAndNestComponent(
        HomePresentationSetting $settings, HomeTemplate $mode, int $index,
        string $type, array $edited, int $imageIndex, ?array $expectedComponent = null,
    ): bool {
        if (! in_array($type, CustomPageSetting::IMAGE_CHILD_TYPES, true)) {
            throw ValidationException::withMessages(['parent_image_index' => 'This component cannot be placed inside an Image.']);
        }

        return $this->mutateComponents($settings, $mode, function (array $components) use ($index, $type, $edited, $imageIndex, $expectedComponent): array {
            $this->assertTarget($components, $index, $type);
            $this->assertTarget($components, $imageIndex, 'image');
            if ($index === $imageIndex || ! ($components[$imageIndex]['image_decorative'] ?? false)) {
                throw ValidationException::withMessages(['parent_image_index' => 'Choose another decorative Image parent.']);
            }
            if ($expectedComponent !== null
                && AdminOrderingState::fingerprints([$components[$index]]) !== AdminOrderingState::fingerprints([$expectedComponent])) {
                throw ValidationException::withMessages(['component' => 'This Home component changed. Reopen the editor.']);
            }
            if (($edited['type'] ?? null) !== $type) {
                throw ValidationException::withMessages(['component' => 'This Home component changed type.']);
            }

            array_splice($components, $index, 1);
            if ($index < $imageIndex) {
                $imageIndex--;
            }
            $components[$imageIndex]['children'] ??= [];
            $components[$imageIndex]['children'][] = $edited;

            return $components;
        }, ordering: true);
    }

    public function unnestImageChild(
        HomePresentationSetting $settings, HomeTemplate $mode,
        int $imageIndex, int $childIndex, string $expectedChildType,
    ): bool {
        return $this->mutateComponents($settings, $mode, function (array $components) use ($imageIndex, $childIndex, $expectedChildType): array {
            $this->assertTarget($components, $imageIndex, 'image');
            $children = $components[$imageIndex]['children'] ?? [];
            if (! isset($children[$childIndex]) || ($children[$childIndex]['type'] ?? null) !== $expectedChildType) {
                throw ValidationException::withMessages(['component' => 'The Image child changed. Reload and try again.']);
            }
            $child = $children[$childIndex];
            array_splice($children, $childIndex, 1);
            if ($children === []) {
                unset($components[$imageIndex]['children']);
            } else {
                $components[$imageIndex]['children'] = $children;
            }
            array_splice($components, $imageIndex + 1, 0, [$child]);

            return $components;
        }, ordering: true);
    }

    /**
     * Atomically apply selection commands to Home parent components and their
     * List/Contact children. Stale targets fail before any mutation is saved.
     *
     * @param  list<array{index:int,type:string}>  $parents
     * @param  list<array{index:int,type:string,child:int|string}>  $children
     */
    public function changeSelection(
        HomePresentationSetting $settings, HomeTemplate $mode,
        array $parents, array $children, string $operation,
    ): bool {
        if (! in_array($operation, ['publish', 'unpublish', 'delete'], true)) {
            throw ValidationException::withMessages(['component' => 'Invalid selected Home action.']);
        }

        return $this->mutateComponents($settings, $mode, function (array $components) use ($parents, $children, $operation): array {
            $parentIndices = $this->validatedIndices($components, $parents);
            $positionsByParent = [];
            foreach ($children as $target) {
                $index = $target['index'];
                $type = $target['type'];
                $this->assertTarget($components, $index, $type);
                if (! in_array($type, ['list', 'contact', 'image'], true)) {
                    throw ValidationException::withMessages(['component' => 'The selected Home child is invalid.']);
                }
                $field = $type === 'list' ? 'items' : 'children';
                $items = $components[$index][$field] ?? [];
                $position = in_array($type, ['list', 'image'], true)
                    ? $target['child']
                    : array_search($target['child'], array_column($items, 'type'), true);
                if (! is_int($position) || ! isset($items[$position])) {
                    throw ValidationException::withMessages(['component' => 'The selected Home child changed. Reload and try again.']);
                }
                if (in_array($index, $parentIndices, true)) {
                    continue;
                }
                $positionsByParent[$index][$field][] = $position;
            }

            foreach ($positionsByParent as $index => $fieldGroups) {
                foreach ($fieldGroups as $field => $positions) {
                    $positions = array_values(array_unique($positions));
                    if ($operation === 'delete') {
                        rsort($positions);
                    }
                    foreach ($positions as $position) {
                        if ($operation === 'delete') {
                            CustomPageSetting::assertComponentDeletable($components[$index][$field][$position]);
                            array_splice($components[$index][$field], $position, 1);
                        } else {
                            $components[$index][$field][$position]['published'] = $operation === 'publish';
                        }
                    }
                }
            }

            foreach ($parentIndices as $index) {
                if ($operation === 'delete') {
                    CustomPageSetting::assertComponentDeletable($components[$index]);
                    unset($components[$index]);
                } else {
                    $components[$index]['published'] = $operation === 'publish';
                }
            }

            return array_values($components);
        });
    }

    public function moveComponent(
        HomePresentationSetting $settings,
        HomeTemplate $mode,
        int $index,
        string $expectedType,
        string $direction,
    ): bool {
        $this->assertDirection($direction);

        return $this->mutateComponents($settings, $mode, function (array $components) use ($index, $expectedType, $direction): array {
            $this->assertTarget($components, $index, $expectedType);
            $target = $direction === 'up' ? $index - 1 : $index + 1;
            if (! array_key_exists($target, $components)) {
                return $components;
            }

            [$components[$index], $components[$target]] = [$components[$target], $components[$index]];

            return $components;
        }, ordering: true);
    }

    /** @param list<array{index:int,type:string}> $targets */
    public function reorderComponents(HomePresentationSetting $settings, HomeTemplate $mode, array $targets): bool
    {
        return $this->mutateComponents($settings, $mode, function (array $components) use ($targets): array {
            if (count($targets) !== count($components)) {
                throw ValidationException::withMessages([
                    'component' => 'The component sequence changed. Reload the workspace and try again.',
                ]);
            }

            $indices = $this->validatedIndices($components, $targets);
            if (count($indices) !== count($components)) {
                throw ValidationException::withMessages([
                    'component' => 'The component sequence is incomplete.',
                ]);
            }

            return array_map(
                static fn (array $target): array => $components[$target['index']],
                $targets,
            );
        }, ordering: true);
    }

    /** @param list<array{index:int,type:string}> $targets */
    public function moveSelectedComponents(
        HomePresentationSetting $settings,
        HomeTemplate $mode,
        array $targets,
        string $direction,
    ): bool {
        $this->assertDirection($direction);

        return $this->mutateComponents($settings, $mode, function (array $components) use ($targets, $direction): array {
            $indices = $this->validatedIndices($components, $targets);
            if ($indices === []) {
                return $components;
            }

            $selected = array_fill_keys($indices, true);
            $sequence = [];
            foreach ($components as $index => $component) {
                $sequence[] = [
                    'component' => $component,
                    'selected' => isset($selected[$index]),
                ];
            }

            if ($direction === 'up') {
                for ($index = 1, $count = count($sequence); $index < $count; $index++) {
                    if ($sequence[$index]['selected'] && ! $sequence[$index - 1]['selected']) {
                        [$sequence[$index - 1], $sequence[$index]] = [$sequence[$index], $sequence[$index - 1]];
                    }
                }
            } else {
                for ($index = count($sequence) - 2; $index >= 0; $index--) {
                    if ($sequence[$index]['selected'] && ! $sequence[$index + 1]['selected']) {
                        [$sequence[$index], $sequence[$index + 1]] = [$sequence[$index + 1], $sequence[$index]];
                    }
                }
            }

            return array_map(
                static fn (array $item): array => $item['component'],
                $sequence,
            );
        }, ordering: true);
    }

    public function deleteComponent(
        HomePresentationSetting $settings,
        HomeTemplate $mode,
        int $index,
        string $expectedType,
    ): bool {
        return $this->mutateComponents($settings, $mode, function (array $components) use ($index, $expectedType): array {
            $this->assertTarget($components, $index, $expectedType);
            CustomPageSetting::assertComponentDeletable($components[$index]);
            unset($components[$index]);

            return array_values($components);
        });
    }

    /** @param list<array{index:int,type:string}> $targets */
    public function deleteComponents(HomePresentationSetting $settings, HomeTemplate $mode, array $targets): bool
    {
        return $this->mutateComponents($settings, $mode, function (array $components) use ($targets): array {
            $indices = $this->validatedIndices($components, $targets);
            if ($indices === []) {
                return $components;
            }

            foreach ($indices as $index) {
                CustomPageSetting::assertComponentDeletable($components[$index]);
            }
            rsort($indices);
            foreach ($indices as $index) {
                unset($components[$index]);
            }

            return array_values($components);
        });
    }

    /**
     * @param  callable(list<array<string, mixed>>): list<array<string, mixed>>  $mutator
     */
    private function mutateComponents(
        HomePresentationSetting $settings,
        HomeTemplate $mode,
        callable $mutator,
        bool $ordering = false,
    ): bool {
        if (! in_array($mode, [HomeTemplate::UnderConstruction, HomeTemplate::Custom], true)) {
            throw ValidationException::withMessages([
                'component' => 'This Home template does not use editable components.',
            ]);
        }

        return DB::transaction(function () use ($settings, $mode, $mutator, $ordering): bool {
            $fresh = $this->locked($settings);
            $configuration = $this->configuration($fresh);
            $components = $configuration[$mode->value]['components'] ?? [];
            $components = is_array($components) && array_is_list($components) ? $components : [];
            $nextComponents = $mutator($components);
            $configuration[$mode->value]['components'] = $nextComponents;

            $this->validateConfiguration($configuration);
            $fresh->setAttribute('configuration', $configuration);

            return $this->save(
                $fresh,
                $ordering ? [
                    'scope' => 'home-components:'.(int) $fresh->getAttribute('site_section_id').':'.$mode->value,
                    'before' => AdminOrderingState::fingerprints($components),
                    'after' => AdminOrderingState::fingerprints($nextComponents),
                ] : null,
            );
        });
    }

    /** @param array<string, mixed> $configuration */
    private function validateConfiguration(array $configuration): void
    {
        if (! is_bool($configuration[HomeTemplate::UnderConstruction->value]['public_site_gate'] ?? null)
            || ! is_bool($configuration[HomeTemplate::UnderConstruction->value]['image_viewer_enabled'] ?? null)
            || ! is_bool($configuration[HomeTemplate::Custom->value]['image_viewer_enabled'] ?? null)) {
            throw ValidationException::withMessages([
                'configuration' => 'Home presentation settings are invalid.',
            ]);
        }

        $this->validateComponents($configuration[HomeTemplate::UnderConstruction->value]['components'] ?? null);
        $this->validateComponents($configuration[HomeTemplate::Custom->value]['components'] ?? null);
    }

    private function validateComponents(mixed $components): void
    {
        if (! is_array($components) || ! array_is_list($components)) {
            throw ValidationException::withMessages(['components' => 'Home components must be an ordered list.']);
        }

        // One canonical validator for both editorial component surfaces.
        // No second Home-only whitelist for List / Contact / Legal Disclaimer.
        $schema = new CustomPageSetting;
        $schema->setAttribute('blocks', $components);
        $schema->assertEditableComponents();
    }

    /**
     * @param  list<array<string, mixed>>  $components
     * @param  list<array{index:int,type:string}>  $targets
     * @return list<int>
     */
    private function validatedIndices(array $components, array $targets): array
    {
        $indices = [];
        foreach ($targets as $target) {
            $index = $target['index'];
            $this->assertTarget($components, $index, $target['type']);
            $indices[] = $index;
        }

        if (count(array_unique($indices)) !== count($indices)) {
            throw ValidationException::withMessages([
                'component' => 'The selected Home component targets contain duplicates.',
            ]);
        }

        sort($indices);

        return $indices;
    }

    /** @param list<array<string, mixed>> $components */
    private function assertTarget(array $components, int $index, string $expectedType): void
    {
        $component = $components[$index] ?? null;
        if (! is_array($component) || ($component['type'] ?? null) !== $expectedType) {
            throw ValidationException::withMessages([
                'component' => 'This Home component changed. Reload the workspace and try again.',
            ]);
        }
    }

    private function assertDirection(string $direction): void
    {
        if (! in_array($direction, ['up', 'down'], true)) {
            throw ValidationException::withMessages([
                'component' => 'The requested component move is invalid.',
            ]);
        }
    }

    private function nullablePositiveInt(mixed $value): ?int
    {
        if ($value === null || $value === '') {
            return null;
        }

        $id = filter_var($value, FILTER_VALIDATE_INT);

        return $id === false || $id <= 0 ? null : (int) $id;
    }

    private function locked(HomePresentationSetting $settings): HomePresentationSetting
    {
        /** @var HomePresentationSetting $fresh */
        $fresh = HomePresentationSetting::query()
            ->whereKey($settings->getKey())
            ->lockForUpdate()
            ->firstOrFail();

        return $fresh;
    }

    /** @param array{scope:string,before:list<string>,after:list<string>}|null $orderingState */
    private function save(HomePresentationSetting $settings, ?array $orderingState = null): bool
    {
        if ($settings->getAttribute('template') === $settings->getOriginal('template')
            && AdminOrderingState::fingerprints([$settings->getAttribute('configuration')]) === AdminOrderingState::fingerprints([$settings->getOriginal('configuration')])) {
            return false;
        }

        $settings->save();
        $actor = $this->audit->requireActor();
        $sectionId = (int) $settings->getAttribute('site_section_id');
        if ($orderingState !== null) {
            $this->audit->recordOrdering(
                $actor,
                'site_section.home_components_reordered',
                'site_section',
                $sectionId,
                $orderingState['scope'],
                $orderingState['before'],
                $orderingState['after'],
            );
        } else {
            $this->audit->record(
                $actor,
                'site_section.updated',
                'site_section',
                $sectionId,
            );
        }

        return true;
    }
}
