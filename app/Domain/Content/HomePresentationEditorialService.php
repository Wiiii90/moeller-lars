<?php

namespace App\Domain\Content;

use App\Domain\Admin\AdminAuditService;
use App\Domain\Admin\AdminOrderingState;
use App\Domain\Artwork\PublicArtworkQuery;
use App\Models\CustomPageSetting;
use App\Models\HomePresentationSetting;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final class HomePresentationEditorialService
{
    public function __construct(
        private readonly AdminAuditService $audit,
        private readonly PublicArtworkQuery $artworks,
    ) {}

    /** @return array<string, mixed> */
    public static function defaults(): array
    {
        return [
            HomeTemplate::Artwork->value => [
                'show_details' => true,
                'show_gallery_link' => false,
                'show_navigation' => false,
                'enable_viewer' => true,
                'hero_mode' => 'automatic',
                'fixed_artwork_id' => null,
                'pool_rule' => 'newest',
                'pool_year' => null,
                'manual_include_ids' => [],
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
            $artworkSettingsChanged = false;

            if (array_key_exists('show_details', $input) && $input['show_details'] !== null) {
                $configuration[HomeTemplate::Artwork->value]['show_details'] = (bool) $input['show_details'];
            }
            if (array_key_exists('show_gallery_link', $input) && $input['show_gallery_link'] !== null) {
                $configuration[HomeTemplate::Artwork->value]['show_gallery_link'] = (bool) $input['show_gallery_link'];
            }
            if (array_key_exists('hero_mode', $input) && $input['hero_mode'] !== null) {
                $configuration[HomeTemplate::Artwork->value]['hero_mode'] = (string) $input['hero_mode'];
                $artworkSettingsChanged = true;
            }
            if (array_key_exists('fixed_artwork_id', $input)) {
                $configuration[HomeTemplate::Artwork->value]['fixed_artwork_id'] = $this->nullablePositiveInt($input['fixed_artwork_id']);
                $artworkSettingsChanged = true;
            }
            if (array_key_exists('pool_rule', $input) && $input['pool_rule'] !== null) {
                $configuration[HomeTemplate::Artwork->value]['pool_rule'] = (string) $input['pool_rule'];
                $artworkSettingsChanged = true;
            }
            if (array_key_exists('pool_year', $input)) {
                $configuration[HomeTemplate::Artwork->value]['pool_year'] = $this->nullablePositiveInt($input['pool_year']);
                $artworkSettingsChanged = true;
            }
            if (array_key_exists('manual_include_ids', $input)) {
                $configuration[HomeTemplate::Artwork->value]['manual_include_ids'] = $this->positiveIntList($input['manual_include_ids']);
                $artworkSettingsChanged = true;
            }
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
            if ($artworkSettingsChanged) {
                $this->validateArtworkReferences($configuration[HomeTemplate::Artwork->value]);
            }

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

            $components[$index] = $component;

            return $components;
        });
    }

    /**
     * Home presents Heading and Rich Text as separate choices although both
     * persist as text components. Keep conversion in the editorial mutation
     * owner so target checks, validation and audit remain transactional.
     */
    public function convertComponent(
        HomePresentationSetting $settings,
        HomeTemplate $mode,
        int $index,
        string $expectedType,
        string $expectedKind,
        string $targetKind,
    ): bool {
        $this->assertComponentKind($targetKind);

        return $this->mutateComponents($settings, $mode, function (array $components) use ($index, $expectedType, $expectedKind, $targetKind): array {
            $this->assertTarget($components, $index, $expectedType);
            $component = $components[$index];
            if ($this->componentKind($component) !== $expectedKind) {
                throw ValidationException::withMessages(['component' => 'This Home component changed. Reload and try again.']);
            }
            if ($expectedKind === $targetKind) {
                return $components;
            }

            $components[$index] = $this->convertedComponent($component, $targetKind);

            return $components;
        });
    }

    /** @param array<string, mixed> $component */
    public function conversionLosesContent(array $component, string $targetKind): bool
    {
        $this->assertComponentKind($targetKind);
        $currentKind = $this->componentKind($component);
        if ($currentKind === $targetKind) {
            return false;
        }
        if (in_array($currentKind, ['divider', 'list', 'contact', 'legal_disclaimer'], true)) {
            return true;
        }
        if ($currentKind === 'image') {
            return is_numeric($component['media_asset_id'] ?? null)
                || (bool) ($component['image_decorative'] ?? false);
        }
        if ($currentKind === 'heading') {
            // Heading becomes the Rich Text body with no lost content.
            return $targetKind !== 'rich_text' && filled($component['title'] ?? null);
        }

        // Rich Text becomes Heading only with its existing title retained;
        // its body is lost. All other destinations discard both.
        return filled($component['body'] ?? null)
            || ($targetKind !== 'heading' && filled($component['title'] ?? null));
    }

    /** @param array<string, mixed> $component */
    private function convertedComponent(array $component, string $targetKind): array
    {
        $title = is_string($component['title'] ?? null) ? trim($component['title']) : '';
        $body = is_string($component['body'] ?? null) ? trim($component['body']) : '';

        $published = CustomPageSetting::componentPublished($component);

        return [
            'published' => $published,
            ...match ($targetKind) {
                'heading' => ['type' => 'text', 'title' => $title !== '' ? $title : null, 'body' => null],
                'rich_text' => ['type' => 'text', 'title' => null, 'body' => ($body !== '' ? $body : ($title !== '' ? $title : null))],
                'image' => ['type' => 'image', 'media_asset_id' => null, 'image_decorative' => false],
                'divider' => ['type' => 'divider', 'variant' => 'thin'],
                'list' => ['type' => 'list', 'title' => null, 'media_asset_id' => null, 'items' => []],
                'contact' => ['type' => 'contact', 'children' => []],
                'legal_disclaimer' => ['type' => 'legal_disclaimer'],
            },
        ];
    }

    /** @param array<string, mixed> $component */
    private function componentKind(array $component): string
    {
        $type = $component['type'] ?? null;
        if ($type === 'text') {
            return filled($component['title'] ?? null) && blank($component['body'] ?? null) ? 'heading' : 'rich_text';
        }

        return in_array($type, ['image', 'divider', 'list', 'contact', 'legal_disclaimer'], true) ? $type : 'unknown';
    }

    private function assertComponentKind(string $kind): void
    {
        if (! in_array($kind, ['image', 'heading', 'rich_text', 'divider', 'list', 'contact', 'legal_disclaimer'], true)) {
            throw ValidationException::withMessages(['component' => 'Choose a supported Home component type.']);
        }
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
     * @param array<string,mixed> $payload
     */
    public function mutateChild(
        HomePresentationSetting $settings, HomeTemplate $mode,
        int $index, string $expectedType, string $operation,
        int|string|null $childKey = null, array $payload = [],
    ): bool {
        if (! in_array($expectedType, ['list', 'contact'], true)
            || ! in_array($operation, ['add', 'update', 'publish', 'move_up', 'move_down', 'reposition', 'delete'], true)) {
            throw ValidationException::withMessages(['component' => 'Unsupported Home child action.']);
        }

        return $this->mutateComponents($settings, $mode, function (array $components) use ($index, $expectedType, $operation, $childKey, $payload): array {
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
                $children[] = $payload;
            } else {
                $position = $expectedType === 'list'
                    ? (is_int($childKey) ? $childKey : -1)
                    : array_search($childKey, array_column($children, 'type'), true);
                if (! is_int($position) || ! isset($children[$position])) {
                    throw ValidationException::withMessages(['component' => 'This Home child changed. Reload and try again.']);
                }
                if ($operation === 'update') {
                    $children[$position] = [
                        ...$payload,
                        'published' => CustomPageSetting::listItemPublished($children[$position]),
                    ];
                    if ($expectedType === 'contact') {
                        $children[$position]['type'] = $childKey;
                    }
                } elseif ($operation === 'publish') {
                    $children[$position]['published'] = ($payload['published'] ?? false) === true;
                } elseif ($operation === 'delete') {
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
     * Atomically apply selection commands to Home parent components and their
     * List/Contact children. Stale targets fail before any mutation is saved.
     *
     * @param list<array{index:int,type:string}> $parents
     * @param list<array{index:int,type:string,child:int|string}> $children
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
                if (! in_array($type, ['list', 'contact'], true)) {
                    throw ValidationException::withMessages(['component' => 'The selected Home child is invalid.']);
                }
                $field = $type === 'list' ? 'items' : 'children';
                $items = $components[$index][$field] ?? [];
                $position = $type === 'list' ? $target['child'] : array_search($target['child'], array_column($items, 'type'), true);
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
                            array_splice($components[$index][$field], $position, 1);
                        } else {
                            $components[$index][$field][$position]['published'] = $operation === 'publish';
                        }
                    }
                }
            }

            foreach ($parentIndices as $index) {
                if ($operation === 'delete') {
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
        $artwork = $configuration[HomeTemplate::Artwork->value] ?? [];
        $mode = $artwork['hero_mode'] ?? null;
        $fixedId = $artwork['fixed_artwork_id'] ?? null;
        $poolRule = $artwork['pool_rule'] ?? null;
        $poolYear = $artwork['pool_year'] ?? null;
        $manualIds = $artwork['manual_include_ids'] ?? null;

        if (! is_bool($artwork['show_details'] ?? null)
            || ! is_bool($artwork['show_gallery_link'] ?? null)
            || ! is_string($mode)
            || ! in_array($mode, ['automatic', 'fixed', 'random'], true)
            || ($fixedId !== null && (! is_int($fixedId) || $fixedId <= 0))
            || ! is_string($poolRule)
            || ! in_array($poolRule, ['newest', 'year'], true)
            || ($poolYear !== null && (! is_int($poolYear) || $poolYear < 1000 || $poolYear > 3000))
            || ! is_array($manualIds)
            || ! array_is_list($manualIds)
            || ! $this->isPositiveIntList($manualIds)
            || ! is_bool($configuration[HomeTemplate::UnderConstruction->value]['public_site_gate'] ?? null)
            || ! is_bool($configuration[HomeTemplate::UnderConstruction->value]['image_viewer_enabled'] ?? null)
            || ! is_bool($configuration[HomeTemplate::Custom->value]['image_viewer_enabled'] ?? null)) {
            throw ValidationException::withMessages([
                'configuration' => 'Home presentation settings are invalid.',
            ]);
        }

        if ($mode === 'fixed' && $fixedId === null) {
            throw ValidationException::withMessages([
                'fixed_artwork_id' => 'Choose an eligible artwork for Fixed mode.',
            ]);
        }

        if ($poolRule === 'year' && $poolYear === null) {
            throw ValidationException::withMessages([
                'pool_year' => 'Choose a year for the specific-year candidate pool.',
            ]);
        }

        $this->validateComponents($configuration[HomeTemplate::UnderConstruction->value]['components'] ?? null);
        $this->validateComponents($configuration[HomeTemplate::Custom->value]['components'] ?? null);
    }

    /** @param array<string, mixed> $artwork */
    private function validateArtworkReferences(array $artwork): void
    {
        $manualIds = $this->positiveIntList($artwork['manual_include_ids'] ?? []);
        $ids = $manualIds;
        $fixedId = $artwork['fixed_artwork_id'] ?? null;
        if (is_int($fixedId) && $fixedId > 0) {
            $ids[] = $fixedId;
        }
        $ids = array_values(array_unique($ids));

        if ($ids !== []) {
            $validIds = $this->artworks->homeCandidatesByIds($ids)
                ->pluck('id')
                ->map(static fn (mixed $id): int => (int) $id)
                ->all();
            sort($ids);
            sort($validIds);

            if ($ids !== $validIds) {
                throw ValidationException::withMessages([
                    'hero_artwork' => 'Hero artwork choices must be eligible published artworks from enabled Home source Galleries.',
                ]);
            }
        }

        if (($artwork['hero_mode'] ?? null) === 'random') {
            $poolCount = $this->artworks->homePoolCandidateCount(
                (string) ($artwork['pool_rule'] ?? 'newest'),
                is_int($artwork['pool_year'] ?? null) ? $artwork['pool_year'] : null,
                $manualIds,
            );

            if ($poolCount < 1) {
                throw ValidationException::withMessages([
                    'hero_mode' => 'Random Pool needs at least one eligible Hero candidate.',
                ]);
            }
        }
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

    /** @return list<int> */
    private function positiveIntList(mixed $values): array
    {
        if (! is_array($values)) {
            return [];
        }

        $ids = [];
        foreach ($values as $value) {
            $id = $this->nullablePositiveInt($value);
            if ($id !== null) {
                $ids[] = $id;
            }
        }

        return array_values(array_unique($ids));
    }

    /** @param list<mixed> $values */
    private function isPositiveIntList(array $values): bool
    {
        foreach ($values as $value) {
            if (! is_int($value) || $value <= 0) {
                return false;
            }
        }

        return count($values) === count(array_unique($values));
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
