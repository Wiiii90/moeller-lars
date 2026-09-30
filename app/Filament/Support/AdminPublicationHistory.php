<?php

namespace App\Filament\Support;

use App\Domain\Admin\AdminActionCatalog;
use App\Domain\Publication\PublicationVersionService;
use App\Models\PublicationCheckpoint;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;

final class AdminPublicationHistory
{
    public function __construct(
        private readonly PublicationVersionService $versions,
        private readonly AdminActivityFeed $activity,
    ) {}

    /**
     * @return array{commits:list<array<string,mixed>>,paginator:LengthAwarePaginator<int,PublicationCheckpoint>}
     */
    public function page(
        ?string $area = null,
        ?string $family = null,
        string $search = '',
        ?string $date = null,
        ?int $hour = null,
        int $perPage = 20,
    ): array {
        $perPage = max(10, min(100, $perPage));
        $currentSchemaHash = $this->versions->schemaHash();
        $liveId = $this->versions->currentLiveCheckpoint()?->getKey();

        /** @var LengthAwarePaginator<int, PublicationCheckpoint> $paginator */
        $paginator = $this->queryForFilters($area, $family, $search, $date, $hour)
            ->with([
                'adminUser:id,name',
                'parent:id,hash,snapshot_available,schema_hash',
                'source:id,hash,snapshot_available,schema_hash',
            ])
            ->orderByDesc('published_at')
            ->orderByDesc('id')
            ->paginate($perPage, ['*'], 'commits_page')
            ->withQueryString();

        $activityCounts = $this->activity->countsForCheckpoints(
            $paginator->getCollection()
                ->map(static fn (PublicationCheckpoint $checkpoint): int => (int) $checkpoint->getKey())
                ->all(),
        );

        return [
            'commits' => $paginator->getCollection()
                ->map(fn (PublicationCheckpoint $checkpoint): array => $this->project(
                    $checkpoint,
                    $liveId === $checkpoint->getKey(),
                    $currentSchemaHash,
                    $activityCounts[(int) $checkpoint->getKey()] ?? 0,
                ))
                ->values()
                ->all(),
            'paginator' => $paginator,
        ];
    }

    /**
     * @return array{total:int,active_days:int,changes:int,activities:int,actors:int,latest_at:mixed}
     */
    public function overview(
        ?string $area = null,
        ?string $family = null,
        string $search = '',
        ?string $date = null,
        ?int $hour = null,
    ): array {
        $query = $this->queryForFilters($area, $family, $search, $date, $hour);
        $driver = $query->getModel()->getConnection()->getDriverName();
        $dateExpression = $this->dateExpression($driver);

        $activeDaysQuery = (clone $query)
            ->toBase()
            ->selectRaw($dateExpression.' AS bucket')
            ->groupByRaw($dateExpression);
        $activeDays = DB::query()->fromSub($activeDaysQuery, 'active_days')->count();

        $checkpointIds = (clone $query)
            ->pluck('publication_checkpoints.id')
            ->map(static fn (mixed $id): int => (int) $id)
            ->all();
        $activityCounts = $this->activity->countsForCheckpoints($checkpointIds);

        return [
            'total' => count($checkpointIds),
            'active_days' => $activeDays,
            'changes' => (int) (clone $query)->sum('change_count'),
            'activities' => array_sum($activityCounts),
            'actors' => (clone $query)
                ->whereNotNull('admin_user_id')
                ->distinct()
                ->count('admin_user_id'),
            'latest_at' => (clone $query)->max('published_at'),
        ];
    }

    /** @return Builder<PublicationCheckpoint> */
    public function queryForFilters(
        ?string $area = null,
        ?string $family = null,
        string $search = '',
        ?string $date = null,
        ?int $hour = null,
    ): Builder {
        $query = PublicationCheckpoint::query();
        $driver = $query->getModel()->getConnection()->getDriverName();

        $actionKeys = $this->filteredActionKeys($area, $family);
        if ($actionKeys !== null) {
            $query->whereHas('auditEvents.auditEvent', static function (Builder $events) use ($actionKeys): void {
                $events->whereIn('action', $actionKeys);
            });
        }

        if (is_string($date) && preg_match('/^\d{4}-\d{2}-\d{2}$/', $date) === 1) {
            $query->whereRaw($this->dateExpression($driver).' = ?', [$date]);
        }

        if ($hour !== null && $hour >= 0 && $hour <= 23) {
            $query->whereRaw($this->hourExpression($driver).' = ?', [$hour]);
        }

        $search = mb_strtolower(trim($search));
        if ($search === '') {
            return $query;
        }

        $needle = '%'.$search.'%';
        $searchActionKeys = $this->searchActionKeys($search);

        $query->where(function (Builder $query) use ($needle, $searchActionKeys): void {
            $query
                ->whereRaw("LOWER(COALESCE(hash, '')) LIKE ?", [$needle])
                ->orWhereRaw("LOWER(COALESCE(message, '')) LIKE ?", [$needle])
                ->orWhereHas('adminUser', static function (Builder $adminUserQuery) use ($needle): void {
                    $adminUserQuery->whereRaw('LOWER(name) LIKE ?', [$needle]);
                });

            if ($searchActionKeys !== []) {
                $query->orWhereHas('auditEvents.auditEvent', static function (Builder $events) use ($searchActionKeys): void {
                    $events->whereIn('action', $searchActionKeys);
                });
            }
        });

        return $query;
    }

    /** @return array<string,mixed>|null */
    public function checkpoint(int $checkpointId): ?array
    {
        /** @var PublicationCheckpoint|null $checkpoint */
        $checkpoint = PublicationCheckpoint::query()
            ->with([
                'adminUser:id,name',
                'parent:id,hash,snapshot_available,schema_hash',
                'source:id,hash,snapshot_available,schema_hash',
            ])
            ->find($checkpointId);
        if (! $checkpoint instanceof PublicationCheckpoint) {
            return null;
        }

        $currentSchemaHash = $this->versions->schemaHash();
        $liveId = $this->versions->currentLiveCheckpoint()?->getKey();
        $activity = $this->activity->forCheckpoint($checkpointId);
        $projected = $this->project(
            $checkpoint,
            $liveId === $checkpoint->getKey(),
            $currentSchemaHash,
            count($activity),
        );

        return [...$projected, 'activities' => $activity];
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

    private function dateExpression(string $driver): string
    {
        return $driver === 'pgsql' ? 'published_at::date' : 'DATE(published_at)';
    }

    private function hourExpression(string $driver): string
    {
        return match ($driver) {
            'sqlite' => "CAST(strftime('%H', published_at) AS INTEGER)",
            'mysql', 'mariadb' => 'HOUR(published_at)',
            default => 'EXTRACT(HOUR FROM published_at)::int',
        };
    }

    /** @return array<string,mixed> */
    private function project(PublicationCheckpoint $checkpoint, bool $live, string $currentSchemaHash, int $activityCount): array
    {
        /** @var CarbonInterface $publishedAt */
        $publishedAt = $checkpoint->getAttribute('published_at');
        $adminUser = $checkpoint->getRelationValue('adminUser');
        $parent = $checkpoint->getRelationValue('parent');
        $source = $checkpoint->getRelationValue('source');
        $hash = (string) ($checkpoint->getAttribute('hash') ?? '');
        $schemaHash = $checkpoint->getAttribute('schema_hash');
        $snapshotAvailable = (bool) $checkpoint->getAttribute('snapshot_available');
        $restorable = $snapshotAvailable
            && is_string($schemaHash)
            && hash_equals($schemaHash, $currentSchemaHash);
        $parentRestorable = $parent instanceof PublicationCheckpoint
            && (bool) $parent->getAttribute('snapshot_available')
            && is_string($parent->getAttribute('schema_hash'))
            && hash_equals((string) $parent->getAttribute('schema_hash'), $currentSchemaHash);
        $message = $checkpoint->getAttribute('message');
        $operation = (string) ($checkpoint->getAttribute('operation') ?? 'commit');

        return [
            'id' => (int) $checkpoint->getKey(),
            'hash' => $hash,
            'short_hash' => $hash !== '' ? substr($hash, 0, 10) : '#'.$checkpoint->getKey(),
            'snapshot_hash' => $checkpoint->getAttribute('snapshot_hash'),
            'schema_hash' => $schemaHash,
            'message' => is_string($message) && trim($message) !== '' ? trim($message) : null,
            'change_count' => (int) $checkpoint->getAttribute('change_count'),
            'activity_count' => $activityCount,
            'actor' => $adminUser?->getAttribute('name') ?? 'Admin',
            'when' => $publishedAt->diffForHumans(),
            'timestamp' => $publishedAt->format('Y-m-d H:i'),
            'operation' => $operation,
            'operation_label' => match ($operation) {
                'initial' => 'Initial state',
                'restore' => 'Restore',
                'revert' => 'Revert',
                default => 'Commit',
            },
            'live' => $live,
            'snapshot_available' => $snapshotAvailable,
            'restorable' => $restorable,
            'legacy' => ! $snapshotAvailable,
            'can_restore' => ! $live && $restorable,
            'can_revert' => $live && $parentRestorable,
            'parent_id' => $parent?->getKey(),
            'parent_hash' => $parent instanceof PublicationCheckpoint ? (string) ($parent->getAttribute('hash') ?? '') : null,
            'parent_short_hash' => $parent instanceof PublicationCheckpoint && (string) ($parent->getAttribute('hash') ?? '') !== ''
                ? substr((string) $parent->getAttribute('hash'), 0, 10)
                : null,
            'source_id' => $source?->getKey(),
            'source_hash' => $source instanceof PublicationCheckpoint ? (string) ($source->getAttribute('hash') ?? '') : null,
        ];
    }
}
