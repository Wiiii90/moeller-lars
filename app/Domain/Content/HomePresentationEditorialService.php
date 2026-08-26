<?php

namespace App\Domain\Content;

use App\Domain\Admin\AdminAuditService;
use App\Domain\Artwork\PublicArtworkQuery;
use App\Models\HomePresentationSetting;
use App\Models\MediaAsset;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final class HomePresentationEditorialService
{
    public function __construct(
        private readonly AdminAuditService $audit,
        private readonly SafeRichTextRenderer $richText,
        private readonly PublicArtworkQuery $artworks,
    ) {}

    /** @return array<string, mixed> */
    public static function defaults(): array
    {
        return [
            HomeTemplate::Artwork->value => [
                'show_details' => true,
                'show_gallery_link' => true,
                'hero_mode' => 'automatic',
                'fixed_artwork_id' => null,
                'pool_rule' => 'newest',
                'pool_year' => null,
                'manual_include_ids' => [],
            ],
            HomeTemplate::UnderConstruction->value => [
                'public_site_gate' => false,
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
            if (array_key_exists('public_site_gate', $input) && $input['public_site_gate'] !== null) {
                $configuration[HomeTemplate::UnderConstruction->value]['public_site_gate'] = (bool) $input['public_site_gate'];
            }

            $this->validateConfiguration($configuration);
            if ($artworkSettingsChanged) {
                $this->validateArtworkReferences($configuration[HomeTemplate::Artwork->value]);
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

            return array_values($components);
        });
    }

    /** @param array<string, mixed> $component */
    public function updateComponent(
        HomePresentationSetting $settings,
        HomeTemplate $mode,
        int $index,
        string $expectedType,
        array $component,
    ): bool {
        return $this->mutateComponents($settings, $mode, function (array $components) use ($index, $expectedType, $component): array {
            $this->assertTarget($components, $index, $expectedType);
            if (($component['type'] ?? null) !== $expectedType) {
                throw ValidationException::withMessages([
                    'component' => 'The component type changed while it was being edited.',
                ]);
            }

            $components[$index] = $component;

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

            return array_values($components);
        });
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

            return array_values(array_map(
                static fn (array $target): array => $components[$target['index']],
                $targets,
            ));
        });
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

            return array_values(array_map(
                static fn (array $item): array => $item['component'],
                $sequence,
            ));
        });
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
     * @param callable(list<array<string, mixed>>): list<array<string, mixed>> $mutator
     */
    private function mutateComponents(
        HomePresentationSetting $settings,
        HomeTemplate $mode,
        callable $mutator,
    ): bool {
        if (! in_array($mode, [HomeTemplate::UnderConstruction, HomeTemplate::Custom], true)) {
            throw ValidationException::withMessages([
                'component' => 'This Home template does not use editable components.',
            ]);
        }

        return DB::transaction(function () use ($settings, $mode, $mutator): bool {
            $fresh = $this->locked($settings);
            $configuration = $this->configuration($fresh);
            $components = $configuration[$mode->value]['components'] ?? [];
            $components = is_array($components) && array_is_list($components) ? $components : [];
            $configuration[$mode->value]['components'] = $mutator($components);

            $this->validateConfiguration($configuration);
            $fresh->setAttribute('configuration', $configuration);

            return $this->save($fresh);
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
            || ! is_bool($configuration[HomeTemplate::UnderConstruction->value]['public_site_gate'] ?? null)) {
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
            throw ValidationException::withMessages([
                'components' => 'Home components must be an ordered list.',
            ]);
        }

        foreach ($components as $index => $component) {
            if (! is_array($component)
                || ! is_string($component['type'] ?? null)
                || ! in_array($component['type'], ['image', 'text', 'divider'], true)) {
                throw ValidationException::withMessages([
                    'components' => 'Home supports Image, Text and Divider components.',
                ]);
            }

            if ($component['type'] === 'image') {
                $this->validateImage($component);
            }
            if ($component['type'] === 'text') {
                $this->validateText($component, $index);
            }
        }
    }

    /** @param array<string, mixed> $component */
    private function validateImage(array $component): void
    {
        if (! is_bool($component['image_decorative'] ?? false)) {
            throw ValidationException::withMessages([
                'components' => 'Home image presentation settings are invalid.',
            ]);
        }

        $mediaId = $component['media_asset_id'] ?? null;
        if ($mediaId === null) {
            return;
        }

        $id = filter_var($mediaId, FILTER_VALIDATE_INT);
        /** @var MediaAsset|null $asset */
        $asset = $id === false ? null : MediaAsset::query()->find((int) $id);
        if (! $asset instanceof MediaAsset
            || (string) $asset->getAttribute('state') !== 'available'
            || ! str_starts_with((string) $asset->getAttribute('mime_type'), 'image/')) {
            throw ValidationException::withMessages([
                'components' => 'Home images must reference an available image from Media Files.',
            ]);
        }

        if (! (bool) ($component['image_decorative'] ?? false)) {
            $alt = $asset->getAttribute('alt_text');
            if (! is_string($alt) || trim($alt) === '') {
                throw ValidationException::withMessages([
                    'components' => 'Non-decorative Home images need canonical ALT text in Media Files.',
                ]);
            }
        }
    }

    /** @param array<string, mixed> $component */
    private function validateText(array $component, int $index): void
    {
        $title = $component['title'] ?? null;
        if ($title !== null && (! is_string($title) || mb_strlen($title) > 160)) {
            throw ValidationException::withMessages([
                'components' => 'Home component headings must be short text.',
            ]);
        }

        $body = $component['body'] ?? null;
        if ($body === null || $body === '') {
            return;
        }
        if (! is_string($body) || mb_strlen($body) > 20000) {
            throw ValidationException::withMessages([
                'components.'.$index.'.body' => 'Home rich text is too long.',
            ]);
        }

        $this->richText->assertValid($body, allowEmbeddedMedia: true);
    }

    /**
     * @param list<array<string, mixed>> $components
     * @param list<array{index:int,type:string}> $targets
     * @return list<int>
     */
    private function validatedIndices(array $components, array $targets): array
    {
        $indices = [];
        foreach ($targets as $target) {
            if (! is_array($target)
                || ! is_int($target['index'] ?? null)
                || ! is_string($target['type'] ?? null)) {
                throw ValidationException::withMessages([
                    'component' => 'The selected Home component target is invalid.',
                ]);
            }

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

    private function save(HomePresentationSetting $settings): bool
    {
        if (! $settings->isDirty(['template', 'configuration'])) {
            return false;
        }

        $settings->save();
        $actor = $this->audit->requireActor();
        $this->audit->record(
            $actor,
            'site_section.updated',
            'site_section',
            (int) $settings->getAttribute('site_section_id'),
        );

        return true;
    }
}
