<?php

namespace App\Filament\Support;

use App\Domain\Analytics\ArtistReportingService;
use App\Domain\Media\PublicMedia;
use App\Models\BlogPost;
use App\Models\Exhibition;
use App\Models\JournalEntryMedia;
use App\Models\MediaAsset;
use App\Models\MediaVariant;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Support\Str;

final class JournalWorkspaceReadModel
{
    private const PAGE_SIZES = [25, 50, 100];

    private const DEFAULT_PAGE_SIZE = 50;

    public function __construct(private readonly ArtistReportingService $analytics) {}

    /**
     * @return array{
     *   rows:list<array<string,mixed>>,
     *   total:int,
     *   page:int,
     *   pages:int,
     *   page_size:int,
     *   unfiltered_entry_count:?int,
     *   metrics:?list<array{label:string,value:int|string,description:string}>
     * }
     */
    public function posts(
        int $sectionId,
        string $statusFilter,
        string $search,
        int $page,
        int $pageSize,
        ?string $journalPublicUrl,
        string $journalSlug,
        bool $includeMetrics = true,
    ): array {
        $query = BlogPost::query()->where('site_section_id', $sectionId);
        if ($statusFilter !== 'any') {
            $query->where('state', $statusFilter);
        }
        $term = trim($search);
        if ($term !== '') {
            $query->where(fn (Builder $searchQuery) => $searchQuery
                ->where('title', 'ilike', '%'.$term.'%')
                ->orWhere('excerpt', 'ilike', '%'.$term.'%'));
        }

        [$total, $page, $pages, $pageSize] = $this->pagination($query, $page, $pageSize);
        $canonicalIds = BlogPost::query()
            ->where('site_section_id', $sectionId)
            ->orderBy('position')
            ->orderBy('id')
            ->pluck('id')
            ->map(fn ($id): int => (int) $id)
            ->values();
        $ranks = $canonicalIds->flip();
        $query->with(['mediaUsages' => function ($usages): void {
            $usages->where('role', JournalEntryMedia::ROLE_COVER)->with('mediaAsset.variants');
        }]);

        /** @var EloquentCollection<int, BlogPost> $records */
        $records = $query->orderBy('position')->orderBy('id')->forPage($page, $pageSize)->get();
        $now = now();
        $count = $canonicalIds->count();
        $rows = $records->map(function (BlogPost $post) use ($ranks, $count, $now, $journalPublicUrl, $journalSlug): array {
            $state = (string) $post->getAttribute('state');
            $published = $post->getAttribute('published_at');
            $scheduled = $post->getAttribute('scheduled_at');
            $publication = match (true) {
                $state === 'scheduled' && $scheduled instanceof \DateTimeInterface => 'Scheduled '.$scheduled->format('M j, Y').' · '.$scheduled->format('H:i'),
                $published instanceof \DateTimeInterface => $published->format('M j, Y'),
                default => 'Not published',
            };
            $rank = ((int) ($ranks[(int) $post->getKey()] ?? 0)) + 1;

            return [
                'id' => (int) $post->getKey(),
                'rank' => $rank,
                'title' => (string) $post->getAttribute('title'),
                'excerpt' => filled($post->getAttribute('excerpt')) ? Str::limit(trim((string) $post->getAttribute('excerpt')), 140) : null,
                'publication' => $publication,
                'state' => $state,
                'thumbnail_url' => $this->coverThumbnailUrl($post),
                'public_url' => $journalPublicUrl !== null && $this->postIsPublic($post, $now)
                    ? route('journal.show', ['section' => $journalSlug, 'slug' => $post->getAttribute('slug')])
                    : null,
                'can_move_up' => $rank > 1,
                'can_move_down' => $rank < $count,
                'can_delete' => ! in_array($state, ['published', 'scheduled'], true),
                'delete_help' => in_array($state, ['published', 'scheduled'], true)
                    ? 'Unpublish or cancel schedule before deleting'
                    : null,
            ];
        })->all();

        $unfilteredEntryCount = null;
        $metrics = null;
        if ($includeMetrics) {
            $recordsForMetrics = BlogPost::query()
                ->where('site_section_id', $sectionId)
                ->get(['id', 'state', 'published_at', 'scheduled_at']);
            $unfilteredEntryCount = $recordsForMetrics->count();
            $analytics = $this->analytics->blog(null, '30d');
            $metrics = [
                ['label' => 'Reads · 30d', 'value' => $this->analyticsValue($analytics['reads'] ?? null), 'description' => $this->analyticsDescription($analytics, 'Posts opened')],
                ['label' => 'Published', 'value' => $recordsForMetrics->where('state', 'published')->count(), 'description' => 'Live posts'],
                ['label' => 'Scheduled', 'value' => $recordsForMetrics->where('state', 'scheduled')->count(), 'description' => 'Queued posts'],
                ['label' => 'Draft', 'value' => $recordsForMetrics->where('state', 'draft')->count(), 'description' => 'Work in progress'],
                ['label' => 'Unpublished', 'value' => $recordsForMetrics->where('state', 'unpublished')->count(), 'description' => 'Offline posts'],
                ['label' => 'Archived', 'value' => $recordsForMetrics->where('state', 'archived')->count(), 'description' => 'Retained posts'],
            ];
        }

        return [
            'rows' => $rows,
            'total' => $total,
            'page' => $page,
            'pages' => $pages,
            'page_size' => $pageSize,
            'unfiltered_entry_count' => $unfilteredEntryCount,
            'metrics' => $metrics,
        ];
    }

    /**
     * @return array{
     *   rows:list<array<string,mixed>>,
     *   total:int,
     *   page:int,
     *   pages:int,
     *   page_size:int,
     *   unfiltered_entry_count:?int,
     *   metrics:?list<array{label:string,value:int|string,description:string}>
     * }
     */
    public function exhibitions(
        int $sectionId,
        string $statusFilter,
        string $timingFilter,
        string $search,
        int $page,
        int $pageSize,
        ?string $journalPublicUrl,
        bool $includeMetrics = true,
    ): array {
        $query = Exhibition::query()->where('site_section_id', $sectionId);
        if ($statusFilter === 'published') {
            $query->where('state', 'published');
        } elseif ($statusFilter === 'unpublished') {
            $query->where('state', '!=', 'published');
        }
        $this->applyTimingFilter($query, $timingFilter);
        $term = trim($search);
        if ($term !== '') {
            $query->where(function (Builder $searchQuery) use ($term): void {
                $searchQuery->where('title', 'ilike', '%'.$term.'%')
                    ->orWhere('venue', 'ilike', '%'.$term.'%')
                    ->orWhere('location_text', 'ilike', '%'.$term.'%')
                    ->orWhere('city', 'ilike', '%'.$term.'%')
                    ->orWhere('country', 'ilike', '%'.$term.'%')
                    ->orWhere('date_text', 'ilike', '%'.$term.'%');
            });
        }

        [$total, $page, $pages, $pageSize] = $this->pagination($query, $page, $pageSize);
        $canonicalIds = Exhibition::query()
            ->where('site_section_id', $sectionId)
            ->orderBy('position')
            ->orderBy('id')
            ->pluck('id')
            ->map(fn ($id): int => (int) $id)
            ->values();
        $ranks = $canonicalIds->flip();
        $count = $canonicalIds->count();
        $query->with(['mediaUsages' => function ($usages): void {
            $usages->where('role', JournalEntryMedia::ROLE_COVER)->with('mediaAsset.variants');
        }]);

        /** @var EloquentCollection<int, Exhibition> $records */
        $records = $query->orderBy('position')->orderBy('id')->forPage($page, $pageSize)->get();
        $now = now();
        $rows = $records->map(function (Exhibition $entry) use ($ranks, $count, $now, $journalPublicUrl): array {
            $internalState = (string) $entry->getAttribute('state');
            $state = $internalState === 'published' ? 'published' : 'unpublished';
            $rank = ((int) ($ranks[(int) $entry->getKey()] ?? 0)) + 1;
            $location = collect([$entry->getAttribute('venue'), $entry->getAttribute('city')])
                ->filter(fn (mixed $value): bool => is_string($value) && trim($value) !== '')
                ->map(fn (string $value): string => trim($value))
                ->unique()
                ->implode(' · ');

            return [
                'id' => (int) $entry->getKey(),
                'rank' => $rank,
                'title' => (string) $entry->getAttribute('title'),
                'location' => $location !== '' ? $location : null,
                'state' => $state,
                'timing' => $entry->temporalState($now),
                'vernissage' => $entry->vernissageDisplay(),
                'date_text' => $entry->displayDate() ?? '',
                'thumbnail_url' => $this->coverThumbnailUrl($entry),
                'public_url' => $journalPublicUrl !== null && $internalState === 'published' ? $journalPublicUrl : null,
                'can_move_up' => $rank > 1,
                'can_move_down' => $rank < $count,
                'can_delete' => $internalState !== 'published',
                'delete_help' => $internalState === 'published' ? 'Unpublish this exhibition before deleting' : null,
            ];
        })->all();

        $unfilteredEntryCount = null;
        $metrics = null;
        if ($includeMetrics) {
            $recordsForMetrics = Exhibition::query()
                ->where('site_section_id', $sectionId)
                ->get(['id', 'state', 'starts_on', 'ends_on']);
            $unfilteredEntryCount = $recordsForMetrics->count();
            $metricNow = now();
            $timing = $recordsForMetrics->map(fn (Exhibition $entry): string => $entry->temporalState($metricNow));
            $analytics = $this->analytics->exhibitions('30d');
            $metrics = [
                ['label' => 'Visits · 30d', 'value' => $this->analyticsValue($analytics['page']['visits'] ?? null), 'description' => $this->analyticsDescription($analytics, 'Journal page')],
                ['label' => 'Views · 30d', 'value' => $this->analyticsValue($analytics['page']['views'] ?? null), 'description' => $this->analyticsDescription($analytics, 'Journal page')],
                ['label' => 'Published', 'value' => $recordsForMetrics->where('state', 'published')->count(), 'description' => 'Public exhibitions'],
                ['label' => 'Current', 'value' => $timing->filter(fn (string $value): bool => $value === 'current')->count(), 'description' => 'Happening now'],
                ['label' => 'Upcoming', 'value' => $timing->filter(fn (string $value): bool => $value === 'upcoming')->count(), 'description' => 'Coming next'],
                ['label' => 'Interactions · 30d', 'value' => $this->analyticsSum($analytics['external_clicks'] ?? null, $analytics['directions_clicks'] ?? null), 'description' => $this->analyticsDescription($analytics, 'External + map')],
            ];
        }

        return [
            'rows' => $rows,
            'total' => $total,
            'page' => $page,
            'pages' => $pages,
            'page_size' => $pageSize,
            'unfiltered_entry_count' => $unfilteredEntryCount,
            'metrics' => $metrics,
        ];
    }

    /** @return array{0:int,1:int,2:int,3:int} */
    private function pagination(Builder $query, int $page, int $pageSize): array
    {
        $pageSize = $this->normalizePageSize($pageSize);
        $total = (clone $query)->count();
        $pages = max(1, (int) ceil($total / $pageSize));
        $page = min(max(1, $page), $pages);

        return [$total, $page, $pages, $pageSize];
    }

    private function normalizePageSize(mixed $value): int
    {
        $size = is_numeric($value) ? (int) $value : self::DEFAULT_PAGE_SIZE;

        return in_array($size, self::PAGE_SIZES, true) ? $size : self::DEFAULT_PAGE_SIZE;
    }

    private function analyticsValue(mixed $metric): int|string
    {
        if (! is_array($metric) || ($metric['state'] ?? null) !== 'available' || ! is_numeric($metric['value'] ?? null)) {
            return '—';
        }

        return (int) round((float) $metric['value']);
    }

    private function analyticsSum(mixed ...$metrics): int|string
    {
        $sum = 0.0;
        foreach ($metrics as $metric) {
            if (! is_array($metric) || ($metric['state'] ?? null) !== 'available' || ! is_numeric($metric['value'] ?? null)) {
                return '—';
            }
            $sum += (float) $metric['value'];
        }

        return (int) round($sum);
    }

    private function analyticsDescription(array $report, string $base): string
    {
        return match ((string) ($report['status'] ?? 'unavailable')) {
            'stale' => $base.' · stale',
            'loading' => $base.' · loading',
            'unavailable' => $base.' · unavailable',
            default => $base,
        };
    }

    private function applyTimingFilter(Builder $query, string $timingFilter): void
    {
        $today = now()->toDateString();
        if ($timingFilter === 'upcoming') {
            $query->whereDate('starts_on', '>', $today);

            return;
        }
        if ($timingFilter === 'unknown') {
            $query->whereNull('starts_on');

            return;
        }
        if ($timingFilter === 'current') {
            $query->whereNotNull('starts_on')->whereDate('starts_on', '<=', $today)->where(function (Builder $current) use ($today): void {
                $current->where(fn (Builder $range) => $range->whereNotNull('ends_on')->whereDate('ends_on', '>=', $today))
                    ->orWhere(fn (Builder $single) => $single->whereNull('ends_on')->whereDate('starts_on', '=', $today));
            });

            return;
        }
        if ($timingFilter === 'past') {
            $query->whereNotNull('starts_on')->where(function (Builder $past) use ($today): void {
                $past->where(fn (Builder $range) => $range->whereNotNull('ends_on')->whereDate('ends_on', '<', $today))
                    ->orWhere(fn (Builder $single) => $single->whereNull('ends_on')->whereDate('starts_on', '<', $today));
            });
        }
    }

    private function coverThumbnailUrl(BlogPost|Exhibition $entry): ?string
    {
        $usage = $entry->getRelationValue('mediaUsages')->first();
        if (! $usage instanceof JournalEntryMedia) {
            return null;
        }
        $asset = $usage->getRelationValue('mediaAsset');
        if (! $asset instanceof MediaAsset) {
            return null;
        }
        $variant = $asset->getRelationValue('variants')->first(fn (MediaVariant $candidate): bool =>
            $candidate->getAttribute('variant_kind') === PublicMedia::THUMBNAIL_KIND
            && $candidate->getAttribute('transform_profile') === PublicMedia::PUBLIC_TRANSFORM_PROFILE
            && $candidate->getAttribute('state') === 'available');

        return $variant instanceof MediaVariant ? route('admin.media.variant', $variant) : null;
    }

    private function postIsPublic(BlogPost $post, CarbonInterface $now): bool
    {
        $state = (string) $post->getAttribute('state');
        $published = $post->getAttribute('published_at');
        if ($state === 'published' && $published instanceof CarbonInterface) {
            return $published->lessThanOrEqualTo($now);
        }
        $scheduled = $post->getAttribute('scheduled_at');

        return $state === 'scheduled' && $scheduled instanceof CarbonInterface && $scheduled->lessThanOrEqualTo($now);
    }
}
