<?php

namespace App\Domain\Publication;

use App\Domain\Admin\AdminAuditService;
use App\Domain\Content\HistoricalCustomPageBlockCanonicalizer;
use App\Models\PublicationCheckpoint;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final class PublicationVersionService
{

    /**
     * Translation applies only when reading immutable historical checkpoint
     * rows. Working and Committed never regain the retired Artwork columns.
     */
    private const HISTORICAL_ARTWORK_DATE_PAYLOAD = <<<'SQL'
((version_row.payload - 'work_year' - 'work_date' - 'date_precision') || jsonb_build_object(
    'year', COALESCE((version_row.payload->>'work_year')::smallint, EXTRACT(YEAR FROM (version_row.payload->>'work_date')::date)::smallint),
    'month', CASE WHEN version_row.payload->>'date_precision' IN ('month', 'day') THEN EXTRACT(MONTH FROM (version_row.payload->>'work_date')::date)::smallint ELSE NULL END,
    'day', CASE WHEN version_row.payload->>'date_precision' = 'day' THEN EXTRACT(DAY FROM (version_row.payload->>'work_date')::date)::smallint ELSE NULL END
))
SQL;

    /** @var array<int, bool> */
    private array $compatibleArtworkCheckpoints = [];


    public function __construct(
        private readonly AdminAuditService $audit,
        private readonly PublicationMediaCleanupService $mediaCleanup,
        private readonly PublicationSchemaGuard $schemaGuard,
        private readonly MediaRightsPublicationSelections $rightsSelections,
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
            // A caller may have loaded this model before waiting for reclaim to
            // release the publication lock. Eligibility belongs to the locked state.
            $checkpoint->refresh();
            $this->assertRestorable($checkpoint);
            $changed = $this->pendingCountAgainstCheckpoint($checkpoint);

            $this->mediaCleanup->queueAbandonedWorkingMedia();
            $this->replaceWorkingFromCheckpoint($checkpoint);
            $this->rightsSelections->restoreWorking($checkpoint);
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
                $this->rightsSelections->restoreWorking($this->currentLiveCheckpoint());
                $this->clearWorkingContext();

                return 0;
            }

            $this->mediaCleanup->queueAbandonedWorkingMedia();
            $this->replaceWorkingFromSchema('committed');
            $this->rightsSelections->restoreWorking($this->currentLiveCheckpoint());
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
        $result = DB::transaction(function () use ($actor): array {
            DB::select('SELECT pg_advisory_xact_lock(?)', [PublicationSnapshot::LOCK_KEY]);
            $this->schemaGuard->assertParity();
            $live = $this->currentLiveCheckpoint();
            if (! $live instanceof PublicationCheckpoint) {
                throw ValidationException::withMessages(['publication' => 'There is no live commit to revert.']);
            }

            $parent = $live->parent()->first();
            if (! $parent instanceof PublicationCheckpoint) {
                throw ValidationException::withMessages(['publication' => 'The first published version has no parent to revert to.']);
            }

            $this->assertRestorable($parent);
            $changed = $this->pendingCountAgainstCheckpoint($parent);

            $this->mediaCleanup->queueAbandonedWorkingMedia();
            $this->replaceWorkingFromCheckpoint($parent);
            $this->rightsSelections->restoreWorking($parent);
            $this->setWorkingContext('revert', (int) $live->getKey());
            $this->audit->record(
                $actor,
                'publication.commit_revert_staged',
                'publication_checkpoint',
                (int) $live->getKey(),
                ['source_publication_checkpoint_id' => (int) $parent->getKey()],
            );

            return ['changed' => $changed, 'checkpoint' => $parent, 'reverted' => $live];
        }, attempts: 1);

        $this->mediaCleanup->drain();

        return $result;
    }

    public function isRestorable(PublicationCheckpoint $checkpoint): bool
    {
        if (! (bool) $checkpoint->getAttribute('snapshot_available')) {
            return false;
        }

        $schemaHash = $checkpoint->getAttribute('schema_hash');

        return is_string($schemaHash) && (
            hash_equals($schemaHash, $this->schemaHash())
            || $this->isCompatibleArtworkDateCheckpoint($checkpoint)
        );
    }

    /**
     * Non-destructive verification of a checkpoint against today's schema.
     * Runs the same schema projection and full row-difference calculation used
     * by Stage Version, but does not truncate Working, restore file rights,
     * enqueue cleanup, or change publication history.
     *
     * @return array{checkpoint_id:int, historical_date_projection:bool, differences_from_working:int, committed_artwork_differences:?int}
     */
    public function verifyCheckpointReadOnly(PublicationCheckpoint $checkpoint): array
    {
        $this->schemaGuard->assertParity();
        $checkpoint->refresh();
        $this->assertRestorable($checkpoint);

        // A matching schema hash alone is not proof that its stored rows are
        // intact; the explicit audit also checks their original content hash.
        if (! $this->originalSnapshotHashValid($checkpoint)) {
            throw ValidationException::withMessages([
                'publication' => 'This checkpoint does not match its original snapshot checksum.',
            ]);
        }

        $committedArtworkDifferences = null;
        if ($this->currentLiveCheckpoint()?->getKey() === $checkpoint->getKey()) {
            // The current LIVE checkpoint must represent Committed exactly.
            // A nonzero result identifies a failed or incomplete historical
            // Artwork conversion without changing any database state.
            $payload = $this->versionPayloadExpression($checkpoint, 'artworks');
            $row = DB::selectOne(
                "WITH target AS (
                    SELECT (jsonb_populate_record(NULL::committed.artworks, {$payload})).*
                    FROM publication_version_rows AS version_row
                    WHERE version_row.publication_checkpoint_id = ? AND version_row.table_name = 'artworks'
                )
                SELECT COUNT(*)::int AS aggregate
                FROM committed.artworks AS working
                FULL OUTER JOIN target AS committed USING (id)
                WHERE ".PublicationSnapshot::ROW_DIFFERENCE_SQL,
                [(int) $checkpoint->getKey()],
            );
            $committedArtworkDifferences = (int) ($row->aggregate ?? -1);
        }

        return [
            'checkpoint_id' => (int) $checkpoint->getKey(),
            'historical_date_projection' => ! hash_equals(
                (string) $checkpoint->getAttribute('schema_hash'),
                $this->schemaHash(),
            ),
            'differences_from_working' => $this->pendingCountAgainstCheckpoint($checkpoint),
            'committed_artwork_differences' => $committedArtworkDifferences,
        ];
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
        if (is_string($schemaHash) && hash_equals($schemaHash, $this->schemaHash())) {
            return;
        }

        // A stale schema hash is not permission to rewrite immutable history.
        // Require an exact payload-shape match, valid historical dates and
        // original snapshot checksum before affecting Working.
        if (! $this->isCompatibleArtworkDateCheckpoint($checkpoint)
            || ! $this->historicalArtworkDatesValid((int) $checkpoint->getKey())
            || ! $this->originalSnapshotHashValid($checkpoint)) {
            throw ValidationException::withMessages([
                'publication' => 'Historical snapshot validation failed; the original version remains preserved but cannot be restored safely.',
            ]);
        }
    }

    private function replaceWorkingFromCheckpoint(PublicationCheckpoint $checkpoint): void
    {
        $this->truncateWorkingTables();

        foreach (PublicationSnapshot::RESTORE_TABLES as $table) {
            $payload = $this->versionPayloadExpression($checkpoint, $table);
            DB::statement(
                "INSERT INTO public.{$table} SELECT (jsonb_populate_record(NULL::public.{$table}, {$payload})).* FROM publication_version_rows AS version_row WHERE version_row.publication_checkpoint_id = ? AND version_row.table_name = ? ORDER BY version_row.id",
                [(int) $checkpoint->getKey(), $table],
            );
        }

        $this->canonicalizeRestoredCustomPageSettings();
    }

    private function canonicalizeRestoredCustomPageSettings(): void
    {
        $rows = DB::select('SELECT id, blocks FROM public.custom_page_settings ORDER BY id');

        foreach ($rows as $row) {
            $blocks = $row->blocks ?? null;
            if (is_string($blocks)) {
                $blocks = json_decode($blocks, true, 512, JSON_THROW_ON_ERROR);
            }

            $canonical = HistoricalCustomPageBlockCanonicalizer::canonicalize($blocks);
            if ($canonical === $blocks) {
                continue;
            }

            DB::statement(
                'UPDATE public.custom_page_settings SET blocks = ?::jsonb WHERE id = ?',
                [json_encode($canonical, JSON_THROW_ON_ERROR), (int) $row->id],
            );
        }
    }

    private function replaceWorkingFromSchema(string $schema): void
    {
        if ($schema !== 'committed') {
            throw new \InvalidArgumentException('Unsupported publication source schema.');
        }

        $this->truncateWorkingTables();

        foreach (PublicationSnapshot::RESTORE_TABLES as $table) {
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

        return $total + $this->rightsSelections->pendingCount($this->currentLiveCheckpoint());
    }

    private function pendingCountAgainstCheckpoint(PublicationCheckpoint $checkpoint): int
    {
        $total = 0;

        foreach (PublicationSnapshot::TABLES as $table) {
            $payload = $this->versionPayloadExpression($checkpoint, $table);
            $row = DB::selectOne(
                "WITH target AS (SELECT (jsonb_populate_record(NULL::public.{$table}, {$payload})).* FROM publication_version_rows AS version_row WHERE version_row.publication_checkpoint_id = ? AND version_row.table_name = ?) SELECT COUNT(*)::int AS aggregate FROM public.{$table} AS working FULL OUTER JOIN target AS committed USING (id) WHERE ".PublicationSnapshot::ROW_DIFFERENCE_SQL,
                [(int) $checkpoint->getKey(), $table],
            );
            $total += (int) ($row->aggregate ?? 0);
        }

        return $total + $this->rightsSelections->pendingCount($checkpoint);
    }


    /** A compatibility projection, not an alternative persistent Artwork model. */
    private function versionPayloadExpression(PublicationCheckpoint $checkpoint, string $table): string
    {
        $schemaHash = (string) $checkpoint->getAttribute('schema_hash');

        return $table === 'artworks' && ! hash_equals($schemaHash, $this->schemaHash())
            ? self::HISTORICAL_ARTWORK_DATE_PAYLOAD
            : 'version_row.payload';
    }

    /**
     * Admit only the one known date-only cutover. All historical rows must
     * have precisely the expected current column set, except that Artwork
     * rows have the three retired date columns in place of year/month/day.
     * Unknown changes in other tables are rejected rather than silently
     * turning missing values into NULL during jsonb_populate_record.
     */
    private function isCompatibleArtworkDateCheckpoint(PublicationCheckpoint $checkpoint): bool
    {
        $id = (int) $checkpoint->getKey();
        if (array_key_exists($id, $this->compatibleArtworkCheckpoints)) {
            return $this->compatibleArtworkCheckpoints[$id];
        }

        try {
            $hasOldArtwork = DB::table('publication_version_rows')
                ->where('publication_checkpoint_id', $id)
                ->where('table_name', 'artworks')
                ->whereRaw("jsonb_exists_all(payload, ARRAY['work_year', 'work_date', 'date_precision'])")
                ->exists();

            if (! $hasOldArtwork) {
                return $this->compatibleArtworkCheckpoints[$id] = false;
            }

            $columns = DB::table('information_schema.columns')
                ->where('table_schema', 'public')
                ->whereIn('table_name', PublicationSnapshot::TABLES)
                ->get(['table_name', 'column_name'])
                ->groupBy('table_name');

            $expected = [];
            foreach (PublicationSnapshot::TABLES as $table) {
                $current = $columns->get($table);
                if ($current === null) {
                    return $this->compatibleArtworkCheckpoints[$id] = false;
                }

                $names = $current->pluck('column_name')
                    ->map(static fn (mixed $name): string => (string) $name)
                    ->all();
                if ($table === 'artworks') {
                    $names = array_values(array_diff($names, ['year', 'month', 'day']));
                    array_push($names, 'work_year', 'work_date', 'date_precision');
                }
                sort($names, SORT_STRING);

                $literals = array_map(
                    static fn (string $name): string => "'".str_replace("'", "''", $name)."'",
                    $names,
                );
                $expected[] = "('{$table}', ARRAY[".implode(', ', $literals)."]::text[])";
            }

            $shape = DB::selectOne(
                "WITH expected(table_name, column_names) AS (VALUES ".implode(', ', $expected).")
                 SELECT COUNT(*)::int AS invalid
                 FROM publication_version_rows AS version_row
                 LEFT JOIN expected ON expected.table_name = version_row.table_name
                 WHERE version_row.publication_checkpoint_id = ?
                   AND (expected.table_name IS NULL
                        OR jsonb_typeof(version_row.payload) IS DISTINCT FROM 'object'
                        OR ARRAY(SELECT name FROM jsonb_object_keys(version_row.payload) AS names(name) ORDER BY name)
                           IS DISTINCT FROM expected.column_names)",
                [$id],
            );

            return $this->compatibleArtworkCheckpoints[$id] = (int) ($shape->invalid ?? -1) === 0;
        } catch (\Throwable) {
            return $this->compatibleArtworkCheckpoints[$id] = false;
        }
    }

    private function historicalArtworkDatesValid(int $checkpointId): bool
    {
        try {
            $row = DB::selectOne(<<<'SQL'
SELECT COUNT(*)::int AS invalid
FROM publication_version_rows
WHERE publication_checkpoint_id = ?
  AND table_name = 'artworks'
  AND (
      COALESCE(payload->>'date_precision', '') NOT IN ('unknown', 'year', 'month', 'day')
      OR ((payload->>'work_date') IS NOT NULL
          AND (payload->>'work_year') IS NOT NULL
          AND (payload->>'work_year')::integer <> EXTRACT(YEAR FROM (payload->>'work_date')::date))
      OR ((payload->>'date_precision') IN ('day', 'month') AND (payload->>'work_date') IS NULL)
      OR ((payload->>'date_precision') = 'month'
          AND EXTRACT(DAY FROM (payload->>'work_date')::date) <> 1)
      OR ((payload->>'date_precision') = 'year'
          AND ((payload->>'work_date') IS NOT NULL OR (payload->>'work_year') IS NULL))
      OR ((payload->>'date_precision') = 'unknown'
          AND (payload->>'work_date') IS NOT NULL)
  )
SQL, [$checkpointId]);

            return (int) ($row->invalid ?? -1) === 0;
        } catch (\Throwable) {
            return false;
        }
    }

    /** Verify immutable original rows against the hash captured before migration. */
    private function originalSnapshotHashValid(PublicationCheckpoint $checkpoint): bool
    {
        $expected = $checkpoint->getAttribute('snapshot_hash');
        if (! is_string($expected) || strlen($expected) !== 64) {
            return false;
        }

        try {
            $hash = hash_init('sha256');
            foreach (PublicationSnapshot::TABLES as $table) {
                hash_update($hash, "table:{$table}\n");
                foreach (DB::table('publication_version_rows')
                    ->where('publication_checkpoint_id', $checkpoint->getKey())
                    ->where('table_name', $table)
                    ->selectRaw('row_key, payload::text AS payload')
                    ->orderByRaw('row_key::bigint')
                    ->cursor() as $row) {
                    hash_update($hash, (string) $row->row_key."\n".(string) $row->payload."\n");
                }
            }

            return hash_equals($expected, hash_final($hash));
        } catch (\Throwable) {
            return false;
        }
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
