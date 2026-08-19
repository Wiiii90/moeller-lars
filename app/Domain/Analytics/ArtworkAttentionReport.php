<?php

namespace App\Domain\Analytics;

use App\Domain\Media\MediaIngestService;
use App\Models\Artwork;
use App\Models\ArtworkCategory;
use App\Models\ArtworkMedia;
use App\Models\MediaAsset;
use App\Models\MediaVariant;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;

final class ArtworkAttentionReport
{
    /**
     * @param  array<int, array<string, mixed>>  $events
     * @param  array<int, array<string, mixed>>  $series
     * @return array<int, array<string, mixed>>
     */
    public function build(array $events, array $series): array
    {
        $keys = collect($events)
            ->pluck('analytics_key')
            ->filter(static fn (mixed $key): bool => is_string($key) && $key !== '')
            ->unique()
            ->values()
            ->all();

        if ($keys === []) {
            return [];
        }

        /** @var Builder<Artwork> $artworkQuery */
        $artworkQuery = Artwork::query();
        /** @var EloquentCollection<int, Artwork> $artworks */
        $artworks = $artworkQuery
            ->with(['category', 'artworkMedia.mediaAsset.variants'])
            ->whereIn('analytics_key', $keys)
            ->get();

        $eventsByKey = collect($events)->groupBy('analytics_key');
        $seriesByKey = collect($series)->groupBy('analytics_key');
        $rows = [];

        foreach ($artworks as $artwork) {
            $analyticsKey = (string) $artwork->getAttribute('analytics_key');
            $metrics = [
                'detail_views' => 0,
                'viewer_opens' => 0,
                'zooms' => 0,
                'previous' => 0,
                'next' => 0,
                'attention_events' => 0,
                'attention_seconds' => 0.0,
            ];

            foreach ($eventsByKey->get($analyticsKey, collect()) as $event) {
                $action = (string) ($event['action'] ?? '');
                $count = (int) round((float) ($event['nb_events'] ?? 0));

                match ($action) {
                    'artwork_detail_view' => $metrics['detail_views'] += $count,
                    'artwork_open' => $metrics['viewer_opens'] += $count,
                    'artwork_zoom_used' => $metrics['zooms'] += $count,
                    'artwork_previous' => $metrics['previous'] += $count,
                    'artwork_next' => $metrics['next'] += $count,
                    'artwork_attention' => $this->addAttention($metrics, $event, $count),
                    default => null,
                };
            }

            $trendRows = $seriesByKey->get($analyticsKey, collect())
                ->values()
                ->all();
            $trend = $this->trend($trendRows);
            $attentionEvents = (int) $metrics['attention_events'];
            $attentionSeconds = (float) $metrics['attention_seconds'];
            /** @var ArtworkCategory|null $category */
            $category = $artwork->getRelationValue('category');
            $isPublic = $artwork->getAttribute('state') === 'published'
                && $category?->getAttribute('state') === 'published';
            $slug = (string) $artwork->getAttribute('slug');

            $rows[] = [
                'id' => (int) $artwork->getKey(),
                'analytics_key' => $analyticsKey,
                'title' => (string) $artwork->getAttribute('title'),
                'slug' => $slug,
                'category' => (string) ($category?->getAttribute('name') ?? 'No Gallery'),
                'state' => (string) $artwork->getAttribute('state'),
                'thumbnail_url' => $this->thumbnailUrl($artwork),
                'public_url' => $isPublic ? route('artworks.show', ['slug' => $slug]) : null,
                'detail_views' => $metrics['detail_views'],
                'viewer_opens' => $metrics['viewer_opens'],
                'zooms' => $metrics['zooms'],
                'navigation' => (int) $metrics['previous'] + (int) $metrics['next'],
                'attention_events' => $attentionEvents,
                'attention_seconds' => $attentionSeconds,
                'attention_label' => $this->formatSeconds($attentionSeconds),
                'average_attention_seconds' => $attentionEvents > 0 ? $attentionSeconds / $attentionEvents : 0.0,
                'average_attention_label' => $attentionEvents > 0 ? $this->formatSeconds($attentionSeconds / $attentionEvents) : '—',
                'trend' => $trend,
            ];
        }

        usort($rows, static function (array $a, array $b): int {
            $attention = ((float) $b['attention_seconds']) <=> ((float) $a['attention_seconds']);
            if ($attention !== 0) {
                return $attention;
            }

            $engagementA = (int) $a['viewer_opens'] + (int) $a['detail_views'] + (int) $a['zooms'];
            $engagementB = (int) $b['viewer_opens'] + (int) $b['detail_views'] + (int) $b['zooms'];

            return $engagementB <=> $engagementA;
        });

        return $rows;
    }

    /** @param array<string, int|float> $metrics
     * @param  array<string, mixed>  $event
     */
    private function addAttention(array &$metrics, array $event, int $fallbackCount): null
    {
        $valueCount = (int) round((float) ($event['nb_events_with_value'] ?? 0));
        $metrics['attention_events'] += $valueCount > 0 ? $valueCount : $fallbackCount;
        $metrics['attention_seconds'] += max(0.0, (float) ($event['sum_event_value'] ?? 0));

        return null;
    }

    /** @param array<int, array<string, mixed>> $series
     * @return array<int, array<string, int|float|string>>
     */
    private function trend(array $series): array
    {
        $days = [];
        foreach ($series as $event) {
            $date = (string) ($event['date'] ?? '');
            if ($date === '') {
                continue;
            }

            $days[$date] ??= [
                'date' => $date,
                'detail_views' => 0,
                'viewer_opens' => 0,
                'zooms' => 0,
                'attention_seconds' => 0.0,
            ];

            $action = (string) ($event['action'] ?? '');
            $count = (int) round((float) ($event['nb_events'] ?? 0));
            match ($action) {
                'artwork_detail_view' => $days[$date]['detail_views'] += $count,
                'artwork_open' => $days[$date]['viewer_opens'] += $count,
                'artwork_zoom_used' => $days[$date]['zooms'] += $count,
                'artwork_attention' => $days[$date]['attention_seconds'] += max(0.0, (float) ($event['sum_event_value'] ?? 0)),
                default => null,
            };
        }

        ksort($days);
        foreach ($days as &$day) {
            $day['attention_label'] = $this->formatSeconds((float) $day['attention_seconds']);
        }
        unset($day);

        return array_values($days);
    }

    private function thumbnailUrl(Artwork $artwork): ?string
    {
        /** @var EloquentCollection<int, ArtworkMedia> $usages */
        $usages = $artwork->getRelation('artworkMedia');
        /** @var ArtworkMedia|null $primary */
        $primary = $usages->firstWhere('role', 'primary');
        /** @var MediaAsset|null $asset */
        $asset = $primary?->getRelationValue('mediaAsset');
        if (! $asset instanceof MediaAsset || $asset->getAttribute('state') !== 'available') {
            return null;
        }

        /** @var EloquentCollection<int, MediaVariant> $variants */
        $variants = $asset->getRelation('variants');
        /** @var MediaVariant|null $variant */
        $variant = $variants->first(static fn (MediaVariant $candidate): bool => $candidate->getAttribute('variant_kind') === 'thumbnail'
            && $candidate->getAttribute('transform_profile') === MediaIngestService::TRANSFORM_PROFILE
            && $candidate->getAttribute('state') === 'available');

        return $variant instanceof MediaVariant ? route('admin.media.variant', $variant) : null;
    }

    private function formatSeconds(float $seconds): string
    {
        $seconds = max(0, (int) round($seconds));
        if ($seconds < 60) {
            return $seconds.'s';
        }

        $minutes = intdiv($seconds, 60);
        $remaining = $seconds % 60;

        return $minutes.'m '.str_pad((string) $remaining, 2, '0', STR_PAD_LEFT).'s';
    }
}
