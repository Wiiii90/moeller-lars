<?php

namespace App\Filament\Support;

use App\Domain\Admin\AdminActionCatalog;
use App\Domain\Admin\AdminActionReceiptService;
use App\Domain\Publication\PublicationService;
use App\Filament\Pages\Activity;
use App\Filament\Pages\SitePages;
use App\Filament\Resources\Artworks\ArtworkResource;
use App\Filament\Resources\BlogPosts\BlogPostResource;
use App\Filament\Resources\Exhibitions\ExhibitionResource;
use App\Filament\Resources\MediaAssets\MediaAssetResource;
use App\Filament\Resources\PublicContentSettings\PublicContentSettingResource;
use App\Models\Artwork;
use App\Models\ArtworkCategory;
use App\Models\AdminActivityOrderingEvent;
use App\Models\AdminActivityOrderingProjection;
use App\Models\ArtworkMaterialPreset;
use App\Models\AuditEvent;
use App\Models\BlogPost;
use App\Models\Exhibition;
use App\Models\MediaAsset;
use App\Models\PublicationCheckpoint;
use App\Models\PublicationCheckpointEvent;
use App\Models\SiteSection;
use App\Models\User;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

final class AdminActivityFeed
{
    public const ACTIVITY_WINDOW_DAYS = 180;

    private const FILTER_WINDOWS = [7, 30, self::ACTIVITY_WINDOW_DAYS];

    public function __construct(
        private readonly AdminActionReceiptService $receipts,
        private readonly PublicationService $publication,
    ) {}

    /**
     * @return array{activity: array<int, array<string, mixed>>, paginator: LengthAwarePaginator<int, AuditEvent>}
     */
    public function page(
        ?string $area = null,
        ?string $family = null,
        int $perPage = 30,
        ?User $actor = null,
        ?int $days = null,
        ?string $search = null,
        ?string $date = null,
        ?int $hour = null,
    ): array {
        $query = $this->query($area, $family, $days, $search, $date, $hour)
            ->with(['adminUser:id,name', 'publicationCheckpointEvent.checkpoint', 'activityOrderingEvent.projection'])
            ->orderByDesc('occurred_at')
            ->orderByDesc('id');

        /** @var LengthAwarePaginator<int, AuditEvent> $paginator */
        $paginator = $query->paginate($perPage)->withQueryString();

        return [
            'activity' => $this->project($paginator->getCollection(), $actor),
            'paginator' => $paginator,
        ];
    }

    /** @return array<string, mixed>|null */
    public function event(int $eventId, ?User $actor = null): ?array
    {
        /** @var AuditEvent|null $event */
        $event = AuditEvent::query()
            ->with(['adminUser:id,name', 'publicationCheckpointEvent.checkpoint', 'activityOrderingEvent.projection'])
            ->find($eventId);

        if (! $event instanceof AuditEvent || $event->getRelationValue('publicationCheckpointEvent') instanceof PublicationCheckpointEvent) {
            return null;
        }

        $orderingProjection = $event->getRelationValue('activityOrderingEvent');
        $orderingProjectionState = $orderingProjection instanceof AdminActivityOrderingEvent
            ? $orderingProjection->getRelationValue('projection')
            : null;
        if (
            AdminActionCatalog::definition((string) $event->getAttribute('action'))['family'] === 'ordering'
            && ! ($orderingProjectionState instanceof AdminActivityOrderingProjection)
        ) {
            return null;
        }
        if (
            $orderingProjectionState instanceof AdminActivityOrderingProjection
            && (int) $orderingProjectionState->getAttribute('last_audit_event_id') !== (int) $event->getKey()
        ) {
            $event = AuditEvent::query()
                ->with(['adminUser:id,name', 'publicationCheckpointEvent.checkpoint', 'activityOrderingEvent.projection'])
                ->find((int) $orderingProjectionState->getAttribute('last_audit_event_id'));
        }

        if (! $event instanceof AuditEvent || $event->getRelationValue('publicationCheckpointEvent') instanceof PublicationCheckpointEvent) {
            return null;
        }

        return $this->project(new EloquentCollection([$event]), $actor)[0] ?? null;
    }

    public function exists(): bool
    {
        return $this->query()->exists();
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
        ?int $days = null,
        ?string $search = null,
        ?string $date = null,
        ?int $hour = null,
    ): array {
        $query = $this->query($area, $family, $days, $search, $date, $hour);
        $driver = $query->getModel()->getConnection()->getDriverName();
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
            $hourValue = (int) $row->bucket;
            if ($hourValue >= 0 && $hourValue <= 23) {
                $hourly[$hourValue] = (int) $row->aggregate;
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
     *     staged_groups:list<array{area:string,entity:string,count:int}>,
     *     current_activities:int,
     *     preflight:array{status:string,label:string,blockers:list<string>},
     *     latest:?array{id:int,message:?string,change_count:int,when:string,timestamp:string,actor:string},
     *     recent:array<int, array{id:int,message:?string,change_count:int,when:string,timestamp:string,actor:string}>
     * }
     */
    public function publicationContext(int $limit = 4): array
    {
        $limit = max(1, min(6, $limit));
        $summary = $this->publication->pendingSummary();
        $currentActivities = $this->query()->count();

        /** @var EloquentCollection<int, PublicationCheckpoint> $checkpointModels */
        $checkpointModels = PublicationCheckpoint::query()
            ->with('adminUser:id,name')
            ->orderByDesc('published_at')
            ->orderByDesc('id')
            ->limit($limit)
            ->get();

        $checkpoints = $checkpointModels
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
            'staged' => $summary['total'],
            'staged_groups' => $summary['groups'],
            'current_activities' => $currentActivities,
            'preflight' => $this->publication->preflight($summary),
            'latest' => $checkpoints[0] ?? null,
            'recent' => array_slice($checkpoints, 1),
        ];
    }

    /** @return array<int, array<string, mixed>> */
    public function recent(int $limit = 7): array
    {
        /** @var EloquentCollection<int, AuditEvent> $events */
        $events = $this->query()
            ->with(['adminUser:id,name', 'publicationCheckpointEvent.checkpoint', 'activityOrderingEvent.projection'])
            ->orderByDesc('occurred_at')
            ->orderByDesc('id')
            ->limit($limit)
            ->get();

        return $this->project($events);
    }

    public function query(
        ?string $area = null,
        ?string $family = null,
        ?int $days = null,
        ?string $search = null,
        ?string $date = null,
        ?int $hour = null,
    ): Builder {
        $query = $this->logicalActivityQuery()
            ->whereDoesntHave('publicationCheckpointEvent');
        $driver = $query->getModel()->getConnection()->getDriverName();
        $dateExpression = match ($driver) {
            'pgsql' => 'occurred_at::date',
            default => 'DATE(occurred_at)',
        };
        $hourExpression = match ($driver) {
            'sqlite' => "CAST(strftime('%H', occurred_at) AS INTEGER)",
            'mysql', 'mariadb' => 'HOUR(occurred_at)',
            default => 'EXTRACT(HOUR FROM occurred_at)::int',
        };

        if (is_string($date) && preg_match('/^\d{4}-\d{2}-\d{2}$/', $date) === 1) {
            $query->whereRaw($dateExpression.' = ?', [$date]);
        } elseif ($days !== null) {
            $days = in_array($days, self::FILTER_WINDOWS, true) ? $days : self::ACTIVITY_WINDOW_DAYS;
            $query->where('occurred_at', '>=', now()->subDays($days));
        }

        if ($hour !== null && $hour >= 0 && $hour <= 23) {
            $query->whereRaw($hourExpression.' = ?', [$hour]);
        }

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
                    $query->whereIn('action', $searchActionKeys);
                    $query->orWhereHas('adminUser', static function (Builder $adminUserQuery) use ($normalizedSearch): void {
                        $adminUserQuery->whereRaw('LOWER(name) LIKE ?', ['%'.$normalizedSearch.'%']);
                    });

                    return;
                }

                $query->whereHas('adminUser', static function (Builder $adminUserQuery) use ($normalizedSearch): void {
                    $adminUserQuery->whereRaw('LOWER(name) LIKE ?', ['%'.$normalizedSearch.'%']);
                });
            });
        }

        return $query;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function forCheckpoint(int $checkpointId): array
    {
        if ($checkpointId < 1) {
            return [];
        }

        /** @var EloquentCollection<int, AuditEvent> $events */
        $events = $this->logicalActivityQuery()
            ->whereHas('publicationCheckpointEvent', static function (Builder $query) use ($checkpointId): void {
                $query->where('publication_checkpoint_id', $checkpointId);
            })
            ->with(['adminUser:id,name', 'publicationCheckpointEvent.checkpoint', 'activityOrderingEvent.projection'])
            ->orderBy('occurred_at')
            ->orderBy('id')
            ->get();

        return $this->project($events);
    }

    /**
     * @param list<int> $checkpointIds
     * @return array<int, int>
     */
    public function countsForCheckpoints(array $checkpointIds): array
    {
        $checkpointIds = array_values(array_unique(array_filter(
            array_map(static fn (mixed $id): int => (int) $id, $checkpointIds),
            static fn (int $id): bool => $id > 0,
        )));

        if ($checkpointIds === []) {
            return [];
        }

        return DB::table('publication_checkpoint_events as checkpoint_event')
            ->join('audit_events as audit_event', 'audit_event.id', '=', 'checkpoint_event.audit_event_id')
            ->leftJoin('admin_activity_ordering_events as ordering_event', 'ordering_event.audit_event_id', '=', 'audit_event.id')
            ->leftJoin('admin_activity_ordering_projections as ordering_projection', 'ordering_projection.id', '=', 'ordering_event.projection_id')
            ->whereIn('checkpoint_event.publication_checkpoint_id', $checkpointIds)
            ->where(function ($query): void {
                $query
                    ->whereNull('ordering_event.audit_event_id')
                    ->orWhereColumn('ordering_projection.last_audit_event_id', 'audit_event.id');
            })
            ->selectRaw('checkpoint_event.publication_checkpoint_id AS checkpoint_id, COUNT(*) AS aggregate')
            ->groupBy('checkpoint_event.publication_checkpoint_id')
            ->get()
            ->mapWithKeys(static fn (object $row): array => [(int) $row->checkpoint_id => (int) $row->aggregate])
            ->all();
    }

    /** @return Builder<AuditEvent> */
    private function logicalActivityQuery(): Builder
    {
        $orderingActions = AdminActionCatalog::keysForFamily('ordering');

        return AuditEvent::query()
            ->where(function (Builder $activityQuery) use ($orderingActions): void {
                $activityQuery
                    ->whereNotIn('action', $orderingActions)
                    ->orWhereExists(function ($projectionQuery): void {
                        $projectionQuery
                            ->selectRaw('1')
                            ->from('admin_activity_ordering_events as ordering_event')
                            ->join('admin_activity_ordering_projections as ordering_projection', 'ordering_projection.id', '=', 'ordering_event.projection_id')
                            ->whereColumn('ordering_event.audit_event_id', 'audit_events.id')
                            ->whereColumn('ordering_projection.last_audit_event_id', 'audit_events.id');
                    });
            });
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
            $metadata = $event->getAttribute('metadata');
            $metadata = is_array($metadata) ? $metadata : [];
            $historicalTarget = is_string($metadata['target_label'] ?? null)
                ? trim($metadata['target_label'])
                : '';
            $orderingProjection = $event->getRelationValue('activityOrderingEvent');
            $orderingProjectionState = $orderingProjection instanceof AdminActivityOrderingEvent
                ? $orderingProjection->getRelationValue('projection')
                : null;
            $orderingScopeTarget = $orderingProjectionState instanceof AdminActivityOrderingProjection
                && is_string($orderingProjectionState->getAttribute('target_label'))
                ? trim((string) $orderingProjectionState->getAttribute('target_label'))
                : '';
            $target = $orderingScopeTarget !== ''
                ? $orderingScopeTarget
                : ($historicalTarget !== ''
                    ? $historicalTarget
                    : ($labels[$entityType][$entityId] ?? $this->fallbackTarget($entityType)));
            /** @var CarbonInterface $occurredAt */
            $occurredAt = $event->getAttribute('occurred_at');
            $adminUser = $event->getRelationValue('adminUser');
            $receipt = $undoReceipts[(int) $event->getKey()] ?? null;
            $undo = null;
            $orderingCount = $orderingProjectionState instanceof AdminActivityOrderingProjection
                ? (int) $orderingProjectionState->getAttribute('event_count')
                : 1;
            $checkpointEvent = $event->getRelationValue('publicationCheckpointEvent');
            $checkpoint = $checkpointEvent instanceof PublicationCheckpointEvent
                ? $checkpointEvent->getRelationValue('checkpoint')
                : null;
            $changeSummary = $orderingProjectionState instanceof AdminActivityOrderingProjection
                ? null
                : $this->changeSummary($metadata['change_summary'] ?? null);
            $actionLabel = $orderingProjectionState instanceof AdminActivityOrderingProjection
                ? $definition['label']
                : $this->activityLabel($actionKey, $definition, $changeSummary);

            if (is_array($receipt)) {
                $inverseLabel = (string) $receipt['inverse_label'];
                $undo = [
                    'id' => (int) $receipt['id'],
                    'inverse_label' => $inverseLabel,
                    'confirmation' => $this->undoConfirmation($actionLabel, $target, $inverseLabel, $changeSummary),
                ];
            }

            return [
                'id' => (int) $event->getKey(),
                'action_key' => $actionKey,
                'action' => $actionLabel,
                'area' => $definition['area'],
                'family' => $definition['family'],
                'entity_type' => $entityType,
                'entity_id' => $entityId,
                'target' => $target,
                'url' => $this->targetUrl($entityType, $entityId, isset($labels[$entityType][$entityId])),
                'actor' => $adminUser?->getAttribute('name') ?? 'Admin',
                'when' => $occurredAt->diffForHumans(),
                'timestamp' => $occurredAt->format('Y-m-d H:i'),
                'metadata' => $metadata,
                'change_summary' => $changeSummary,
                'ordering_projection' => $orderingProjectionState instanceof AdminActivityOrderingProjection
                    ? [
                        'event_count' => $orderingCount,
                        'item_count' => (int) $orderingProjectionState->getAttribute('item_count'),
                        'is_identity' => $orderingProjectionState->isIdentity(),
                        'started_at' => $orderingProjectionState->getAttribute('started_at')?->format('Y-m-d H:i'),
                        'ended_at' => $orderingProjectionState->getAttribute('ended_at')?->format('Y-m-d H:i'),
                    ]
                    : null,
                'publication_status' => $checkpoint !== null ? 'committed' : 'staged',
                'checkpoint_id' => $checkpoint?->getKey(),
                'checkpoint_short_hash' => $checkpoint instanceof PublicationCheckpoint ? $checkpoint->shortHash() : null,
                'checkpoint_message' => $checkpoint?->getAttribute('message'),
                'checkpoint_at' => $checkpoint?->getAttribute('published_at')?->format('Y-m-d H:i'),
                'undo' => $undo,
            ];
        })->values()->all();
    }

    /**
     * @param  array{label:string,area:string,family:string}  $definition
     * @param  array{count:int,truncated:bool,items:list<array{field:string,label:string,before:string,after:string}>}|null  $summary
     */
    private function activityLabel(string $actionKey, array $definition, ?array $summary): string
    {
        if ($summary === null || $summary['items'] === []) {
            return $definition['label'];
        }

        $items = collect($summary['items'])
            ->filter(static fn (mixed $item): bool => is_array($item)
                && is_string($item['label'] ?? null)
                && trim($item['label']) !== '')
            ->take(2)
            ->values();

        if ($items->isEmpty()) {
            return $definition['label'];
        }

        $shown = $items
            ->map(static fn (array $item): string => $item['label'].': '.$item['before'].' → '.$item['after'])
            ->implode('; ');
        $remaining = max(0, $summary['count'] - $items->count());
        $suffix = $remaining > 0 ? ' +'.$remaining.' more' : '';

        if ($actionKey === 'admin.undo_applied') {
            return 'Restored '.$shown.$suffix;
        }

        if (in_array($definition['family'], ['edit', 'settings'], true)) {
            return 'Changed '.$shown.$suffix;
        }

        if (in_array($definition['family'], ['publish', 'media', 'ordering'], true)) {
            return $definition['label'].' · '.$shown.$suffix;
        }

        return $definition['label'];
    }

    /**
     * @param  array{count:int,truncated:bool,items:list<array{field:string,label:string,before:string,after:string}>}|null  $summary
     */
    private function undoConfirmation(string $actionLabel, string $target, string $inverseLabel, ?array $summary): string
    {
        if ($summary === null || $summary['items'] === []) {
            return 'Undo “'.$actionLabel.'” for “'.$target.'”? This will apply “'.$inverseLabel.'”.';
        }

        $restores = collect($summary['items'])
            ->take(2)
            ->map(static fn (array $item): string => $item['label'].' to “'.$item['before'].'”')
            ->implode('; ');
        $remaining = max(0, $summary['count'] - min(2, count($summary['items'])));
        if ($remaining > 0) {
            $restores .= '; +'.$remaining.' more';
        }

        return 'Undo “'.$actionLabel.'” for “'.$target.'”? This will restore '.$restores.'.';
    }

    /**
     * @return array{count:int,truncated:bool,items:list<array{field:string,label:string,before:string,after:string}>}|null
     */
    private function changeSummary(mixed $value): ?array
    {
        if (! is_array($value) || ! is_array($value['items'] ?? null)) {
            return null;
        }

        $items = [];
        foreach ($value['items'] as $item) {
            if (! is_array($item)) {
                continue;
            }

            $field = $item['field'] ?? null;
            $label = $item['label'] ?? null;
            $before = $item['before'] ?? null;
            $after = $item['after'] ?? null;
            if (! is_string($field) || ! is_string($label) || ! is_string($before) || ! is_string($after)) {
                continue;
            }

            $items[] = compact('field', 'label', 'before', 'after');
        }

        if ($items === []) {
            return null;
        }

        return [
            'count' => max(count($items), (int) ($value['count'] ?? count($items))),
            'truncated' => (bool) ($value['truncated'] ?? false),
            'items' => $items,
        ];
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
            'artwork_material_preset' => $this->pluckLabels(ArtworkMaterialPreset::class, $ids->get('artwork_material_preset', []), 'name'),
            'site_section' => $this->pluckLabels(SiteSection::class, $ids->get('site_section', []), 'title'),
            'media_asset' => $this->pluckLabels(MediaAsset::class, $ids->get('media_asset', []), 'original_filename'),
            'cv_entry' => [],
            'exhibition' => $this->pluckLabels(Exhibition::class, $ids->get('exhibition', []), 'title'),
            'blog_post' => $this->pluckLabels(BlogPost::class, $ids->get('blog_post', []), 'title'),
            'blog_setting' => [1 => 'Blog settings'],
            'public_content_setting' => [1 => 'Website settings'],
            'publication_checkpoint' => $this->checkpointLabels($ids->get('publication_checkpoint', [])),
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

    /** @param array<int,int> $ids
     * @return array<int,string>
     */
    private function checkpointLabels(array $ids): array
    {
        if ($ids === []) {
            return [];
        }

        return PublicationCheckpoint::query()
            ->whereKey($ids)
            ->get(['id', 'hash'])
            ->mapWithKeys(static fn (PublicationCheckpoint $checkpoint): array => [
                (int) $checkpoint->getKey() => 'Commit '.$checkpoint->shortHash(),
            ])
            ->all();
    }

    private function fallbackTarget(string $entityType): string
    {
        return match ($entityType) {
            'artwork' => 'Artwork no longer available',
            'artwork_category' => 'Gallery no longer available',
            'artwork_material_preset' => 'Material preset no longer available',
            'site_section' => 'Public page no longer available',
            'media_asset' => 'Media no longer available',
            'cv_entry' => 'Custom Page list entry no longer available',
            'exhibition' => 'Exhibition no longer available',
            'blog_post' => 'Blog post no longer available',
            'blog_setting' => 'Blog settings',
            'public_content_setting' => 'Website settings',
            'publication_checkpoint' => 'Publication version',
            default => 'Administrative record',
        };
    }

    private function targetUrl(string $entityType, int $entityId, bool $exists): ?string
    {
        if (! $exists && ! in_array($entityType, ['cv_entry', 'blog_setting', 'public_content_setting', 'publication_checkpoint'], true)) {
            return null;
        }

        return match ($entityType) {
            'artwork' => ArtworkResource::getUrl('edit', ['record' => $entityId]),
            'artwork_category' => ArtworkResource::getUrl('gallery', ['gallery' => $entityId]),
            'site_section' => SitePages::getUrl(),
            'media_asset' => MediaAssetResource::getUrl('view', ['record' => $entityId]),
            'cv_entry' => SitePages::getUrl(),
            'exhibition' => ExhibitionResource::getUrl('edit', ['record' => $entityId]),
            'blog_post' => BlogPostResource::getUrl('edit', ['record' => $entityId]),
            'blog_setting' => SitePages::getUrl(),
            'public_content_setting' => PublicContentSettingResource::getNavigationUrl(),
            'publication_checkpoint' => Activity::getUrl().'?view=commits',
            default => null,
        };
    }
}
