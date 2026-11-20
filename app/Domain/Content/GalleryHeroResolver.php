<?php

namespace App\Domain\Content;

use App\Domain\Artwork\PublicArtworkQuery;
use App\Models\Artwork;
use App\Models\MediaAsset;
use App\Models\GalleryHeroSetting;
use Carbon\CarbonImmutable;
use DateTimeInterface;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;

final class GalleryHeroResolver
{
    public function __construct(
        private readonly PublicArtworkQuery $artworks,
        private readonly GalleryHeroConfigurationService $configuration,
    ) {}

    /**
     * @return array{
     *     configuration:array<string,mixed>,
     *     group:Collection<int,GalleryHeroCandidate>,
     *     current:?GalleryHeroCandidate,
     *     sequence:Collection<int,GalleryHeroCandidate>,
     *     weights:array<string,int>,
     *     rotation_slot:int,
     *     next_rotation_at:?CarbonImmutable
     * }
     */
    public function resolve(
        GalleryHeroSetting $settings,
        ?DateTimeInterface $at = null,
        ?int $randomBasisPoint = null,
        ?string $previousVisualKey = null,
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
            'random' => $this->randomResolution($configuration, $group, $weights, $randomBasisPoint, $previousVisualKey),
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
     * @return array{0:Collection<int,GalleryHeroCandidate>,1:array<string,int>}
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
                ? $this->artworks->heroGalleryRepresentatives(
                    $configuration['enabled_gallery_ids'], $ranking, array_values(array_unique($reservedArtworkIds)),
                    12500, $perGallery ? $amount : GalleryHeroConfigurationService::MAX_GROUP_SIZE,
                )
                : $this->artworks->configuredHeroCandidates(
                    $configuration['enabled_gallery_ids'], GalleryHeroConfigurationService::MAX_GROUP_SIZE, $ranking,
                    array_values(array_unique($reservedArtworkIds)),
                );
            foreach ($regular as $artwork) {
                $sourceId = (int) $artwork->getAttribute('artwork_category_id');
                $pools[$sourceId] ??= new Collection;
                $pools[$sourceId]->push(GalleryHeroCandidate::fromArtwork($artwork));
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
                ->take($perGallery ? $amount : GalleryHeroConfigurationService::MAX_GROUP_SIZE)
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
            ->take($perGallery ? GalleryHeroConfigurationService::MAX_GROUP_SIZE : $amount)
            ->values();

        return [$group, $this->configuration->sourcedGroupWeights(
            $group, $configuration['sourced_weights'],
        )];
    }

    /** Read all stored Dummy members for the Hero editor, including inactive ones. */
    public function dummyCandidates(array $members, int $sourceId, string $sourceName, bool $editorial = false): Collection
    {
        return $this->selectedCandidates($members, $sourceId, $sourceName, $editorial);
    }

    /**
     * Sort candidates across all sources by one canonical Ranking rule. Direct
     * Storage references without an Artwork date sort after dated Artworks
     * under artwork-date ranking, never under a fabricated date.
     *
     * @param Collection<int,GalleryHeroCandidate> $candidates
     * @return Collection<int,GalleryHeroCandidate>
     */
    private function rankCandidates(Collection $candidates, string $rule): Collection
    {
        $oldest = str_starts_with($rule, 'oldest_');
        return $candidates->sort(function (GalleryHeroCandidate $left, GalleryHeroCandidate $right) use ($rule, $oldest): int {
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
    private function candidateRankingParts(GalleryHeroCandidate $candidate, string $rule): array
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
     * @return Collection<int,GalleryHeroCandidate>
     */
    private function selectedCandidates(array $members, ?int $sourceId = null, ?string $sourceName = null, bool $editorial = false): Collection
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

        $artworks = ($editorial
            ? $this->artworks->editorialHeroCandidatesByIds($artworkIds)
            : $this->artworks->heroCandidatesByIds($artworkIds))->keyBy('id');
        $media = MediaAsset::query()->whereIn('id', $mediaIds)->where('state', 'available')
            ->with(['variants', 'artworks.category'])->get()->keyBy('id');
        $candidates = new Collection;
        foreach ($members as $member) {
            if (isset($member['artwork_id'])) {
                $artwork = $artworks->get($member['artwork_id']);
                if ($artwork instanceof Artwork) {
                    $candidates->push(GalleryHeroCandidate::fromArtwork($artwork, $sourceId, $sourceName));
                }
            } else {
                $asset = $media->get($member['media_asset_id']);
                if ($asset instanceof MediaAsset && ($candidate = GalleryHeroCandidate::fromMedia(
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

    /**
     * A drawable alternative is a distinct visual asset with a positive
     * configured chance, not merely another Artwork referring to that asset.
     *
     * @param Collection<int,GalleryHeroCandidate> $group
     * @param array<string,int> $weights
     */
    public function hasRandomAlternatives(Collection $group, array $weights): bool
    {
        $visuals = [];
        foreach ($group as $candidate) {
            if (($weights[$candidate->key()] ?? 0) <= 0) {
                continue;
            }
            $visuals[$candidate->visualKey()] = true;
            if (count($visuals) > 1) {
                return true;
            }
        }

        return false;
    }

    /**
     * Form choices use the same sourced membership as the public resolver.
     * Uncommitted source/amount edits are evaluated without persisting them.
     *
     * @param array<string,mixed> $draft
     */
    public function canRandomize(GalleryHeroSetting $settings, array $draft = []): bool
    {
        $mode = $draft['group_source'] ?? null;
        $configuration = $this->configuration->configuration(
            $settings,
            is_string($mode) && in_array($mode, ['automatic', 'semi_automatic', 'manual'], true)
                ? $mode : null,
        );
        $selection = $draft['selection_mode'] ?? null;
        if (is_string($selection) && array_key_exists($selection, GalleryHeroConfigurationService::SELECTION_MODES)) {
            $configuration['selection_mode'] = $selection;
        }
        $rule = $draft['source_rule'] ?? null;
        if (is_string($rule) && array_key_exists($rule, GalleryHeroConfigurationService::SOURCE_RULES)) {
            $configuration['source_rule'] = $rule;
        }
        $amount = filter_var($draft['group_size'] ?? null, FILTER_VALIDATE_INT);
        if ($amount !== false && $amount >= 1 && $amount <= GalleryHeroConfigurationService::MAX_GROUP_SIZE) {
            $configuration['group_size'] = $amount;
        }

        [$group, $weights] = $this->sourceGroup($configuration);

        return $this->hasRandomAlternatives($group, $weights);
    }

    /**
     * Reweight only this draw; stored basis-point chances remain unchanged.
     * If there is no positively weighted alternative, keep the only possible
     * image rather than falsely allowing a zero-weighted candidate.
     *
     * @param Collection<int,GalleryHeroCandidate> $group
     * @param array<string,int> $weights
     * @return array<string,int>
     */
    public function nextRandomWeights(Collection $group, array $weights, ?string $lastVisualKey): array
    {
        if ($lastVisualKey === null || ! $this->hasRandomAlternatives($group, $weights)) {
            return $weights;
        }

        $adjusted = [];
        $changed = false;
        foreach ($group as $candidate) {
            $weight = $weights[$candidate->key()] ?? 0;
            if ($candidate->visualKey() === $lastVisualKey && $weight > 0) {
                $weight = 0;
                $changed = true;
            }
            $adjusted[$candidate->key()] = $weight;
        }

        return $changed
            ? $this->configuration->sourcedGroupWeights($group, $adjusted)
            : $weights;
    }

    /** @param array<string,mixed> $configuration
     * @param  Collection<int,GalleryHeroCandidate>  $group
     * @param  array<string,int>  $weights
     * @return array<string,mixed>
     */
    private function randomResolution(
        array $configuration,
        Collection $group,
        array $weights,
        ?int $randomBasisPoint,
        ?string $previousVisualKey,
    ): array {
        $drawWeights = $this->nextRandomWeights($group, $weights, $previousVisualKey);
        $basisPoint = $randomBasisPoint ?? random_int(0, GalleryHeroConfigurationService::WEIGHT_TOTAL - 1);
        if ($basisPoint < 0 || $basisPoint >= GalleryHeroConfigurationService::WEIGHT_TOTAL) {
            throw ValidationException::withMessages(['random' => 'Random Hero sample must be within the configured weight range.']);
        }

        $current = null;
        $cumulative = 0;
        foreach ($group as $artwork) {
            $cumulative += $drawWeights[$artwork->key()] ?? 0;
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
     * @param  Collection<int,GalleryHeroCandidate>  $group
     * @param  array<string,int>  $weights
     * @return array<string,mixed>
     */
    private function sequentialResolution(
        GalleryHeroSetting $settings,
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
        GalleryHeroSetting $settings,
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
