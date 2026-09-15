<?php

namespace App\Domain\Publication;

use App\Domain\Admin\AdminAuditService;
use App\Models\PublicationCheckpoint;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final class PublicationVersionService
{
    public function __construct(
        private readonly AdminAuditService $audit,
        private readonly PublicationEventStateService $eventStates,
        private readonly PublicationMediaCleanupService $mediaCleanup,
        private readonly PublicationSchemaGuard $schemaGuard,
    ) {}

    public function capture(
        PublicationCheckpoint $checkpoint,
        ?PublicationCheckpoint $parent = null,
        string $operation = 'commit',
        ?PublicationCheckpoint $source = null,
    ): PublicationCheckpoint {
        $this->schemaGuard->assertParity();

        DB::table('publication_version_rows')
            ->where('publication_checkpoint_id', $checkpoint->getKey())
            ->delete();

        foreach (PublicationSnapshot::TABLES as $table) {
            DB::statement(
                "INSERT INTO publication_version_rows (publication_checkpoint_id, table_name, row_key, payload) SELECT ?, ?, source.id::text, to_jsonb(source) FROM public.{$table} AS source",
                [(int) $checkpoint->getKey(), $table],
            );
        }

        $snapshotHash = $this->snapshotHash('public');
        $schemaHash = $this->schemaHash();
        $parentHash = $parent?->getAttribute('hash');
        $sourceHash = $source?->getAttribute('hash');
        $publishedAt = $checkpoint->getAttribute('published_at');
        $publishedAtValue = $publishedAt instanceof \DateTimeInterface
            ? $publishedAt->format('Y-m-d\TH:i:s.uP')
            : (string) $publishedAt;
        $hash = hash('sha256', implode('|', [
            is_string($parentHash) ? $parentHash : '',
            $snapshotHash,
            (string) ($checkpoint->getAttribute('admin_user_id') ?? ''),
            $publishedAtValue,
            (string) ($checkpoint->getAttribute('message') ?? ''),
            (string) $checkpoint->getAttribute('change_count'),
            $operation,
            is_string($sourceHash) ? $sourceHash : '',
        ]));

        $checkpoint->forceFill([
            'hash' => $hash,
            'snapshot_hash' => $snapshotHash,
            'schema_hash' => $schemaHash,
            'snapshot_available' => true,
            'operation' => $operation,
            'parent_publication_checkpoint_id' => $parent?->getKey(),
            'source_publication_checkpoint_id' => $source?->getKey(),
        ])->save();

        return $checkpoint->refresh();
    }

    /** @return array{changed:int, checkpoint:PublicationCheckpoint} */
    public function stageVersion(PublicationCheckpoint $checkpoint, User $actor): array
    {
        $changed = DB::transaction(function () use ($checkpoint, $actor): int {
            DB::select('SELECT pg_advisory_xact_lock(?)', [PublicationSnapshot::LOCK_KEY]);
            $this->schemaGuard->assertParity();
            $this->assertRestorable($checkpoint);
            $changed = $this->pendingCountAgainstCheckpoint($checkpoint);

            $this->mediaCleanup->queueAbandonedWorkingMedia();
            $this->replaceWorkingFromCheckpoint($checkpoint);
            $this->eventStates->clearUncheckpointedPendingStates();
            $this->setWorkingContext('restore', (int) $checkpoint->getKey());
            $this->audit->record(
                $actor,
                'publication.version_restored',
                'publication_checkpoint',
                (int) $checkpoint->getKey(),
            );

            return $changed;
        }, attempts: 1);

        $this->mediaCleanup->drain();

        return ['changed' => $changed, 'checkpoint' => $checkpoint->refresh()];
    }

    public function resetStagedChanges(User $actor): int
    {
        $changed = DB::transaction(function () use ($actor): int {
            DB::select('SELECT pg_advisory_xact_lock(?)', [PublicationSnapshot::LOCK_KEY]);
            $this->schemaGuard->assertParity();
            $changed = $this->pendingCount();
            if ($changed < 1) {
                $this->clearWorkingContext();

                return 0;
            }

            $this->mediaCleanup->queueAbandonedWorkingMedia();
            $this->replaceWorkingFromSchema('committed');
            $this->eventStates->clearUncheckpointedPendingStates();
            $this->clearWorkingContext();

            $live = $this->currentLiveCheckpoint();
            $this->audit->record(
                $actor,
                'publication.stage_reset',
                'publication_checkpoint',
                (int) ($live?->getKey() ?? 1),
            );

            return $changed;
        }, attempts: 1);

        if ($changed > 0) {
            $this->mediaCleanup->drain();
        }

        return $changed;
    }

    /** @return array{changed:int, checkpoint:PublicationCheckpoint, reverted:PublicationCheckpoint} */
    public function stageRevertOfCurrent(User $actor): array
    {
        $live = $this->currentLiveCheckpoint();
        if (! $live instanceof PublicationCheckpoint) {
            throw ValidationException::withMessages(['publication' => 'There is no live commit to revert.']);
        }

        $parent = $live->parent()->first();
        if (! $parent instanceof PublicationCheckpoint) {
            throw ValidationException::withMessages(['publication' => 'The first published version has no parent to revert to.']);
        }

        $changed = DB::transaction(function () use ($live, $parent, $actor): int {
            DB::select('SELECT pg_advisory_xact_lock(?)', [PublicationSnapshot::LOCK_KEY]);
            $this->schemaGuard->assertParity();
            $this->assertRestorable($parent);
            $changed = $this->pendingCountAgainstCheckpoint($parent);

            $this->mediaCleanup->queueAbandonedWorkingMedia();
            $this->replaceWorkingFromCheckpoint($parent);
            $this->eventStates->clearUncheckpointedPendingStates();
            $this->setWorkingContext('revert', (int) $live->getKey());
            $this->audit->record(
                $actor,
                'publication.commit_revert_staged',
                'publication_checkpoint',
                (int) $live->getKey(),
                ['source_publication_checkpoint_id' => (int) $parent->getKey()],
            );

            return $changed;
        }, attempts: 1);

        $this->mediaCleanup->drain();

        return [
            'changed' => $changed,
            'checkpoint' => $parent->refresh(),
            'reverted' => $live->refresh(),
        ];
    }

    public function isRestorable(PublicationCheckpoint $checkpoint): bool
    {
        if (! (bool) $checkpoint->getAttribute('snapshot_available')) {
            return false;
        }

        $schemaHash = $checkpoint->getAttribute('schema_hash');

        return is_string($schemaHash) && hash_equals($schemaHash, $this->schemaHash());
    }

    public function currentLiveCheckpoint(): ?PublicationCheckpoint
    {
        /** @var PublicationCheckpoint|null $checkpoint */
        $checkpoint = PublicationCheckpoint::query()
            ->orderByDesc('published_at')
            ->orderByDesc('id')
            ->first();

        return $checkpoint;
    }

    public function workingContext(): array
    {
        $context = DB::table('publication_working_context')->where('id', 1)->first();
        $operation = is_string($context->operation ?? null) ? (string) $context->operation : null;
        $sourceId = is_numeric($context->source_publication_checkpoint_id ?? null)
            ? (int) $context->source_publication_checkpoint_id
            : null;

        return [
            'operation' => in_array($operation, ['restore', 'revert'], true) ? $operation : null,
            'source_publication_checkpoint_id' => $sourceId,
        ];
    }

    public function clearWorkingContext(): void
    {
        DB::table('publication_working_context')->where('id', 1)->update([
            'operation' => null,
            'source_publication_checkpoint_id' => null,
            'updated_at' => now(),
        ]);
    }

    public function schemaHash(): string
    {
        $rows = DB::table('information_schema.columns')
            ->where('table_schema', 'public')
            ->whereIn('table_name', PublicationSnapshot::TABLES)
            ->orderBy('table_name')
            ->orderBy('ordinal_position')
            ->get([
                'table_name',
                'column_name',
                'ordinal_position',
                'data_type',
                'udt_name',
                'is_nullable',
                'character_maximum_length',
                'numeric_precision',
                'numeric_scale',
                'datetime_precision',
                'is_identity',
                'identity_generation',
                'is_generated',
            ]);

        return hash('sha256', json_encode(
            $rows->map(static fn (object $row): array => (array) $row)->all(),
            JSON_THROW_ON_ERROR,
        ));
    }

    public function snapshotHash(string $schema): string
    {
        if (! in_array($schema, ['public', 'committed'], true)) {
            throw new \InvalidArgumentException('Unsupported publication snapshot schema.');
        }

        $context = hash_init('sha256');

        foreach (PublicationSnapshot::TABLES as $table) {
            hash_update($context, "table:{$table}\n");
            $rows = DB::select("SELECT id::text AS row_key, to_jsonb(source)::text AS payload FROM {$schema}.{$table} AS source ORDER BY id");

            foreach ($rows as $row) {
                hash_update($context, (string) $row->row_key."\n".(string) $row->payload."\n");
            }
        }

        return hash_final($context);
    }

    private function assertRestorable(PublicationCheckpoint $checkpoint): void
    {
        if (! (bool) $checkpoint->getAttribute('snapshot_available')) {
            throw ValidationException::withMessages([
                'publication' => 'This legacy commit predates restorable snapshots and can only be inspected.',
            ]);
        }

        $schemaHash = $checkpoint->getAttribute('schema_hash');
        if (! is_string($schemaHash) || ! hash_equals($schemaHash, $this->schemaHash())) {
            throw ValidationException::withMessages([
                'publication' => 'This commit snapshot was created against a different publication schema and cannot be restored safely.',
            ]);
        }
    }

    private function replaceWorkingFromCheckpoint(PublicationCheckpoint $checkpoint): void
    {
        $this->truncateWorkingTables();

        foreach (PublicationSnapshot::TABLES as $table) {
            DB::statement(
                "INSERT INTO public.{$table} SELECT (jsonb_populate_record(NULL::public.{$table}, version_row.payload)).* FROM publication_version_rows AS version_row WHERE version_row.publication_checkpoint_id = ? AND version_row.table_name = ? ORDER BY version_row.id",
                [(int) $checkpoint->getKey(), $table],
            );
        }
    }

    private function replaceWorkingFromSchema(string $schema): void
    {
        if ($schema !== 'committed') {
            throw new \InvalidArgumentException('Unsupported publication source schema.');
        }

        $this->truncateWorkingTables();

        foreach (PublicationSnapshot::TABLES as $table) {
            DB::statement("INSERT INTO public.{$table} SELECT * FROM {$schema}.{$table}");
        }
    }

    private function truncateWorkingTables(): void
    {
        DB::statement(
            'TRUNCATE TABLE '.implode(', ', array_map(
                static fn (string $table): string => 'public.'.$table,
                PublicationSnapshot::TABLES,
            )),
        );
    }

    private function pendingCount(): int
    {
        $total = 0;
        foreach (PublicationSnapshot::TABLES as $table) {
            $row = DB::selectOne(sprintf(
                'SELECT COUNT(*)::int AS aggregate FROM public.%1$s AS working FULL OUTER JOIN committed.%1$s AS committed USING (id) WHERE '.PublicationSnapshot::ROW_DIFFERENCE_SQL,
                $table,
            ));
            $total += (int) ($row->aggregate ?? 0);
        }

        return $total;
    }

    private function pendingCountAgainstCheckpoint(PublicationCheckpoint $checkpoint): int
    {
        $total = 0;

        foreach (PublicationSnapshot::TABLES as $table) {
            $row = DB::selectOne(
                "WITH target AS (SELECT (jsonb_populate_record(NULL::public.{$table}, version_row.payload)).* FROM publication_version_rows AS version_row WHERE version_row.publication_checkpoint_id = ? AND version_row.table_name = ?) SELECT COUNT(*)::int AS aggregate FROM public.{$table} AS working FULL OUTER JOIN target AS committed USING (id) WHERE ".PublicationSnapshot::ROW_DIFFERENCE_SQL,
                [(int) $checkpoint->getKey(), $table],
            );
            $total += (int) ($row->aggregate ?? 0);
        }

        return $total;
    }

    private function setWorkingContext(string $operation, int $sourceCheckpointId): void
    {
        DB::table('publication_working_context')->where('id', 1)->update([
            'operation' => $operation,
            'source_publication_checkpoint_id' => $sourceCheckpointId,
            'updated_at' => now(),
        ]);
    }
}
