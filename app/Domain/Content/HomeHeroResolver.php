<?php

namespace App\Domain\Content;

use App\Domain\Artwork\PublicArtworkQuery;
use App\Models\Artwork;
use App\Models\HomePresentationSetting;
use Carbon\CarbonImmutable;
use DateTimeInterface;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Validation\ValidationException;

final class HomeHeroResolver
{
    public function __construct(
        private readonly PublicArtworkQuery $artworks,
        private readonly HomeHeroConfigurationService $configuration,
    ) {}

    /**
     * @return array{
     *     configuration:array<string,mixed>,
     *     group:EloquentCollection<int,Artwork>,
     *     current:?Artwork,
     *     sequence:EloquentCollection<int,Artwork>,
     *     weights:array<int,int>,
     *     rotation_slot:int,
     *     next_rotation_at:?CarbonImmutable
     * }
     */
    public function resolve(
        HomePresentationSetting $settings,
        ?DateTimeInterface $at = null,
        ?int $randomBasisPoint = null,
    ): array {
        $configuration = $this->configuration->configuration($settings);
        [$group, $weights] = $configuration['group_source'] === 'manual'
            ? $this->manualGroup($configuration)
            : $this->automaticGroup($configuration);

        if ($group->isEmpty()) {
            return [
                'configuration' => $configuration,
                'group' => $group,
                'current' => null,
                'sequence' => $group,
                'weights' => $weights,
                'rotation_slot' => 0,
                'next_rotation_at' => null,
            ];
        }

        return match ($configuration['display_strategy']) {
            'random' => $this->randomResolution($configuration, $group, $weights, $randomBasisPoint),
            'sequential' => $this->sequentialResolution($settings, $configuration, $group, $weights, $at),
            default => [
                'configuration' => $configuration,
                'group' => $group,
                'current' => $group->first(),
                'sequence' => $group,
                'weights' => $weights,
                'rotation_slot' => 0,
                'next_rotation_at' => null,
            ],
        };
    }

    /** @param array<string,mixed> $configuration
     *  @return array{0:EloquentCollection<int,Artwork>,1:array<int,int>}
     */
    private function manualGroup(array $configuration): array
    {
        $stored = $configuration['manual_group'];
        $ids = array_map(static fn (array $member): int => $member['artwork_id'], $stored);
        $eligible = $this->artworks->homeCandidatesByIds($ids)
            ->keyBy(fn (Artwork $artwork): int => (int) $artwork->getKey());

        $effectiveMembers = [];
        $group = new EloquentCollection;
        foreach ($stored as $member) {
            $artwork = $eligible->get($member['artwork_id']);
            if (! $artwork instanceof Artwork) {
                continue;
            }
            $group->push($artwork);
            $effectiveMembers[] = $member;
        }

        if ($effectiveMembers === []) {
            return [$group, []];
        }

        $effectiveMembers = $this->configuration->normalizeManualGroup($effectiveMembers);
        $weights = [];
        foreach ($effectiveMembers as $member) {
            $weights[$member['artwork_id']] = $member['weight'];
        }

        return [$group, $weights];
    }

    /** @param array<string,mixed> $configuration
     *  @return array{0:EloquentCollection<int,Artwork>,1:array<int,int>}
     */
    private function automaticGroup(array $configuration): array
    {
        $group = $this->artworks->configuredHomeCandidates(
            $configuration['group_size'],
            $configuration['newest_by'],
            $configuration['candidate_filter'],
            $configuration['candidate_filter'] === 'year' ? $configuration['specific_year'] : null,
            $configuration['manual_include_ids'],
        );

        $weights = [];
        if ($group->isNotEmpty()) {
            $base = intdiv(HomeHeroConfigurationService::WEIGHT_TOTAL, $group->count());
            $remainder = HomeHeroConfigurationService::WEIGHT_TOTAL - ($base * $group->count());
            foreach ($group->values() as $index => $artwork) {
                $weights[(int) $artwork->getKey()] = $base + ($index < $remainder ? 1 : 0);
            }
        }

        return [$group, $weights];
    }

    /** @param array<string,mixed> $configuration
     *  @param EloquentCollection<int,Artwork> $group
     *  @param array<int,int> $weights
     *  @return array<string,mixed>
     */
    private function randomResolution(
        array $configuration,
        EloquentCollection $group,
        array $weights,
        ?int $randomBasisPoint,
    ): array {
        $basisPoint = $randomBasisPoint ?? random_int(0, HomeHeroConfigurationService::WEIGHT_TOTAL - 1);
        if ($basisPoint < 0 || $basisPoint >= HomeHeroConfigurationService::WEIGHT_TOTAL) {
            throw ValidationException::withMessages(['random' => 'Random Home sample must be within the configured weight range.']);
        }

        $current = null;
        $cumulative = 0;
        foreach ($group as $artwork) {
            $cumulative += $weights[(int) $artwork->getKey()] ?? 0;
            if ($basisPoint < $cumulative) {
                $current = $artwork;
                break;
            }
        }
        $current ??= $group->last();

        return [
            'configuration' => $configuration,
            'group' => $group,
            'current' => $current,
            'sequence' => $group,
            'weights' => $weights,
            'rotation_slot' => 0,
            'next_rotation_at' => null,
        ];
    }

    /** @param array<string,mixed> $configuration
     *  @param EloquentCollection<int,Artwork> $group
     *  @param array<int,int> $weights
     *  @return array<string,mixed>
     */
    private function sequentialResolution(
        HomePresentationSetting $settings,
        array $configuration,
        EloquentCollection $group,
        array $weights,
        ?DateTimeInterface $at,
    ): array {
        $now = $at === null
            ? CarbonImmutable::now('UTC')
            : CarbonImmutable::instance($at)->utc();
        $anchor = $this->rotationAnchor($settings, $configuration, $now);
        $interval = $configuration['rotation_interval'];
        $secondsPerUnit = $interval['unit'] === 'weeks' ? 604800 : 86400;
        $intervalSeconds = $interval['count'] * $secondsPerUnit;

        $elapsedSeconds = max(0, $now->getTimestamp() - $anchor->getTimestamp());
        $elapsedIntervals = intdiv($elapsedSeconds, $intervalSeconds);
        $slot = $elapsedIntervals % $group->count();
        $sequence = $group->slice($slot)->concat($group->take($slot))->values();
        $nextRotationAt = $now->lessThan($anchor)
            ? $anchor
            : $anchor->addSeconds(($elapsedIntervals + 1) * $intervalSeconds);

        return [
            'configuration' => $configuration,
            'group' => $group,
            'current' => $sequence->first(),
            'sequence' => $sequence,
            'weights' => $weights,
            'rotation_slot' => $slot,
            'next_rotation_at' => $nextRotationAt,
        ];
    }

    /** @param array<string,mixed> $configuration */
    private function rotationAnchor(
        HomePresentationSetting $settings,
        array $configuration,
        CarbonImmutable $fallback,
    ): CarbonImmutable {
        $stored = $configuration['rotation_started_at'];
        if (is_string($stored) && $stored !== '') {
            return CarbonImmutable::parse($stored)->utc();
        }

        $updatedAt = $settings->getAttribute('updated_at');
        if ($updatedAt instanceof DateTimeInterface) {
            return CarbonImmutable::instance($updatedAt)->utc();
        }
        $createdAt = $settings->getAttribute('created_at');
        if ($createdAt instanceof DateTimeInterface) {
            return CarbonImmutable::instance($createdAt)->utc();
        }

        return $fallback;
    }
}
