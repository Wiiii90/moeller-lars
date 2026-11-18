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
        [$group, $weights] = $this->sourceGroup($configuration);

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
    private function sourceGroup(array $configuration): array
    {
        $mode = $configuration['group_source'];
        $perGallery = $configuration['selection_mode'] === 'per_gallery';
        $galleryFirst = str_starts_with($configuration['source_rule'], 'gallery_');
        $ranking = $galleryFirst ? substr($configuration['source_rule'], 8) : $configuration['source_rule'];
        $amount = $configuration['group_size'];
        $pools = [];
        $names = [];

        $dummyGalleries = $mode === 'automatic' ? [] : array_values(array_filter(
            $configuration['dummy_galleries'], static fn (array $gallery): bool => $gallery['enabled'],
        ));

        $reservedArtworkIds = $configuration['disabled_auto_artwork_ids'];
        foreach ($dummyGalleries as $dummy) {
            foreach ($dummy['members'] as $member) {
                if (isset($member['artwork_id'])) {
                    $reservedArtworkIds[] = (int) $member['artwork_id'];
                }
            }
        }

        if ($mode !== 'manual') {
            $regular = ($perGallery || $galleryFirst)
                ? $this->artworks->homeGalleryRepresentatives(
                    $ranking, array_values(array_unique($reservedArtworkIds)),
                    12500, $perGallery ? $amount : HomeHeroConfigurationService::MAX_GROUP_SIZE,
                )
                : $this->artworks->configuredHomeCandidates(
                    HomeHeroConfigurationService::MAX_GROUP_SIZE, $ranking,
                    array_values(array_unique($reservedArtworkIds)),
                );
            foreach ($regular as $artwork) {
                $sourceId = (int) $artwork->getAttribute('artwork_category_id');
                $pools[$sourceId] ??= new Collection;
                $pools[$sourceId]->push(HomeHeroCandidate::fromArtwork($artwork));
                $names[$sourceId] = (string) ($artwork->getRelationValue('category')?->getAttribute('name') ?? '');
            }
        }

        foreach ($dummyGalleries as $dummy) {
            $sourceId = (int) $dummy['id'];
            $pools[$sourceId] = $this->rankCandidates(
                $this->selectedCandidates(
                    array_values(array_filter(
                        $dummy['members'], static fn (array $member): bool => $member['enabled'],
                    )),
                    $sourceId,
                    (string) $dummy['name'],
                ),
                $ranking,
            );
            $names[$sourceId] = (string) $dummy['name'];
        }

        // A source's own rank always comes from Ranking. The source-order
        // variant changes only which entire Gallery is read first.
        foreach ($pools as $sourceId => $candidates) {
            $pools[$sourceId] = $this->rankCandidates($candidates, $ranking)
                ->take($perGallery ? $amount : HomeHeroConfigurationService::MAX_GROUP_SIZE)
                ->values();
        }
        $ids = array_keys($pools);
        usort($ids, static fn (int $a, int $b): int => strcmp(
            mb_strtolower($names[$a] ?? ''), mb_strtolower($names[$b] ?? ''),
        ) ?: ($a <=> $b));
        $saved = array_flip($configuration['source_order']);
        usort($ids, static fn (int $a, int $b): int =>
            (($saved[$a] ?? PHP_INT_MAX) <=> ($saved[$b] ?? PHP_INT_MAX))
            ?: strcmp(mb_strtolower($names[$a] ?? ''), mb_strtolower($names[$b] ?? ''))
            ?: ($a <=> $b));

        $candidates = new Collection;
        $seen = [];
        foreach ($ids as $sourceId) {
            foreach ($pools[$sourceId] as $candidate) {
                $key = $candidate->key();
                if (isset($seen[$key])) {
                    continue;
                }
                $seen[$key] = true;
                $candidates->push($candidate);
            }
        }
        $group = ($galleryFirst ? $candidates : $this->rankCandidates($candidates, $ranking))
            ->take($perGallery ? HomeHeroConfigurationService::MAX_GROUP_SIZE : $amount)
            ->values();

        return [$group, $this->configuration->sourcedGroupWeights(
            $group, $configuration['sourced_weights'],
        )];
    }

    /** Read all stored Dummy members for the Home editor, including inactive ones. */
    public function dummyCandidates(array $members, int $sourceId, string $sourceName): Collection
    {
        return $this->selectedCandidates($members, $sourceId, $sourceName);
    }

    /**
     * Sort candidates across all sources by one canonical Ranking rule. Direct
     * Storage references without an Artwork date sort after dated Artworks
     * under artwork-date ranking, never under a fabricated date.
     *
     * @param Collection<int,HomeHeroCandidate> $candidates
     * @return Collection<int,HomeHeroCandidate>
     */
    private function rankCandidates(Collection $candidates, string $rule): Collection
    {
        $oldest = str_starts_with($rule, 'oldest_');
        return $candidates->sort(function (HomeHeroCandidate $left, HomeHeroCandidate $right) use ($rule, $oldest): int {
            $a = $this->candidateRankingParts($left, $rule);
            $b = $this->candidateRankingParts($right, $rule);
            foreach ($a as $index => $value) {
                $other = $b[$index];
                if ($value === null || $other === null) {
                    $comparison = $value === $other ? 0 : ($value === null ? 1 : -1);
                } else {
                    $comparison = $oldest ? ($value <=> $other) : ($other <=> $value);
                }
                if ($comparison !== 0) {
                    return $comparison;
                }
            }
            return strcmp($left->key(), $right->key());
        })->values();
    }

    /** @return list<int|null> */
    private function candidateRankingParts(HomeHeroCandidate $candidate, string $rule): array
    {
        $artwork = $candidate->artwork();
        $record = $artwork ?? $candidate->mediaAsset();
        $id = (int) $record?->getKey();

        if (in_array($rule, ['newest_media_added', 'oldest_media_added'], true)) {
            $primary = $artwork?->getRelationValue('artworkMedia')
                ?->filter(static fn ($link): bool => $link->getAttribute('role') === 'primary')
                ->sortBy([['position', 'asc'], ['id', 'asc']])
                ->first();
            $media = $artwork ? $primary?->getRelationValue('mediaAsset') : $candidate->mediaAsset();
            return [$this->rankTimestamp($media?->getAttribute('created_at')), $id];
        }

        return [
            $artwork?->getAttribute('year') === null ? $candidate->year : (int) $artwork->getAttribute('year'),
            $artwork?->getAttribute('month') === null ? $candidate->month : (int) $artwork->getAttribute('month'),
            $artwork?->getAttribute('day') === null ? $candidate->day : (int) $artwork->getAttribute('day'),
            $this->rankTimestamp($artwork?->getAttribute('created_at')),
            $id,
        ];
    }

    private function rankTimestamp(mixed $value): ?int
    {
        return $value === null ? null : (int) CarbonImmutable::parse($value)->utc()->format('Uu');
    }

    /**
     * Bulk-load selected identities. Only canonical Artwork eligibility and
     * available Storage media with ALT/preview enter the public group.
     *
     * @param list<array<string,int>> $members
     * @return Collection<int,HomeHeroCandidate>
     */
    private function selectedCandidates(array $members, ?int $sourceId = null, ?string $sourceName = null): Collection
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
                    $candidates->push(HomeHeroCandidate::fromArtwork($artwork, $sourceId, $sourceName));
                }
            } else {
                $asset = $media->get($member['media_asset_id']);
                if ($asset instanceof MediaAsset && ($candidate = HomeHeroCandidate::fromMedia(
                    $asset, $sourceId, $sourceName,
                    is_string($member['title'] ?? null) ? $member['title'] : null,
                    isset($member['year']) ? (int) $member['year'] : null,
                    isset($member['month']) ? (int) $member['month'] : null,
                    isset($member['day']) ? (int) $member['day'] : null,
                )) !== null) {
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
