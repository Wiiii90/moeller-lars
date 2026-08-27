<?php

namespace App\Domain\Content;

use App\Domain\Admin\AdminAuditService;
use App\Domain\Artwork\PublicArtworkQuery;
use App\Models\HomePresentationSetting;
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

    public function __construct(
        private readonly AdminAuditService $audit,
        private readonly PublicArtworkQuery $artworks,
    ) {}

    /**
     * Canonical Hero configuration. New group-source/display-strategy keys are authoritative when
     * present; the legacy fixed/automatic/random representation remains losslessly readable.
     *
     * @return array{
     *     group_source:string,
     *     display_strategy:string,
     *     manual_group:list<array{artwork_id:int,weight:int}>,
     *     manual_group_count:int,
     *     rotation_interval:array{count:int,unit:string},
     *     rotation_started_at:?string,
     *     mode:string,
     *     hero_artwork_id:?int,
     *     selection:string,
     *     newest_by:string,
     *     group_size:int,
     *     candidate_filter:string,
     *     specific_year:?int,
     *     manual_include_ids:list<int>,
     *     show_details:bool,
     *     show_gallery_link:bool
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
        $candidateFilter = ($artwork['pool_rule'] ?? null) === 'year' ? 'year' : 'all';
        $rotationInterval = $this->readRotationInterval($artwork['rotation_interval'] ?? null);
        $rotationStartedAt = $this->readRotationStartedAt($artwork['rotation_started_at'] ?? null);
        $heroArtworkId = $legacyFixedId ?? ($manualGroup[0]['artwork_id'] ?? null);

        return [
            'group_source' => $groupSource,
            'display_strategy' => $displayStrategy,
            'manual_group' => $manualGroup,
            'manual_group_count' => count($manualGroup),
            'rotation_interval' => $rotationInterval,
            'rotation_started_at' => $rotationStartedAt,
            // Compatibility aliases for the current admin while the UI is reconciled in the next tranche.
            'mode' => $groupSource,
            'hero_artwork_id' => $heroArtworkId,
            'selection' => $displayStrategy === 'random' ? 'random' : 'newest',
            'newest_by' => $newestBy,
            'group_size' => $this->boundedGroupSize($artwork['group_size'] ?? null),
            'candidate_filter' => $candidateFilter,
            'specific_year' => $this->nullableYear($artwork['pool_year'] ?? null),
            'manual_include_ids' => $this->positiveIntList($artwork['manual_include_ids'] ?? []),
            'show_details' => (bool) ($artwork['show_details'] ?? true),
            'show_gallery_link' => (bool) ($artwork['show_gallery_link'] ?? true),
        ];
    }

    /** @param array<string, mixed> $input */
    public function updateArtworkSettings(HomePresentationSetting $settings, array $input): bool
    {
        return DB::transaction(function () use ($settings, $input): bool {
            /** @var HomePresentationSetting $fresh */
            $fresh = HomePresentationSetting::query()
                ->whereKey($settings->getKey())
                ->lockForUpdate()
                ->firstOrFail();

            $current = $this->configuration($fresh);
            $groupSource = $this->requestedGroupSource($input, $current['group_source']);
            $displayStrategy = $this->requestedDisplayStrategy($input, $current['display_strategy']);
            $newestBy = $this->enum($input['newest_by'] ?? $current['newest_by'], ['artwork_date', 'added'], 'artwork_date');
            $candidateFilter = $this->enum($input['candidate_filter'] ?? $current['candidate_filter'], ['all', 'year'], 'all');
            $groupSize = $this->validatedGroupSize($input['group_size'] ?? $current['group_size']);
            $specificYear = $this->nullableYear($input['specific_year'] ?? $current['specific_year']);
            $manualIncludeIds = $this->positiveIntList($input['manual_include_ids'] ?? $current['manual_include_ids']);
            $manualGroup = $current['manual_group'];

            if (array_key_exists('manual_group', $input)) {
                $manualGroup = $this->normalizeManualGroup($input['manual_group']);
            } elseif ($groupSource === 'manual' && array_key_exists('hero_artwork_id', $input)) {
                $heroArtworkId = $this->nullablePositiveInt($input['hero_artwork_id']);
                $manualGroup = $heroArtworkId === null
                    ? []
                    : [['artwork_id' => $heroArtworkId, 'weight' => self::WEIGHT_TOTAL]];
            }

            $rotationInterval = $this->requestedRotationInterval($input, $current['rotation_interval']);

            if ($groupSource === 'manual' && $manualGroup === []) {
                throw ValidationException::withMessages([
                    'manual_group' => 'Manual Home groups need at least one artwork.',
                ]);
            }

            if ($groupSource === 'automatic' && $candidateFilter === 'year' && $specificYear === null) {
                throw ValidationException::withMessages([
                    'specific_year' => 'Choose a year for the Specific Year candidate filter.',
                ]);
            }

            $rotationStartedAt = $current['rotation_started_at'];
            $manualSequenceChanged = $this->manualGroupIds($manualGroup) !== $this->manualGroupIds($current['manual_group']);
            $intervalChanged = $rotationInterval !== $current['rotation_interval'];
            $sourceChanged = $groupSource !== $current['group_source'];
            $strategyChanged = $displayStrategy !== $current['display_strategy'];
            $currentSpecificYear = $current['candidate_filter'] === 'year' ? $current['specific_year'] : null;
            $requestedSpecificYear = $candidateFilter === 'year' ? $specificYear : null;
            $automaticGroupDefinitionChanged = $groupSource === 'automatic'
                && ($groupSize !== $current['group_size']
                    || $newestBy !== $current['newest_by']
                    || $candidateFilter !== $current['candidate_filter']
                    || $requestedSpecificYear !== $currentSpecificYear
                    || ! $this->sameIdSet($manualIncludeIds, $current['manual_include_ids']));

            if ($displayStrategy === 'sequential'
                && ($rotationStartedAt === null
                    || $manualSequenceChanged
                    || $intervalChanged
                    || $sourceChanged
                    || $strategyChanged
                    || $automaticGroupDefinitionChanged)) {
                $rotationStartedAt = CarbonImmutable::now('UTC')->toIso8601String();
            }

            $root = $fresh->configuration();
            $artwork = is_array($root[HomeTemplate::Artwork->value] ?? null)
                ? $root[HomeTemplate::Artwork->value]
                : [];

            $artwork['show_details'] = (bool) ($input['show_details'] ?? $current['show_details']);
            $artwork['show_gallery_link'] = (bool) ($input['show_gallery_link'] ?? $current['show_gallery_link']);
            $artwork['group_source'] = $groupSource;
            $artwork['display_strategy'] = $displayStrategy;
            $artwork['manual_group'] = $manualGroup;
            $artwork['rotation_interval'] = $rotationInterval;
            $artwork['rotation_started_at'] = $rotationStartedAt;

            // Keep legacy keys current so older readers and intermediate admin states remain lossless.
            $artwork['hero_mode'] = $groupSource === 'manual'
                ? 'fixed'
                : ($displayStrategy === 'random' ? 'random' : 'automatic');
            $artwork['automatic_selection'] = $displayStrategy === 'random' ? 'random' : 'newest';
            $artwork['fixed_artwork_id'] = $groupSource === 'manual'
                ? ($manualGroup[0]['artwork_id'] ?? null)
                : $current['hero_artwork_id'];
            $artwork['newest_by'] = $newestBy;
            $artwork['group_size'] = $groupSize;
            $artwork['pool_rule'] = $candidateFilter === 'year' ? 'year' : 'newest';
            $artwork['pool_year'] = $candidateFilter === 'year' ? $specificYear : null;
            $artwork['manual_include_ids'] = $manualIncludeIds;

            $root[HomeTemplate::Artwork->value] = $artwork;
            $fresh->setAttribute('template', HomeTemplate::Artwork->value);
            $fresh->setAttribute('configuration', $root);

            if (! $fresh->isDirty(['template', 'configuration'])) {
                return false;
            }

            $fresh->save();
            $actor = $this->audit->requireActor();
            $this->audit->record(
                $actor,
                'site_section.updated',
                'site_section',
                (int) $fresh->getAttribute('site_section_id'),
            );

            return true;
        });
    }

    public function addManualMember(HomePresentationSetting $settings, int $artworkId): bool
    {
        if (! $this->artworks->homeCandidateById($artworkId)) {
            throw ValidationException::withMessages([
                'manual_group' => 'Choose an eligible published artwork from an enabled Home source Gallery.',
            ]);
        }

        $current = $this->configuration($settings->fresh());
        $group = $current['manual_group'];
        if (in_array($artworkId, $this->manualGroupIds($group), true)) {
            throw ValidationException::withMessages([
                'manual_group' => 'That artwork is already in the Manual Home group.',
            ]);
        }

        if ($group === []) {
            $group[] = ['artwork_id' => $artworkId, 'weight' => self::WEIGHT_TOTAL];
        } else {
            $newWeight = intdiv(self::WEIGHT_TOTAL, count($group) + 1);
            $existingWeights = array_map(static fn (array $member): int => $member['weight'], $group);
            $scaled = $this->allocateBudget($existingWeights, self::WEIGHT_TOTAL - $newWeight);
            foreach ($group as $index => $member) {
                $group[$index]['weight'] = $scaled[$index];
            }
            $group[] = ['artwork_id' => $artworkId, 'weight' => $newWeight];
        }

        return $this->updateArtworkSettings($settings, ['manual_group' => $group]);
    }

    public function removeManualMember(HomePresentationSetting $settings, int $artworkId): bool
    {
        $current = $this->configuration($settings->fresh());
        $group = $current['manual_group'];
        $index = array_search($artworkId, $this->manualGroupIds($group), true);
        if ($index === false) {
            throw ValidationException::withMessages(['manual_group' => 'That artwork is not in the Manual Home group.']);
        }
        if ($current['group_source'] === 'manual' && count($group) === 1) {
            throw ValidationException::withMessages(['manual_group' => 'The active Manual Home group cannot be empty.']);
        }

        array_splice($group, (int) $index, 1);
        if ($group !== []) {
            $group = $this->normalizeManualGroup($group);
        }

        return $this->updateArtworkSettings($settings, ['manual_group' => $group]);
    }

    /** @param list<int> $artworkIds */
    public function reorderManualGroup(HomePresentationSetting $settings, array $artworkIds): bool
    {
        $current = $this->configuration($settings->fresh());
        $group = $current['manual_group'];
        $expected = $this->manualGroupIds($group);
        $actual = array_values(array_map('intval', $artworkIds));
        if (count($actual) !== count(array_unique($actual))) {
            throw ValidationException::withMessages(['manual_group' => 'Manual Home group order contains duplicates.']);
        }

        $sortedExpected = $expected;
        $sortedActual = $actual;
        sort($sortedExpected);
        sort($sortedActual);
        if ($sortedExpected !== $sortedActual) {
            throw ValidationException::withMessages(['manual_group' => 'Manual Home group order must contain the same artworks.']);
        }

        $byId = [];
        foreach ($group as $member) {
            $byId[$member['artwork_id']] = $member;
        }
        $reordered = array_map(static fn (int $id): array => $byId[$id], $actual);

        return $this->updateArtworkSettings($settings, ['manual_group' => $reordered]);
    }

    public function updateManualMemberWeight(HomePresentationSetting $settings, int $artworkId, int $basisPoints): bool
    {
        if ($basisPoints < 0 || $basisPoints > self::WEIGHT_TOTAL) {
            throw ValidationException::withMessages([
                'weight' => 'Manual Home percentage must be between 0 and 100 percent.',
            ]);
        }

        $current = $this->configuration($settings->fresh());
        $group = $current['manual_group'];
        $target = array_search($artworkId, $this->manualGroupIds($group), true);
        if ($target === false) {
            throw ValidationException::withMessages(['manual_group' => 'That artwork is not in the Manual Home group.']);
        }
        if (count($group) === 1) {
            $group[0]['weight'] = self::WEIGHT_TOTAL;

            return $this->updateArtworkSettings($settings, ['manual_group' => $group]);
        }

        $otherWeights = [];
        $otherIndices = [];
        foreach ($group as $index => $member) {
            if ($index === $target) {
                continue;
            }
            $otherIndices[] = $index;
            $otherWeights[] = $member['weight'];
        }
        $distributed = $this->allocateBudget($otherWeights, self::WEIGHT_TOTAL - $basisPoints);
        foreach ($otherIndices as $offset => $index) {
            $group[$index]['weight'] = $distributed[$offset];
        }
        $group[(int) $target]['weight'] = $basisPoints;

        return $this->updateArtworkSettings($settings, ['manual_group' => $group]);
    }

    /**
     * @param mixed $value
     * @return list<array{artwork_id:int,weight:int}>
     */
    public function normalizeManualGroup(mixed $value): array
    {
        if (! is_array($value) || ! array_is_list($value)) {
            throw ValidationException::withMessages(['manual_group' => 'Manual Home group must be an ordered list.']);
        }
        if ($value === []) {
            return [];
        }

        $members = [];
        $seen = [];
        foreach ($value as $member) {
            if (! is_array($member)) {
                throw ValidationException::withMessages(['manual_group' => 'Manual Home group members must be structured values.']);
            }
            $artworkId = $this->nullablePositiveInt($member['artwork_id'] ?? null);
            $weight = filter_var($member['weight'] ?? null, FILTER_VALIDATE_INT);
            if ($artworkId === null || $weight === false || $weight < 0 || $weight > self::WEIGHT_TOTAL) {
                throw ValidationException::withMessages(['manual_group' => 'Manual Home group members need a valid artwork and percentage.']);
            }
            if (isset($seen[$artworkId])) {
                throw ValidationException::withMessages(['manual_group' => 'Manual Home group cannot contain duplicate artworks.']);
            }
            $seen[$artworkId] = true;
            $members[] = ['artwork_id' => $artworkId, 'weight' => (int) $weight];
        }

        $weights = $this->allocateBudget(
            array_map(static fn (array $member): int => $member['weight'], $members),
            self::WEIGHT_TOTAL,
        );
        foreach ($members as $index => $member) {
            $members[$index]['weight'] = $weights[$index];
        }

        return $members;
    }

    /** @param list<array{artwork_id:int,weight:int}> $group
     *  @return list<int>
     */
    private function manualGroupIds(array $group): array
    {
        return array_values(array_map(static fn (array $member): int => $member['artwork_id'], $group));
    }

    /** @param list<int> $left
     *  @param list<int> $right
     */
    private function sameIdSet(array $left, array $right): bool
    {
        sort($left);
        sort($right);

        return $left === $right;
    }

    /** @return list<array{artwork_id:int,weight:int}> */
    private function readManualGroup(mixed $value): array
    {
        if (! is_array($value) || ! array_is_list($value) || $value === []) {
            return [];
        }

        $members = [];
        $seen = [];
        foreach ($value as $member) {
            if (! is_array($member)) {
                continue;
            }
            $artworkId = $this->nullablePositiveInt($member['artwork_id'] ?? null);
            $weight = filter_var($member['weight'] ?? null, FILTER_VALIDATE_INT);
            if ($artworkId === null || $weight === false || $weight < 0 || $weight > self::WEIGHT_TOTAL || isset($seen[$artworkId])) {
                continue;
            }
            $seen[$artworkId] = true;
            $members[] = ['artwork_id' => $artworkId, 'weight' => (int) $weight];
        }

        if ($members === []) {
            return [];
        }

        $weights = $this->allocateBudget(
            array_map(static fn (array $member): int => $member['weight'], $members),
            self::WEIGHT_TOTAL,
        );
        foreach ($members as $index => $member) {
            $members[$index]['weight'] = $weights[$index];
        }

        return $members;
    }

    /** @param list<int> $weights
     *  @return list<int>
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
        if (array_key_exists('group_source', $input)) {
            return $this->enum($input['group_source'], ['automatic', 'manual'], $current);
        }
        if (array_key_exists('mode', $input)) {
            return $this->enum($input['mode'], ['automatic', 'manual'], $current);
        }

        return $current;
    }

    /** @param array<string, mixed> $input */
    private function requestedDisplayStrategy(array $input, string $current): string
    {
        if (array_key_exists('display_strategy', $input)) {
            return $this->enum($input['display_strategy'], ['ordered', 'random', 'sequential'], $current);
        }
        if (array_key_exists('selection', $input)) {
            return $this->enum($input['selection'], ['newest', 'random'], 'newest') === 'random'
                ? 'random'
                : 'ordered';
        }

        return $current;
    }

    /** @param array<string, mixed> $input
     *  @param array{count:int,unit:string} $current
     *  @return array{count:int,unit:string}
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
        $unit = $this->enumOrNull($value['unit'] ?? null, ['days', 'weeks']);
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
        $unit = $this->enumOrNull($value['unit'] ?? null, ['days', 'weeks']);
        if ($count === false || $count < 1 || $unit === null) {
            throw ValidationException::withMessages(['rotation_interval' => 'Sequential rotation interval needs a positive count in days or weeks.']);
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

    private function nullableYear(mixed $value): ?int
    {
        $year = $this->nullablePositiveInt($value);

        return $year !== null && $year >= 1000 && $year <= 3000 ? $year : null;
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
}
