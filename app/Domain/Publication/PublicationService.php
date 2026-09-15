<?php

namespace App\Domain\Publication;

use App\Models\PublicationCheckpoint;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final class PublicationService
{
    public function __construct(
        private readonly PublicationMediaCleanupService $mediaCleanup,
        private readonly PublicationEventStateService $eventStates,
        private readonly PublicationSchemaGuard $schemaGuard,
        private readonly PublicationVersionService $versions,
    ) {}

    public function hasPendingChanges(): bool
    {
        $parts = array_map(
            static fn (string $table): string => sprintf(
                'SELECT 1 AS changed FROM public.%1$s AS working FULL OUTER JOIN committed.%1$s AS committed USING (id) WHERE '.PublicationSnapshot::ROW_DIFFERENCE_SQL,
                $table,
            ),
            PublicationSnapshot::TABLES,
        );

        $row = DB::selectOne(
            'SELECT EXISTS (SELECT 1 FROM ('.implode(' UNION ALL ', $parts).') AS publication_changes LIMIT 1) AS pending',
        );

        return in_array($row->pending ?? false, [true, 1, '1', 't'], true);
    }

    /**
     * @return array{
     *     total:int,
     *     groups:list<array{area:string,entity:string,count:int}>
     * }
     */
    public function pendingSummary(): array
    {
        $groups = [];
        $total = 0;

        foreach (PublicationSnapshot::TABLES as $table) {
            $row = DB::selectOne(sprintf(
                'SELECT COUNT(*)::int AS aggregate FROM public.%1$s AS working FULL OUTER JOIN committed.%1$s AS committed USING (id) WHERE '.PublicationSnapshot::ROW_DIFFERENCE_SQL,
                $table,
            ));
            $count = (int) ($row->aggregate ?? 0);
            if ($count < 1) {
                continue;
            }

            $definition = PublicationSnapshot::GROUPS[$table];
            $groups[] = [
                'area' => $definition['area'],
                'entity' => $definition['entity'],
                'count' => $count,
            ];
            $total += $count;
        }

        usort($groups, static fn (array $left, array $right): int => [$left['area'], $left['entity']] <=> [$right['area'], $right['entity']]);

        return ['total' => $total, 'groups' => $groups];
    }

    /**
     * Concrete current Working-vs-LIVE rows for on-demand review.
     * Framework-only timestamps are omitted from field differences just as they
     * are from PublicationSnapshot::ROW_DIFFERENCE_SQL.
     *
     * @return array{rows:list<array{area:string,entity:string,table:string,row_key:string,label:string,change:string,fields:list<string>}>,truncated:bool}
     */
    public function pendingDetails(int $limit = 200): array
    {
        $limit = max(1, min(500, $limit));
        $rows = [];
        $truncated = false;

        foreach (PublicationSnapshot::TABLES as $table) {
            $remaining = $limit - count($rows);
            if ($remaining < 1) {
                $truncated = true;
                break;
            }

            $changes = DB::select(
                "SELECT COALESCE(working.id, committed.id)::text AS row_key, CASE WHEN working.id IS NULL THEN 'removed' WHEN committed.id IS NULL THEN 'added' ELSE 'changed' END AS change_kind, to_jsonb(working)::text AS working_payload, to_jsonb(committed)::text AS committed_payload FROM public.{$table} AS working FULL OUTER JOIN committed.{$table} AS committed USING (id) WHERE ".PublicationSnapshot::ROW_DIFFERENCE_SQL.' ORDER BY COALESCE(working.id, committed.id) LIMIT ?',
                [$remaining + 1],
            );

            if (count($changes) > $remaining) {
                $changes = array_slice($changes, 0, $remaining);
                $truncated = true;
            }

            $definition = PublicationSnapshot::GROUPS[$table];
            foreach ($changes as $change) {
                $working = $this->decodePayload($change->working_payload ?? null);
                $committed = $this->decodePayload($change->committed_payload ?? null);
                $changeKind = (string) $change->change_kind;

                $rows[] = [
                    'area' => $definition['area'],
                    'entity' => $definition['entity'],
                    'table' => $table,
                    'row_key' => (string) $change->row_key,
                    'label' => $this->publicationRowLabel($working ?? $committed, (string) $change->row_key),
                    'change' => $changeKind,
                    'fields' => $changeKind === 'changed'
                        ? $this->changedFields($working ?? [], $committed ?? [])
                        : [],
                ];
            }

            if ($truncated) {
                break;
            }
        }

        return ['rows' => $rows, 'truncated' => $truncated];
    }

    /**
     * Report exactly the readiness checks the current Commit path can enforce.
     * Business-domain mutations are expected to preserve their own invariants
     * before they reach the publication snapshot.
     *
     * @param  array{total:int,groups:list<array{area:string,entity:string,count:int}>}|null  $summary
     * @return array{status:string,label:string,blockers:list<string>}
     */
    public function preflight(?array $summary = null): array
    {
        $summary ??= $this->pendingSummary();

        if ($summary['total'] < 1) {
            return [
                'status' => 'idle',
                'label' => 'Nothing staged',
                'blockers' => [],
            ];
        }

        try {
            $this->schemaGuard->assertParity();
        } catch (\Throwable $exception) {
            return [
                'status' => 'blocked',
                'label' => 'Publication blocked',
                'blockers' => [$exception->getMessage()],
            ];
        }

        return [
            'status' => 'ready',
            'label' => 'Ready to publish',
            'blockers' => [],
        ];
    }

    public function commit(User $actor, ?string $message = null): ?PublicationCheckpoint
    {
        $message = is_string($message) ? trim($message) : null;
        if ($message === '') {
            $message = null;
        }
        if ($message !== null && mb_strlen($message) > 240) {
            throw ValidationException::withMessages([
                'message' => 'The Commit message may contain no more than 240 characters.',
            ]);
        }

        $checkpoint = DB::transaction(function () use ($actor, $message): ?PublicationCheckpoint {
            DB::select('SELECT pg_advisory_xact_lock(?)', [PublicationSnapshot::LOCK_KEY]);
            $this->schemaGuard->assertParity();
            DB::statement(
                'LOCK TABLE '.implode(', ', array_map(
                    static fn (string $table): string => 'public.'.$table,
                    PublicationSnapshot::TABLES,
                )).' IN SHARE MODE',
            );

            $summary = $this->pendingSummary();
            if ($summary['total'] < 1) {
                $this->versions->clearWorkingContext();

                return null;
            }

            $pendingAuditEventIds = $this->eventStates->pendingEventIdsForCommit();
            /** @var PublicationCheckpoint|null $parent */
            $parent = PublicationCheckpoint::query()
                ->orderByDesc('published_at')
                ->orderByDesc('id')
                ->first();
            $workingContext = $this->versions->workingContext();
            $operation = $workingContext['operation'] ?? 'commit';
            $sourceId = $workingContext['source_publication_checkpoint_id'] ?? null;
            /** @var PublicationCheckpoint|null $source */
            $source = is_int($sourceId)
                ? PublicationCheckpoint::query()->find($sourceId)
                : null;

            DB::statement(
                'TRUNCATE TABLE '.implode(', ', array_map(
                    static fn (string $table): string => 'committed.'.$table,
                    PublicationSnapshot::TABLES,
                )),
            );

            foreach (PublicationSnapshot::TABLES as $table) {
                DB::statement("INSERT INTO committed.{$table} SELECT * FROM public.{$table}");
            }

            /** @var PublicationCheckpoint $checkpoint */
            $checkpoint = PublicationCheckpoint::query()->create([
                'admin_user_id' => $actor->getKey(),
                'message' => $message,
                'change_count' => $summary['total'],
                'published_at' => now(),
            ]);
            $checkpoint = $this->versions->capture(
                $checkpoint,
                $parent,
                is_string($operation) ? $operation : 'commit',
                $source,
            );

            if ($pendingAuditEventIds !== []) {
                $createdAt = now();
                DB::table('publication_checkpoint_events')->insert(array_map(
                    static fn (int $auditEventId): array => [
                        'publication_checkpoint_id' => $checkpoint->getKey(),
                        'audit_event_id' => $auditEventId,
                        'created_at' => $createdAt,
                    ],
                    $pendingAuditEventIds,
                ));
            }

            $this->versions->clearWorkingContext();

            return $checkpoint;
        }, attempts: 1);

        if ($checkpoint instanceof PublicationCheckpoint) {
            $this->mediaCleanup->drain();
        }

        return $checkpoint;
    }

    /** @return array<string,mixed>|null */
    private function decodePayload(mixed $payload): ?array
    {
        if (! is_string($payload) || $payload === '') {
            return null;
        }

        $decoded = json_decode($payload, true);

        return is_array($decoded) ? $decoded : null;
    }

    /** @param array<string,mixed>|null $payload */
    private function publicationRowLabel(?array $payload, string $rowKey): string
    {
        if ($payload !== null) {
            foreach (['title', 'name', 'original_filename', 'navigation_label', 'slug', 'scope', 'storage_key', 'type'] as $key) {
                $value = $payload[$key] ?? null;
                if (is_string($value) && trim($value) !== '') {
                    return trim($value);
                }
            }
        }

        return '#'.$rowKey;
    }

    /**
     * @param  array<string,mixed>  $working
     * @param  array<string,mixed>  $committed
     * @return list<string>
     */
    private function changedFields(array $working, array $committed): array
    {
        $ignored = ['id' => true, 'created_at' => true, 'updated_at' => true];
        $keys = array_values(array_unique([...array_keys($working), ...array_keys($committed)]));
        sort($keys);

        return array_values(array_filter(
            $keys,
            static fn (string $key): bool => ! isset($ignored[$key])
                && ($working[$key] ?? null) !== ($committed[$key] ?? null),
        ));
    }
}
