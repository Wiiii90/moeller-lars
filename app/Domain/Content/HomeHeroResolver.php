<?php

namespace App\Domain\Content;

use App\Domain\Artwork\PublicArtworkQuery;
use App\Models\Artwork;
use App\Models\MediaAsset;
use App\Models\HomePresentationSetting;
use Carbon\CarbonImmutable;
use DateTimeInterface;
use Illuminate\Support\Collection;
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
     *     group:Collection<int,HomeHeroCandidate>,
     *     current:?HomeHeroCandidate,
     *     sequence:Collection<int,HomeHeroCandidate>,
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
     * @return array{0:Collection<int,HomeHeroCandidate>,1:array<string,int>}
     */
    private function manualGroup(array $configuration): array
    {
        $members = $configuration['manual_group'];
        $eligible = $this->selectedCandidates($members);
        $effective = array_values(array_filter(
            $members,
            static fn (array $member): bool => $eligible->contains(
                static fn (HomeHeroCandidate $candidate): bool => $candidate->key() === HomeHeroConfigurationService::memberKey($member),
            ),
        ));
        $weights = [];
        if ($effective !== []) {
            foreach ($this->configuration->normalizeManualGroup($effective) as $member) {
                $weights[HomeHeroConfigurationService::memberKey($member)] = $member['weight'];
            }
        }

        return [$eligible, $weights];
    }

    /** Read the one stored Manual group, independent of the active source mode.
     * @return Collection<int,HomeHeroCandidate>
     */
    public function manualCandidates(HomePresentationSetting $settings): Collection
    {
        return $this->selectedCandidates($this->configuration->configuration($settings)['manual_group']);
    }

    /** @param array<string,mixed> $configuration
     * @return array{0:Collection<int,HomeHeroCandidate>,1:array<string,int>}
     */
    private function automaticGroup(array $configuration): array
    {
        $semi = $configuration['group_source'] === 'semi_automatic';
        $manual = $semi && $configuration['manual_group_enabled']
            ? $this->selectedCandidates($configuration['manual_group'])->take($configuration['group_size'])->values()
            : new Collection;

        $remaining = max(0, $configuration['group_size'] - $manual->count());
        $group = $manual;
        if ($remaining > 0) {
            $excluded = $semi ? $configuration['disabled_auto_artwork_ids'] : [];
            // An explicitly selected Artwork cannot appear twice in the same
            // mixed group, even when its Gallery is enabled for automatic use.
            foreach ($manual as $candidate) {
                if ($candidate->artwork() instanceof Artwork) {
                    $excluded[] = (int) $candidate->record->getKey();
                }
            }
            $automatic = $this->artworks->configuredHomeCandidates(
                $remaining,
                $configuration['source_rule'],
                array_values(array_unique($excluded)),
            )->map(static fn (Artwork $artwork): HomeHeroCandidate => HomeHeroCandidate::fromArtwork($artwork))
                ->values();
            $group = $manual->concat($automatic)->values();
        }

        $weights = [];
        if ($group->isNotEmpty()) {
            $base = intdiv(HomeHeroConfigurationService::WEIGHT_TOTAL, $group->count());
            $remainder = HomeHeroConfigurationService::WEIGHT_TOTAL - ($base * $group->count());
            foreach ($group->values() as $index => $candidate) {
                $weights[$candidate->key()] = $base + ($index < $remainder ? 1 : 0);
            }
        }

        return [$group, $weights];
    }

    /**
     * Bulk-load selected identities. Only canonical Artwork eligibility and
     * available Storage media with ALT/preview enter the public group.
     *
     * @param list<array<string,int>> $members
     * @return Collection<int,HomeHeroCandidate>
     */
    private function selectedCandidates(array $members): Collection
    {
        $artworkIds = [];
        $mediaIds = [];
        foreach ($members as $member) {
            if (isset($member['artwork_id'])) {
                $artworkIds[] = $member['artwork_id'];
            } elseif (isset($member['media_asset_id'])) {
                $mediaIds[] = $member['media_asset_id'];
            }
        }

        $artworks = $this->artworks->homeCandidatesByIds($artworkIds)->keyBy('id');
        $media = MediaAsset::query()->whereIn('id', $mediaIds)->where('state', 'available')
            ->with(['variants', 'artworks.category'])->get()->keyBy('id');
        $candidates = new Collection;
        foreach ($members as $member) {
            if (isset($member['artwork_id'])) {
                $artwork = $artworks->get($member['artwork_id']);
                if ($artwork instanceof Artwork) {
                    $candidates->push(HomeHeroCandidate::fromArtwork($artwork));
                }
            } else {
                $asset = $media->get($member['media_asset_id']);
                if ($asset instanceof MediaAsset && ($candidate = HomeHeroCandidate::fromMedia($asset)) !== null) {
                    $candidates->push($candidate);
                }
            }
        }

        return $candidates;
    }

    /** @param array<string,mixed> $configuration
     * @param  Collection<int,HomeHeroCandidate>  $group
     * @param  array<string,int>  $weights
     * @return array<string,mixed>
     */
    private function randomResolution(
        array $configuration,
        Collection $group,
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
            $cumulative += $weights[$artwork->key()] ?? 0;
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
     * @param  Collection<int,HomeHeroCandidate>  $group
     * @param  array<string,int>  $weights
     * @return array<string,mixed>
     */
    private function sequentialResolution(
        HomePresentationSetting $settings,
        array $configuration,
        Collection $group,
        array $weights,
        ?DateTimeInterface $at,
    ): array {
        $now = $at === null
            ? CarbonImmutable::now('UTC')
            : CarbonImmutable::instance($at)->utc();
        $anchor = $this->rotationAnchor($settings, $configuration, $now);
        $interval = $configuration['rotation_interval'];
        if ($interval['unit'] === 'months') {
            // Every boundary is calculated from the original anchor, never the
            // previous clipped month (Jan 31 -> Feb 28 -> Mar 31).
            $months = max(0, ($now->year - $anchor->year) * 12 + $now->month - $anchor->month);
            if ($months > 0 && $anchor->addMonthsNoOverflow($months)->greaterThan($now)) {
                $months--;
            }
            $elapsedIntervals = $now->lessThan($anchor) ? 0 : intdiv($months, $interval['count']);
            $nextRotationAt = $now->lessThan($anchor)
                ? $anchor
                : $anchor->addMonthsNoOverflow(($elapsedIntervals + 1) * $interval['count']);
        } else {
            $secondsPerUnit = $interval['unit'] === 'weeks' ? 604800 : 86400;
            $intervalSeconds = $interval['count'] * $secondsPerUnit;
            $elapsedSeconds = max(0, $now->getTimestamp() - $anchor->getTimestamp());
            $elapsedIntervals = intdiv($elapsedSeconds, $intervalSeconds);
            $nextRotationAt = $now->lessThan($anchor)
                ? $anchor
                : $anchor->addSeconds(($elapsedIntervals + 1) * $intervalSeconds);
        }

        $slot = $elapsedIntervals % $group->count();
        $sequence = $group->slice($slot)->concat($group->take($slot))->values();

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
