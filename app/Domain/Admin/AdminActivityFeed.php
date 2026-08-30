<?php

namespace App\Domain\Admin;

use App\Filament\Pages\SitePages;
use App\Filament\Resources\Artworks\ArtworkResource;
use App\Filament\Resources\BlogPosts\BlogPostResource;
use App\Filament\Resources\CvEntries\CvEntryResource;
use App\Filament\Resources\Exhibitions\ExhibitionResource;
use App\Filament\Resources\MediaAssets\MediaAssetResource;
use App\Filament\Resources\PublicContentSettings\PublicContentSettingResource;
use App\Models\Artwork;
use App\Models\ArtworkCategory;
use App\Models\AuditEvent;
use App\Models\BlogPost;
use App\Models\CvEntry;
use App\Models\Exhibition;
use App\Models\MediaAsset;
use App\Models\PublicationCheckpoint;
use App\Models\PublicationCheckpointEvent;
use App\Models\PublicationEventState;
use App\Models\SiteSection;
use App\Models\User;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;

final class AdminActivityFeed
{
    public const ACTIVITY_WINDOW_DAYS = 180;

    private const FILTER_WINDOWS = [7, 30, self::ACTIVITY_WINDOW_DAYS];

    public function __construct(private readonly AdminActionReceiptService $receipts) {}

    /**
     * @return array{activity: array<int, array<string, mixed>>, paginator: LengthAwarePaginator<int, AuditEvent>}
     */
    public function page(
        ?string $area = null,
        ?string $family = null,
        int $perPage = 30,
        ?User $actor = null,
        int $days = self::ACTIVITY_WINDOW_DAYS,
        ?string $search = null,
    ): array {
        $query = $this->filteredQuery($area, $family, $days, $search)
            ->with(['adminUser:id,name', 'publicationCheckpointEvent.checkpoint', 'publicationEventState'])
            ->orderByDesc('occurred_at')
            ->orderByDesc('id');

        /** @var LengthAwarePaginator<int, AuditEvent> $paginator */
        $paginator = $query->paginate($perPage)->withQueryString();

        return [
            'activity' => $this->project($paginator->getCollection(), $actor),
            'paginator' => $paginator,
        ];
    }

    /**
     * @return array{
     *     total:int,
     *     hourly:array<int, int>,
     *     daily:array<string, int>,
     *     active_days:int,
     *     areas:int,
     *     families:int,
     *     actors:int,
     *     latest_at:mixed
     * }
     */
    public function overview(
        ?string $area = null,
        ?string $family = null,
        int $days = self::ACTIVITY_WINDOW_DAYS,
        ?string $search = null,
    ): array {
        $query = $this->filteredQuery($area, $family, $days, $search);
        $driver = $query->getConnection()->getDriverName();
        $hourExpression = match ($driver) {
            'sqlite' => "CAST(strftime('%H', occurred_at) AS INTEGER)",
            'mysql', 'mariadb' => 'HOUR(occurred_at)',
            default => 'EXTRACT(HOUR FROM occurred_at)::int',
        };
        $dateExpression = match ($driver) {
            'pgsql' => 'occurred_at::date',
            default => 'DATE(occurred_at)',
        };

        $hourly = array_fill(0, 24, 0);
        $hourRows = (clone $query)
            ->toBase()
            ->selectRaw($hourExpression.' AS bucket, COUNT(*) AS aggregate')
            ->groupByRaw($hourExpression)
            ->orderBy('bucket')
            ->get();
        foreach ($hourRows as $row) {
            $hour = (int) $row->bucket;
            if ($hour >= 0 && $hour <= 23) {
                $hourly[$hour] = (int) $row->aggregate;
            }
        }

        $daily = [];
        $dayRows = (clone $query)
            ->toBase()
            ->selectRaw($dateExpression.' AS bucket, COUNT(*) AS aggregate')
            ->groupByRaw($dateExpression)
            ->orderBy('bucket')
            ->get();
        foreach ($dayRows as $row) {
            $daily[(string) $row->bucket] = (int) $row->aggregate;
        }

        $actionCounts = [];
        $actionRows = (clone $query)
            ->toBase()
            ->selectRaw('action, COUNT(*) AS aggregate')
            ->groupBy('action')
            ->get();
        foreach ($actionRows as $row) {
            $actionCounts[(string) $row->action] = (int) $row->aggregate;
        }

        $areas = [];
        $families = [];
        foreach (array_keys($actionCounts) as $action) {
            $definition = AdminActionCatalog::definition($action);
            $areas[$definition['area']] = true;
            $families[$definition['family']] = true;
        }

        return [
            'total' => array_sum($actionCounts),
            'hourly' => $hourly,
            'daily' => $daily,
            'active_days' => count(array_filter($daily, static fn (int $count): bool => $count > 0)),
            'areas' => count($areas),
            'families' => count($families),
            'actors' => (clone $query)->whereNotNull('admin_user_id')->distinct()->count('admin_user_id'),
            'latest_at' => (clone $query)->max('occurred_at'),
        ];
    }

    /**
     * @return array{
     *     staged:int,
     *     latest:?array{id:int,message:?string,change_count:int,when:string,timestamp:string,actor:string},
     *     recent:array<int, array{id:int,message:?string,change_count:int,when:string,timestamp:string,actor:string}>
     * }
     */
    public function publicationContext(int $limit = 4): array
    {
        $limit = max(1, min(6, $limit));
        $staged = PublicationEventState::query()
            ->where('status', PublicationEventState::STATUS_PENDING)
            ->whereDoesntHave('auditEvent.publicationCheckpointEvent')
            ->count();

        $checkpoints = PublicationCheckpoint::query()
            ->with('adminUser:id,name')
            ->orderByDesc('published_at')
            ->orderByDesc('id')
            ->limit($limit)
            ->get()
            ->map(static function (PublicationCheckpoint $checkpoint): array {
                /** @var CarbonInterface $publishedAt */
                $publishedAt = $checkpoint->getAttribute('published_at');
                $adminUser = $checkpoint->getRelationValue('adminUser');
                $message = $checkpoint->getAttribute('message');

                return [
                    'id' => (int) $checkpoint->getKey(),
                    'message' => is_string($message) && trim($message) !== '' ? trim($message) : null,
                    'change_count' => (int) $checkpoint->getAttribute('change_count'),
                    'when' => $publishedAt->diffForHumans(),
                    'timestamp' => $publishedAt->format('Y-m-d H:i'),
                    'actor' => $adminUser?->getAttribute('name') ?? 'Admin',
                ];
            })
            ->values()
            ->all();

        return [
            'staged' => $staged,
            'latest' => $checkpoints[0] ?? null,
            'recent' => array_slice($checkpoints, 1),
        ];
    }

    /** @return array<int, array<string, mixed>> */
    public function recent(int $limit = 7): array
    {
        /** @var EloquentCollection<int, AuditEvent> $events */
        $events = $this->filteredQuery(days: self::ACTIVITY_WINDOW_DAYS)
            ->with(['adminUser:id,name', 'publicationCheckpointEvent.checkpoint', 'publicationEventState'])
            ->orderByDesc('occurred_at')
            ->orderByDesc('id')
            ->limit($limit)
            ->get();

        return $this->project($events);
    }

    private function filteredQuery(
        ?string $area = null,
        ?string $family = null,
        int $days = self::ACTIVITY_WINDOW_DAYS,
        ?string $search = null,
    ): Builder {
        $days = in_array($days, self::FILTER_WINDOWS, true) ? $days : self::ACTIVITY_WINDOW_DAYS;
        $query = AuditEvent::query()->where('occurred_at', '>=', now()->subDays($days));

        $actionKeys = $this->filteredActionKeys($area, $family);
        if ($actionKeys !== null) {
            $query->whereIn('action', $actionKeys);
        }

        $search = trim((string) $search);
        if ($search !== '') {
            $searchActionKeys = $this->searchActionKeys($search);
            $normalizedSearch = mb_strtolower($search);

            $query->where(function (Builder $query) use ($searchActionKeys, $normalizedSearch): void {
                if ($searchActionKeys !== []) {
                    $query->whereIn('action', $searchActionKeys)
                        ->orWhereHas('adminUser', static fn (Builder $adminUserQuery) => $adminUserQuery
                            ->whereRaw('LOWER(name) LIKE ?', ['%'.$normalizedSearch.'%']));

                    return;
                }

                $query->whereHas('adminUser', static fn (Builder $adminUserQuery) => $adminUserQuery
                    ->whereRaw('LOWER(name) LIKE ?', ['%'.$normalizedSearch.'%']));
            });
        }

        return $query;
    }

    /** @return array<int, string>|null */
    private function filteredActionKeys(?string $area, ?string $family): ?array
    {
        $areaKeys = $area !== null && $area !== '' ? AdminActionCatalog::keysForArea($area) : null;
        $familyKeys = $family !== null && $family !== '' ? AdminActionCatalog::keysForFamily($family) : null;

        if ($areaKeys === null && $familyKeys === null) {
            return null;
        }

        if ($areaKeys === null) {
            return $familyKeys;
        }

        if ($familyKeys === null) {
            return $areaKeys;
        }

        return array_values(array_intersect($areaKeys, $familyKeys));
    }

    /** @return array<int, string> */
    private function searchActionKeys(string $search): array
    {
        return array_values(array_filter(
            AdminActionCatalog::keys(),
            static function (string $key) use ($search): bool {
                $definition = AdminActionCatalog::definition($key);

                foreach ([$definition['label'], $definition['area'], $definition['family']] as $value) {
                    if (mb_stripos($value, $search) !== false) {
                        return true;
                    }
                }

                return false;
            },
        ));
    }

    /**
     * @param  Collection<int, AuditEvent>  $events
     * @return array<int, array<string, mixed>>
     */
    private function project(Collection $events, ?User $actor = null): array
    {
        $labels = $this->targetLabels($events);
        $undoReceipts = $actor instanceof User ? $this->receipts->availableForEvents($events, $actor) : [];

        return $events->map(function (AuditEvent $event) use ($labels, $undoReceipts): array {
            $actionKey = (string) $event->getAttribute('action');
            $entityType = (string) $event->getAttribute('entity_type');
            $entityId = (int) $event->getAttribute('entity_id');
            $definition = AdminActionCatalog::definition($actionKey);
            $target = $labels[$entityType][$entityId] ?? $this->fallbackTarget($entityType);
            /** @var CarbonInterface $occurredAt */
            $occurredAt = $event->getAttribute('occurred_at');
            $adminUser = $event->getRelationValue('adminUser');
            $receipt = $undoReceipts[(int) $event->getKey()] ?? null;
            $undo = null;
            $checkpointEvent = $event->getRelationValue('publicationCheckpointEvent');
            $checkpoint = $checkpointEvent instanceof PublicationCheckpointEvent
                ? $checkpointEvent->getRelationValue('checkpoint')
                : null;
            $publicationEventState = $event->getRelationValue('publicationEventState');

            if (is_array($receipt)) {
                $inverseLabel = (string) $receipt['inverse_label'];
                $undo = [
                    'id' => (int) $receipt['id'],
                    'inverse_label' => $inverseLabel,
                    'confirmation' => 'Undo “'.$definition['label'].'” for “'.$target.'”? This will apply “'.$inverseLabel.'”.',
                ];
            }

            return [
                'id' => (int) $event->getKey(),
                'action_key' => $actionKey,
                'action' => $definition['label'],
                'area' => $definition['area'],
                'family' => $definition['family'],
                'target' => $target,
                'url' => $this->targetUrl($entityType, $entityId, isset($labels[$entityType][$entityId])),
                'actor' => $adminUser?->getAttribute('name') ?? 'Admin',
                'when' => $occurredAt->diffForHumans(),
                'timestamp' => $occurredAt->format('Y-m-d H:i'),
                'publication_status' => $checkpoint !== null
                    ? 'committed'
                    : ($publicationEventState instanceof PublicationEventState
                        ? (string) $publicationEventState->getAttribute('status')
                        : null),
                'checkpoint_id' => $checkpoint?->getKey(),
                'checkpoint_message' => $checkpoint?->getAttribute('message'),
                'checkpoint_at' => $checkpoint?->getAttribute('published_at')?->format('Y-m-d H:i'),
                'undo' => $undo,
            ];
        })->values()->all();
    }

    /**
     * @param  Collection<int, AuditEvent>  $events
     * @return array<string, array<int, string>>
     */
    private function targetLabels(Collection $events): array
    {
        $ids = $events
            ->groupBy(fn (AuditEvent $event): string => (string) $event->getAttribute('entity_type'))
            ->map(fn (Collection $group): array => $group
                ->pluck('entity_id')
                ->filter(fn (mixed $id): bool => is_int($id) || ctype_digit((string) $id))
                ->map(fn (mixed $id): int => (int) $id)
                ->filter(fn (int $id): bool => $id > 0)
                ->unique()
                ->values()
                ->all());

        return [
            'artwork' => $this->pluckLabels(Artwork::class, $ids->get('artwork', []), 'title'),
            'artwork_category' => $this->pluckLabels(ArtworkCategory::class, $ids->get('artwork_category', []), 'name'),
            'site_section' => $this->pluckLabels(SiteSection::class, $ids->get('site_section', []), 'title'),
            'media_asset' => $this->pluckLabels(MediaAsset::class, $ids->get('media_asset', []), 'original_filename'),
            'cv_entry' => $this->pluckLabels(CvEntry::class, $ids->get('cv_entry', []), 'title'),
            'exhibition' => $this->pluckLabels(Exhibition::class, $ids->get('exhibition', []), 'title'),
            'blog_post' => $this->pluckLabels(BlogPost::class, $ids->get('blog_post', []), 'title'),
            'blog_setting' => [1 => 'Blog settings'],
            'public_content_setting' => [1 => 'Website settings'],
        ];
    }

    /**
     * @param  class-string<Model>  $model
     * @param  array<int, int>  $ids
     * @return array<int, string>
     */
    private function pluckLabels(string $model, array $ids, string $attribute): array
    {
        if ($ids === []) {
            return [];
        }

        return $model::query()
            ->whereKey($ids)
            ->pluck($attribute, 'id')
            ->map(fn (mixed $label): string => (string) $label)
            ->all();
    }

    private function fallbackTarget(string $entityType): string
    {
        return match ($entityType) {
            'artwork' => 'Artwork no longer available',
            'artwork_category' => 'Gallery no longer available',
            'site_section' => 'Public page no longer available',
            'media_asset' => 'Media no longer available',
            'cv_entry' => 'Vita entry no longer available',
            'exhibition' => 'Exhibition no longer available',
            'blog_post' => 'Blog post no longer available',
            'blog_setting' => 'Blog settings',
            'public_content_setting' => 'Website settings',
            default => 'Administrative record',
        };
    }

    private function targetUrl(string $entityType, int $entityId, bool $exists): ?string
    {
        if (! $exists && ! in_array($entityType, ['blog_setting', 'public_content_setting'], true)) {
            return null;
        }

        return match ($entityType) {
            'artwork' => ArtworkResource::getUrl('edit', ['record' => $entityId]),
            'artwork_category' => ArtworkResource::getUrl('gallery', ['gallery' => $entityId]),
            'site_section' => SitePages::getUrl(),
            'media_asset' => MediaAssetResource::getUrl('view', ['record' => $entityId]),
            'cv_entry' => CvEntryResource::getUrl('edit', ['record' => $entityId]),
            'exhibition' => ExhibitionResource::getUrl('edit', ['record' => $entityId]),
            'blog_post' => BlogPostResource::getUrl('edit', ['record' => $entityId]),
            'blog_setting' => SitePages::getUrl(),
            'public_content_setting' => PublicContentSettingResource::getNavigationUrl(),
            default => null,
        };
    }
}
