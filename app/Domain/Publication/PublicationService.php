<?php

namespace App\Domain\Publication;

use App\Models\PublicationCheckpoint;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final class PublicationService
{
    public function __construct(private readonly PublicationMediaCleanupService $mediaCleanup) {}

    public function hasPendingChanges(): bool
    {
        $parts = array_map(
            static fn (string $table): string => sprintf(
                'SELECT 1 AS changed FROM public.%1$s AS working FULL OUTER JOIN committed.%1$s AS committed USING (id) WHERE to_jsonb(working) IS DISTINCT FROM to_jsonb(committed)',
                $table,
            ),
            PublicationSnapshot::TABLES,
        );

        $row = DB::selectOne(
            'SELECT EXISTS (SELECT 1 FROM ('.implode(' UNION ALL ', $parts).') AS publication_changes LIMIT 1) AS pending',
        );

        return in_array($row?->pending ?? false, [true, 1, '1', 't'], true);
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
                'SELECT COUNT(*)::int AS aggregate FROM public.%1$s AS working FULL OUTER JOIN committed.%1$s AS committed USING (id) WHERE to_jsonb(working) IS DISTINCT FROM to_jsonb(committed)',
                $table,
            ));
            $count = (int) ($row?->aggregate ?? 0);
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
            DB::statement(
                'LOCK TABLE '.implode(', ', array_map(
                    static fn (string $table): string => 'public.'.$table,
                    PublicationSnapshot::TABLES,
                )).' IN SHARE MODE',
            );

            $summary = $this->pendingSummary();
            if ($summary['total'] < 1) {
                return null;
            }

            $pendingAuditEventIds = DB::table('audit_events')
                ->leftJoin('publication_checkpoint_events', 'publication_checkpoint_events.audit_event_id', '=', 'audit_events.id')
                ->whereNull('publication_checkpoint_events.audit_event_id')
                ->whereIn('audit_events.entity_type', PublicationSnapshot::AUDIT_ENTITY_TYPES)
                ->orderBy('audit_events.id')
                ->pluck('audit_events.id')
                ->map(static fn (mixed $id): int => (int) $id)
                ->all();

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

            return $checkpoint;
        }, attempts: 1);

        if ($checkpoint instanceof PublicationCheckpoint) {
            $this->mediaCleanup->drain();
        }

        return $checkpoint;
    }
}
