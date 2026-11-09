<?php

namespace App\Domain\Content;

use App\Domain\Admin\AdminAuditService;
use App\Models\HomePresentationSetting;
use App\Models\MediaAsset;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final class HomeHeroConfigurationService
{
    public const DEFAULT_GROUP_SIZE = 12;

    public const MAX_GROUP_SIZE = 50;

    public const WEIGHT_TOTAL = 10000;

    /** @var array{count:int,unit:string} */
    public const DEFAULT_ROTATION_INTERVAL = [
        'count' => 1,
        'unit' => 'weeks',
    ];

    public function __construct(private readonly AdminAuditService $audit) {}

    /**
     * Canonical Hero configuration. Legacy fixed/automatic/random values are
     * accepted as historic input, never persisted as a second model.
     *
     * @return array{
     *     group_source:string,
     *     display_strategy:string,
     *     manual_group:list<array{artwork_id?:int,media_asset_id?:int,weight:int}>,
     *     manual_group_count:int,
     *     rotation_interval:array{count:int,unit:string},
     *     rotation_started_at:?string,
     *     newest_by:string,
     *     group_size:int,
     *     show_details:bool,
     *     show_gallery_link:bool,
     *     show_navigation:bool,
     *     video_sound_enabled:bool,
     *     enable_viewer:bool
     * }
     */
    public function configuration(HomePresentationSetting $settings): array
    {
        $root = $settings->configuration();
        $artwork = is_array($root[HomeTemplate::Artwork->value] ?? null)
            ? $root[HomeTemplate::Artwork->value]
            : [];

        $persistedMode = is_string($artwork['hero_mode'] ?? null)
            ? $artwork['hero_mode']
            : 'automatic';

        $groupSource = $this->enumOrNull($artwork['group_source'] ?? null, ['automatic', 'manual'])
            ?? (in_array($persistedMode, ['fixed', 'manual'], true) ? 'manual' : 'automatic');

        $displayStrategy = $this->enumOrNull($artwork['display_strategy'] ?? null, ['ordered', 'random', 'sequential']);
        if ($displayStrategy === null) {
            $legacySelection = $persistedMode === 'random'
                ? 'random'
                : $this->enum($artwork['automatic_selection'] ?? null, ['newest', 'random'], 'newest');
            $displayStrategy = $legacySelection === 'random' ? 'random' : 'ordered';
        }

        $manualGroup = $this->readManualGroup($artwork['manual_group'] ?? null);
        $legacyFixedId = $this->nullablePositiveInt($artwork['fixed_artwork_id'] ?? null);
        if ($manualGroup === [] && $legacyFixedId !== null && in_array($persistedMode, ['fixed', 'manual'], true)) {
            $manualGroup = [[
                'artwork_id' => $legacyFixedId,
                'weight' => self::WEIGHT_TOTAL,
            ]];
        }

        $newestBy = $this->enum($artwork['newest_by'] ?? null, ['artwork_date', 'added'], 'artwork_date');
        $rotationInterval = $this->readRotationInterval($artwork['rotation_interval'] ?? null);
        $rotationStartedAt = $this->readRotationStartedAt($artwork['rotation_started_at'] ?? null);

        return [
            'group_source' => $groupSource,
            'display_strategy' => $displayStrategy,
            'manual_group' => $manualGroup,
            'manual_group_count' => count($manualGroup),
            'rotation_interval' => $rotationInterval,
            'rotation_started_at' => $rotationStartedAt,
            'newest_by' => $newestBy,
            'group_size' => $this->boundedGroupSize($artwork['group_size'] ?? null),
            'show_details' => (bool) ($artwork['show_details'] ?? true),
            'show_gallery_link' => (bool) ($artwork['show_gallery_link'] ?? false),
            'show_navigation' => (bool) ($artwork['show_navigation'] ?? false),
            'video_sound_enabled' => (bool) ($artwork['video_sound_enabled'] ?? false),
            'enable_viewer' => (bool) ($artwork['enable_viewer'] ?? true),
        ];
    }

    /**
     * @param  array<string, mixed>  $input
     * @param  array{before:list<int|string>,after:list<int|string>}|null  $orderingState
     */
    public function updateArtworkSettings(HomePresentationSetting $settings, array $input, ?array $orderingState = null): bool
    {
        return DB::transaction(function () use ($settings, $input, $orderingState): bool {
            /** @var HomePresentationSetting $fresh */
            $fresh = HomePresentationSetting::query()
                ->whereKey($settings->getKey())
                ->lockForUpdate()
                ->firstOrFail();

            $current = $this->configuration($fresh);
            $groupSource = $this->requestedGroupSource($input, $current['group_source']);
            $displayStrategy = $this->requestedDisplayStrategy($input, $current['display_strategy']);
            $newestBy = $this->enum($input['newest_by'] ?? $current['newest_by'], ['artwork_date', 'added'], 'artwork_date');
            $groupSize = $this->validatedGroupSize($input['group_size'] ?? $current['group_size']);
            $manualGroup = $current['manual_group'];

            if (array_key_exists('manual_group', $input)) {
                $manualGroup = $this->normalizeManualGroup($input['manual_group']);
            }

            $rotationInterval = $this->requestedRotationInterval($input, $current['rotation_interval']);

            if ($groupSource === 'manual' && $manualGroup === []) {
                throw ValidationException::withMessages([
                    'manual_group' => 'Manual Home groups need at least one artwork.',
                ]);
            }

            $rotationStartedAt = $current['rotation_started_at'];
            $showDetails = (bool) ($input['show_details'] ?? $current['show_details']);
            $showGalleryLink = (bool) ($input['show_gallery_link'] ?? $current['show_gallery_link']);
            $showNavigation = (bool) ($input['show_navigation'] ?? $current['show_navigation']);
            $videoSoundEnabled = (bool) ($input['video_sound_enabled'] ?? $current['video_sound_enabled']);
            $enableViewer = (bool) ($input['enable_viewer'] ?? $current['enable_viewer']);
            $manualSequenceChanged = $this->manualGroupKeys($manualGroup) !== $this->manualGroupKeys($current['manual_group']);
            $intervalChanged = $rotationInterval !== $current['rotation_interval'];
            $sourceChanged = $groupSource !== $current['group_source'];
            $strategyChanged = $displayStrategy !== $current['display_strategy'];
            $automaticGroupDefinitionChanged = $groupSource === 'automatic'
                && ($groupSize !== $current['group_size'] || $newestBy !== $current['newest_by']);

            if ($displayStrategy === 'sequential'
                && ($rotationStartedAt === null
                    || $manualSequenceChanged
                    || $intervalChanged
                    || $sourceChanged
                    || $strategyChanged
                    || $automaticGroupDefinitionChanged)) {
                $rotationStartedAt = CarbonImmutable::now('UTC')->toIso8601String();
            }

            if (
                $fresh->template() === HomeTemplate::Artwork
                && $groupSource === $current['group_source']
                && $displayStrategy === $current['display_strategy']
                && $manualGroup === $current['manual_group']
                && $rotationInterval === $current['rotation_interval']
                && $rotationStartedAt === $current['rotation_started_at']
                && $newestBy === $current['newest_by']
                && $groupSize === $current['group_size']
                && $showDetails === $current['show_details']
                && $showGalleryLink === $current['show_gallery_link']
                && $showNavigation === $current['show_navigation']
                && $videoSoundEnabled === $current['video_sound_enabled']
                && $enableViewer === $current['enable_viewer']
            ) {
                return false;
            }

            $root = $fresh->configuration();
            $artwork = is_array($root[HomeTemplate::Artwork->value] ?? null)
                ? $root[HomeTemplate::Artwork->value]
                : [];

            $artwork['show_details'] = $showDetails;
            $artwork['show_gallery_link'] = $showGalleryLink;
            $artwork['show_navigation'] = $showNavigation;
            $artwork['video_sound_enabled'] = $videoSoundEnabled;
            $artwork['enable_viewer'] = $enableViewer;
            $artwork['group_source'] = $groupSource;
            $artwork['display_strategy'] = $displayStrategy;
            $artwork['manual_group'] = $manualGroup;
            $artwork['rotation_interval'] = $rotationInterval;
            $artwork['rotation_started_at'] = $rotationStartedAt;

            // Historical keys are read above but no longer written. There is
            // exactly one authoritative group and display strategy.
            unset($artwork['hero_mode'], $artwork['automatic_selection'], $artwork['fixed_artwork_id']);
            $artwork['newest_by'] = $newestBy;
            $artwork['group_size'] = $groupSize;
            unset($artwork['pool_rule'], $artwork['pool_year'], $artwork['manual_include_ids']);

            $root[HomeTemplate::Artwork->value] = $artwork;
            $fresh->setAttribute('template', HomeTemplate::Artwork->value);
            $fresh->setAttribute('configuration', $root);

            if (! $fresh->isDirty(['template', 'configuration'])) {
                return false;
            }

            $fresh->save();
            $actor = $this->audit->requireActor();
            $sectionId = (int) $fresh->getAttribute('site_section_id');
            if ($orderingState !== null) {
                $this->audit->recordOrdering(
                    $actor,
                    'site_section.home_artworks_reordered',
                    'site_section',
                    $sectionId,
                    'home-artworks:'.$sectionId,
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
        });
    }

    /** Add a selected Storage asset without copying files or inventing an Artwork. */
    public function addCandidate(HomePresentationSetting $settings, int $mediaAssetId): bool
    {
        $asset = $mediaAssetId > 0
            ? MediaAsset::query()->with('variants')->find($mediaAssetId)
            : null;
        if (! $asset instanceof MediaAsset || ! HomeHeroCandidate::mediaIsEligible($asset)) {
            throw ValidationException::withMessages([
                'media_asset_id' => 'Choose an available image with a public preview and ALT text, or an available video with ALT text.',
            ]);
        }

        $current = $this->configuration($settings->fresh());
        $group = $current['manual_group'];
        if (count($group) >= self::MAX_GROUP_SIZE) {
            throw ValidationException::withMessages([
                'media_asset_id' => 'The Hero group cannot exceed '.self::MAX_GROUP_SIZE.' explicitly selected candidates.',
            ]);
        }
        if (in_array('media:'.$mediaAssetId, $this->manualGroupKeys($group), true)) {
            throw ValidationException::withMessages(['media_asset_id' => 'That file is already selected.']);
        }

        $member = ['media_asset_id' => $mediaAssetId];
        if ($group === []) {
            $group[] = [...$member, 'weight' => self::WEIGHT_TOTAL];
        } else {
            $weight = intdiv(self::WEIGHT_TOTAL, count($group) + 1);
            $scaled = $this->allocateBudget(array_column($group, 'weight'), self::WEIGHT_TOTAL - $weight);
            foreach ($group as $index => $existing) {
                $group[$index]['weight'] = $scaled[$index];
            }
            $group[] = [...$member, 'weight' => $weight];
        }

        return $this->updateArtworkSettings($settings, ['manual_group' => $group]);
    }

    public function removeCandidate(HomePresentationSetting $settings, string $key): bool
    {
        $current = $this->configuration($settings->fresh());
        $group = $current['manual_group'];
        $index = array_search($key, $this->manualGroupKeys($group), true);
        if ($index === false) {
            throw ValidationException::withMessages(['candidate' => 'This candidate is not in the selected group.']);
        }
        if ($current['group_source'] === 'manual' && count($group) === 1) {
            throw ValidationException::withMessages(['candidate' => 'The active Manual Home group cannot be empty.']);
        }

        array_splice($group, $index, 1);
        if ($group !== []) {
            $group = $this->normalizeManualGroup($group);
        }

        return $this->updateArtworkSettings($settings, ['manual_group' => $group]);
    }

    /** @param list<string> $keys */
    public function reorderManualGroup(HomePresentationSetting $settings, array $keys): bool
    {
        $current = $this->configuration($settings->fresh());
        $group = $current['manual_group'];
        $expected = $this->manualGroupKeys($group);
        if (count($keys) !== count(array_unique($keys))) {
            throw ValidationException::withMessages(['candidate' => 'Home group order contains duplicates.']);
        }

        $left = $expected;
        $right = $keys;
        sort($left);
        sort($right);
        if ($left !== $right) {
            throw ValidationException::withMessages(['candidate' => 'Home group order has changed. Reload and try again.']);
        }
        $byKey = [];
        foreach ($group as $member) {
            $byKey[self::memberKey($member)] = $member;
        }

        return $this->updateArtworkSettings(
            $settings,
            ['manual_group' => array_map(static fn (string $key): array => $byKey[$key], $keys)],
            ['before' => $expected, 'after' => $keys],
        );
    }

    public function updateCandidateWeight(HomePresentationSetting $settings, string $key, int $basisPoints): bool
    {
        if ($basisPoints < 0 || $basisPoints > self::WEIGHT_TOTAL) {
            throw ValidationException::withMessages(['weight' => 'Hero percentage must be between 0 and 100.']);
        }
        $group = $this->configuration($settings->fresh())['manual_group'];
        $target = array_search($key, $this->manualGroupKeys($group), true);
        if ($target === false) {
            throw ValidationException::withMessages(['candidate' => 'The selected Hero candidate changed.']);
        }
        if (count($group) === 1) {
            $group[0]['weight'] = self::WEIGHT_TOTAL;

            return $this->updateArtworkSettings($settings, ['manual_group' => $group]);
        }
        $indices = [];
        $other = [];
        foreach ($group as $index => $member) {
            if ($index === $target) {
                continue;
            }
            $indices[] = $index;
            $other[] = $member['weight'];
        }
        $scaled = $this->allocateBudget($other, self::WEIGHT_TOTAL - $basisPoints);
        foreach ($indices as $offset => $index) {
            $group[$index]['weight'] = $scaled[$offset];
        }
        $group[$target]['weight'] = $basisPoints;

        return $this->updateArtworkSettings($settings, ['manual_group' => $group]);
    }

    /** @return list<array{artwork_id?:int,media_asset_id?:int,weight:int}> */
    public function normalizeManualGroup(mixed $value): array
    {
        if (! is_array($value) || ! array_is_list($value)) {
            throw ValidationException::withMessages(['manual_group' => 'The Hero group must be an ordered list.']);
        }
        $members = [];
        $seen = [];
        foreach ($value as $member) {
            if (! is_array($member)) {
                throw ValidationException::withMessages(['manual_group' => 'Hero candidates must be structured values.']);
            }
            $identity = $this->memberIdentity($member);
            $weight = filter_var($member['weight'] ?? null, FILTER_VALIDATE_INT);
            if ($identity === null || $weight === false || $weight < 0 || $weight > self::WEIGHT_TOTAL) {
                throw ValidationException::withMessages(['manual_group' => 'Choose a valid Hero candidate and percentage.']);
            }
            $key = self::memberKey($identity);
            if (isset($seen[$key])) {
                throw ValidationException::withMessages(['manual_group' => 'The Hero group cannot contain duplicate candidates.']);
            }
            $seen[$key] = true;
            $members[] = [...$identity, 'weight' => (int) $weight];
        }
        if ($members === []) {
            return [];
        }

        $weights = $this->allocateBudget(array_column($members, 'weight'), self::WEIGHT_TOTAL);
        foreach ($members as $index => $member) {
            $members[$index]['weight'] = $weights[$index];
        }

        return $members;
    }

    /** @param list<array<string,int>> $group
     * @return list<string>
     */
    private function manualGroupKeys(array $group): array
    {
        return array_map(self::memberKey(...), $group);
    }

    /** @param array<string,mixed> $member
     * @return array{artwork_id?:int,media_asset_id?:int}|null
     */
    private function memberIdentity(array $member): ?array
    {
        $artworkId = $this->nullablePositiveInt($member['artwork_id'] ?? null);
        $mediaId = $this->nullablePositiveInt($member['media_asset_id'] ?? null);
        if (($artworkId === null) === ($mediaId === null)) {
            return null;
        }

        return $artworkId !== null ? ['artwork_id' => $artworkId] : ['media_asset_id' => $mediaId];
    }

    /** @param array<string,int> $member */
    public static function memberKey(array $member): string
    {
        if (isset($member['media_asset_id'])) {
            return 'media:'.$member['media_asset_id'];
        }

        return 'artwork:'.$member['artwork_id'];
    }

    /** @return list<array{artwork_id?:int,media_asset_id?:int,weight:int}> */
    private function readManualGroup(mixed $value): array
    {
        if (! is_array($value) || ! array_is_list($value)) {
            return [];
        }
        $members = [];
        $seen = [];
        foreach ($value as $member) {
            if (! is_array($member)) {
                continue;
            }
            $identity = $this->memberIdentity($member);
            $weight = filter_var($member['weight'] ?? null, FILTER_VALIDATE_INT);
            if ($identity === null || $weight === false || $weight < 0 || $weight > self::WEIGHT_TOTAL) {
                continue;
            }
            $key = self::memberKey($identity);
            if (isset($seen[$key])) {
                continue;
            }
            $seen[$key] = true;
            $members[] = [...$identity, 'weight' => (int) $weight];
        }

        return $members === [] ? [] : $this->normalizeManualGroup($members);
    }

    /** @param list<int> $weights
     * @return list<int>
     */
    private function allocateBudget(array $weights, int $budget): array
    {
        if ($weights === []) {
            return [];
        }
        if ($budget < 0) {
            throw ValidationException::withMessages(['weight' => 'Manual Home weight budget is invalid.']);
        }

        $sum = array_sum($weights);
        if ($sum <= 0) {
            $base = intdiv($budget, count($weights));
            $remainder = $budget - ($base * count($weights));

            return array_map(
                static fn (int $index): int => $base + ($index < $remainder ? 1 : 0),
                array_keys($weights),
            );
        }

        $allocated = [];
        $remainders = [];
        $used = 0;
        foreach ($weights as $index => $weight) {
            $numerator = $weight * $budget;
            $floor = intdiv($numerator, $sum);
            $allocated[$index] = $floor;
            $remainders[$index] = $numerator % $sum;
            $used += $floor;
        }

        $left = $budget - $used;
        $order = array_keys($weights);
        usort($order, static function (int $leftIndex, int $rightIndex) use ($remainders): int {
            $comparison = $remainders[$rightIndex] <=> $remainders[$leftIndex];

            return $comparison !== 0 ? $comparison : ($leftIndex <=> $rightIndex);
        });
        for ($offset = 0; $offset < $left; $offset++) {
            $allocated[$order[$offset % count($order)]]++;
        }
        ksort($allocated);

        return array_values($allocated);
    }

    /** @param array<string, mixed> $input */
    private function requestedGroupSource(array $input, string $current): string
    {
        return array_key_exists('group_source', $input)
            ? $this->enum($input['group_source'], ['automatic', 'manual'], $current)
            : $current;
    }

    /** @param array<string, mixed> $input */
    private function requestedDisplayStrategy(array $input, string $current): string
    {
        return array_key_exists('display_strategy', $input)
            ? $this->enum($input['display_strategy'], ['ordered', 'random', 'sequential'], $current)
            : $current;
    }

    /** @param array<string, mixed> $input
     * @param  array{count:int,unit:string}  $current
     * @return array{count:int,unit:string}
     */
    private function requestedRotationInterval(array $input, array $current): array
    {
        if (array_key_exists('rotation_interval', $input)) {
            return $this->validatedRotationInterval($input['rotation_interval']);
        }
        if (array_key_exists('rotation_interval_count', $input) || array_key_exists('rotation_interval_unit', $input)) {
            return $this->validatedRotationInterval([
                'count' => $input['rotation_interval_count'] ?? $current['count'],
                'unit' => $input['rotation_interval_unit'] ?? $current['unit'],
            ]);
        }

        return $current;
    }

    /** @return array{count:int,unit:string} */
    private function readRotationInterval(mixed $value): array
    {
        if (! is_array($value)) {
            return self::DEFAULT_ROTATION_INTERVAL;
        }
        $count = filter_var($value['count'] ?? null, FILTER_VALIDATE_INT);
        $unit = $this->enumOrNull($value['unit'] ?? null, ['days', 'weeks', 'months']);
        if ($count === false || $count < 1 || $unit === null) {
            return self::DEFAULT_ROTATION_INTERVAL;
        }

        return ['count' => (int) $count, 'unit' => $unit];
    }

    /** @return array{count:int,unit:string} */
    private function validatedRotationInterval(mixed $value): array
    {
        if (! is_array($value)) {
            throw ValidationException::withMessages(['rotation_interval' => 'Choose a valid Sequential rotation interval.']);
        }
        $count = filter_var($value['count'] ?? null, FILTER_VALIDATE_INT);
        $unit = $this->enumOrNull($value['unit'] ?? null, ['days', 'weeks', 'months']);
        if ($count === false || $count < 1 || $unit === null) {
            throw ValidationException::withMessages(['rotation_interval' => 'Sequential rotation interval needs a positive count in days, weeks or calendar months.']);
        }

        return ['count' => (int) $count, 'unit' => $unit];
    }

    private function readRotationStartedAt(mixed $value): ?string
    {
        if (! is_string($value) || trim($value) === '') {
            return null;
        }

        try {
            return CarbonImmutable::parse($value)->utc()->toIso8601String();
        } catch (\Throwable) {
            return null;
        }
    }

    private function enum(mixed $value, array $allowed, string $default): string
    {
        return is_string($value) && in_array($value, $allowed, true) ? $value : $default;
    }

    private function enumOrNull(mixed $value, array $allowed): ?string
    {
        return is_string($value) && in_array($value, $allowed, true) ? $value : null;
    }

    private function boundedGroupSize(mixed $value): int
    {
        $size = filter_var($value, FILTER_VALIDATE_INT);
        if ($size === false || $size < 1) {
            return self::DEFAULT_GROUP_SIZE;
        }

        return min((int) $size, self::MAX_GROUP_SIZE);
    }

    private function validatedGroupSize(mixed $value): int
    {
        $size = filter_var($value, FILTER_VALIDATE_INT);
        if ($size === false || $size < 1 || $size > self::MAX_GROUP_SIZE) {
            throw ValidationException::withMessages([
                'group_size' => 'Group size must be between 1 and '.self::MAX_GROUP_SIZE.'.',
            ]);
        }

        return (int) $size;
    }

    private function nullablePositiveInt(mixed $value): ?int
    {
        if ($value === null || $value === '') {
            return null;
        }

        $id = filter_var($value, FILTER_VALIDATE_INT);

        return $id === false || $id <= 0 ? null : (int) $id;
    }

}
