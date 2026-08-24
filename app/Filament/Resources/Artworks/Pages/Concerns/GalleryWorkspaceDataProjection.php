<?php

namespace App\Filament\Resources\Artworks\Pages\Concerns;

use App\Domain\Analytics\ArtistReportingService;
use App\Domain\Media\MediaIngestService;
use App\Domain\Media\MediaTypePolicy;
use App\Filament\Resources\MediaAssets\MediaAssetResource;
use App\Models\Artwork;
use App\Models\ArtworkMedia;
use App\Models\MediaAsset;
use App\Models\MediaVariant;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;

trait GalleryWorkspaceDataProjection
{
    private function loadArtworks(): void
    {
        /** @var EloquentCollection<int, Artwork> $records */
        $records = Artwork::query()
            ->where('artwork_category_id', $this->galleryContext['id'])
            ->with([
                'artworkMedia' => static fn ($query) => $query->where('role', 'primary')->orderBy('position'),
                'artworkMedia.mediaAsset.variants' => static fn ($query) => $query
                    ->where('variant_kind', MediaIngestService::THUMBNAIL_KIND)
                    ->where('transform_profile', MediaIngestService::TRANSFORM_PROFILE),
            ])
            ->orderBy('position')
            ->orderBy('id')
            ->get();

        $this->publishedCount = $records->where('state', 'published')->count();
        $analyticsKeys = $records->pluck('analytics_key')
            ->filter(static fn (mixed $key): bool => is_string($key) && trim($key) !== '')
            ->values()
            ->all();

        // One canonical Matomo-backed report for the Gallery. Per-artwork analytics below are
        // projected from this same response; there is no per-artwork reporting fan-out.
        $this->analytics = app(ArtistReportingService::class)->gallery(
            (string) $this->galleryContext['path'],
            $analyticsKeys,
            '30d',
        );

        $analyticsState = (string) ($this->analytics['artworks']['state'] ?? 'unavailable');
        $rawAnalyticsRows = $this->analytics['artworks']['rows'] ?? [];
        $analyticsRows = collect(is_array($rawAnalyticsRows) ? $rawAnalyticsRows : [])
            ->mapWithKeys(static function (mixed $row): array {
                if (! is_array($row)) {
                    return [];
                }

                $key = (string) ($row['analytics_key'] ?? '');

                return $key === '' ? [] : [$key => $row];
            });
        $galleryPublished = $this->galleryContext['state'] === 'published';
        $count = $records->count();

        $projected = $records->values()->map(function (Artwork $artwork, int $index) use ($analyticsRows, $analyticsState, $galleryPublished, $count): array {
            /** @var EloquentCollection<int, ArtworkMedia> $primaries */
            $primaries = $artwork->getRelation('artworkMedia')->where('role', 'primary')->values();
            /** @var ArtworkMedia|null $primary */
            $primary = $primaries->count() === 1 ? $primaries->first() : null;
            /** @var MediaAsset|null $primaryAsset */
            $primaryAsset = $primary?->getRelationValue('mediaAsset');
            /** @var MediaVariant|null $thumbnail */
            $thumbnail = $primaryAsset instanceof MediaAsset
                ? $primaryAsset->getRelation('variants')->first(static fn (MediaVariant $candidate): bool => $candidate->getAttribute('variant_kind') === MediaIngestService::THUMBNAIL_KIND
                    && $candidate->getAttribute('transform_profile') === MediaIngestService::TRANSFORM_PROFILE
                    && $candidate->getAttribute('state') === 'available')
                : null;
            $mime = $primaryAsset instanceof MediaAsset ? (string) $primaryAsset->getAttribute('mime_type') : '';
            $kind = MediaTypePolicy::isVideo($mime) ? 'video' : (MediaTypePolicy::isImage($mime) ? 'image' : 'none');
            $analyticsRow = $analyticsRows->get((string) $artwork->getAttribute('analytics_key'));
            $analyticsRow = is_array($analyticsRow) ? $analyticsRow : [];
            $analyticsAvailable = $analyticsState !== 'unavailable';

            return [
                'id' => (int) $artwork->getKey(),
                'sequence' => $index + 1,
                'title' => (string) $artwork->getAttribute('title'),
                'state' => (string) $artwork->getAttribute('state'),
                'state_label' => ucfirst((string) $artwork->getAttribute('state')),
                'readiness_label' => $this->readinessLabel($artwork, $galleryPublished, $primaries->count(), $primaryAsset),
                'is_ready' => $this->isReadyToPublish($artwork, $galleryPublished, $primaries->count(), $primaryAsset),
                'year' => $artwork->getAttribute('work_year'),
                'medium' => $artwork->getAttribute('medium'),
                'dimensions' => $artwork->getAttribute('dimensions'),
                'primary_kind' => $kind,
                'thumbnail_url' => $thumbnail instanceof MediaVariant ? route('admin.media.variant', $thumbnail) : null,
                'primary_original_url' => $primaryAsset instanceof MediaAsset && $primaryAsset->getAttribute('state') === 'available'
                    ? route('admin.media.original', $primaryAsset)
                    : null,
                'media_preview_url' => $primaryAsset instanceof MediaAsset && $primaryAsset->getAttribute('state') === 'available'
                    ? MediaAssetResource::getUrl('view', ['record' => $primaryAsset->getKey(), 'artwork' => $artwork->getKey()])
                    : null,
                'public_url' => $galleryPublished && $artwork->getAttribute('state') === 'published'
                    ? route('artworks.show', ['slug' => $artwork->getAttribute('slug')])
                    : null,
                'can_move_up' => $index > 0,
                'can_move_down' => $index < $count - 1,
                'analytics' => [
                    'available' => $analyticsAvailable,
                    'views' => $analyticsAvailable ? (int) ($analyticsRow['detail_views'] ?? 0) : null,
                    'opens' => $analyticsAvailable ? (int) ($analyticsRow['viewer_opens'] ?? 0) : null,
                    'zooms' => $analyticsAvailable ? (int) ($analyticsRow['zooms'] ?? 0) : null,
                    'attention' => $analyticsAvailable ? (string) ($analyticsRow['attention_label'] ?? '0s') : '—',
                ],
            ];
        });

        $currentIds = $projected->pluck('id')->all();
        $this->selectedArtworkIds = array_values(array_filter(
            $this->selectedArtworkIds,
            static fn (int|string $id): bool => in_array((int) $id, $currentIds, true),
        ));

        $search = mb_strtolower(trim((string) ($this->search ?? '')));
        $status = (string) ($this->statusFilter ?? 'any');
        $readiness = (string) ($this->readinessFilter ?? 'any');

        $this->artworks = $projected
            ->filter(static function (array $artwork) use ($search, $status, $readiness): bool {
                if ($status !== 'any' && $artwork['state'] !== $status) {
                    return false;
                }
                if ($readiness === 'ready' && ! $artwork['is_ready']) {
                    return false;
                }
                if ($readiness === 'needs-attention' && $artwork['is_ready']) {
                    return false;
                }
                if ($search === '') {
                    return true;
                }

                $haystack = mb_strtolower(implode(' ', array_filter([
                    $artwork['title'] ?? null,
                    $artwork['medium'] ?? null,
                    $artwork['year'] ?? null,
                    $artwork['dimensions'] ?? null,
                ], static fn (mixed $value): bool => $value !== null && $value !== '')));

                return str_contains($haystack, $search);
            })
            ->values()
            ->all();

        $this->metrics = $this->galleryMetrics($records);
    }

    /** @param EloquentCollection<int, Artwork> $records */
    private function galleryMetrics(EloquentCollection $records): array
    {
        $analytics = $this->analytics ?? [];
        $rowsState = (string) ($analytics['artworks']['state'] ?? 'unavailable');
        /** @var list<array<string, mixed>> $rows */
        $rows = [];
        $rawRows = $analytics['artworks']['rows'] ?? [];
        if (is_array($rawRows)) {
            foreach ($rawRows as $row) {
                if (is_array($row)) {
                    $rows[] = $row;
                }
            }
        }
        $opens = $rowsState === 'unavailable' ? null : array_sum(array_map(static fn (array $row): int => (int) ($row['viewer_opens'] ?? 0), $rows));
        $attentionSeconds = $rowsState === 'unavailable' ? null : array_sum(array_map(static fn (array $row): float => (float) ($row['attention_seconds'] ?? 0), $rows));

        return [
            ['label' => 'Artworks', 'value' => number_format($records->count()), 'description' => 'In this Gallery'],
            ['label' => 'Published', 'value' => number_format($this->publishedCount), 'description' => 'Currently public'],
            ['label' => 'Visits', 'value' => $this->metricValue($analytics['page']['visits'] ?? null), 'description' => 'Gallery · 30d'],
            ['label' => 'Views', 'value' => $this->metricValue($analytics['page']['views'] ?? null), 'description' => 'Gallery · 30d'],
            ['label' => 'Artwork opens', 'value' => $opens === null ? '—' : number_format($opens), 'description' => 'Viewer opens · 30d'],
            ['label' => 'Attention', 'value' => $attentionSeconds === null ? '—' : $this->formatSeconds($attentionSeconds), 'description' => 'Tracked artwork time · 30d'],
        ];
    }

    private function metricValue(mixed $metric): string
    {
        if (! is_array($metric) || ($metric['state'] ?? null) !== 'available' || ! is_numeric($metric['value'] ?? null)) {
            return '—';
        }

        return number_format((float) $metric['value'], ((float) $metric['value']) === floor((float) $metric['value']) ? 0 : 1);
    }

    private function formatSeconds(float $seconds): string
    {
        $seconds = max(0, (int) round($seconds));
        if ($seconds < 60) {
            return $seconds.'s';
        }
        if ($seconds < 3600) {
            return intdiv($seconds, 60).'m '.str_pad((string) ($seconds % 60), 2, '0', STR_PAD_LEFT).'s';
        }

        $hours = intdiv($seconds, 3600);
        $minutes = intdiv($seconds % 3600, 60);

        return $hours.'h '.str_pad((string) $minutes, 2, '0', STR_PAD_LEFT).'m';
    }
}
