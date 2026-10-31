<?php

namespace App\Domain\Publication;

use App\Domain\Admin\AdminAuditService;
use App\Domain\Content\HistoricalCustomPageBlockCanonicalizer;
use App\Domain\Media\MediaThumbnailCutoverService;
use App\Models\PublicationCheckpoint;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final class PublicationVersionService
{
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

    public function reconcilePreReleaseSnapshotDefaults(bool $validationOffline = false): void
    {
        // Reuse the thumbnail cutover's exact restricted Validation identity
        // check. The maintenance flag is only injected into the disposable
        // offline container; the normal production runtime remains forbidden.
        $authorized = $validationOffline
            ? app(MediaThumbnailCutoverService::class)->authorized(true)
            : app()->environment(['local', 'testing']);

        if (! $authorized || DB::connection()->getDriverName() !== 'pgsql') {
            throw new \RuntimeException('Historical snapshot alignment requires local/testing or authorized offline Validation.');
        }

        // Reproduce the exact editorial data migrations from 2026-10-20.
        // Only pre-migration history qualifies; newly authored exhibition
        // values and checkpoints created after those migrations stay intact.
        // Legacy Vita imported the organization name into "venue". Migration
        // 2026_10_20_010000 moved it to "organizer" and cleared "venue".
        DB::update(<<<'SQL'
            UPDATE publication_version_row_manifests AS manifest
            SET payload_id = resolve_history_payload(
                stored.payload || jsonb_build_object(
                    'organizer', stored.payload->'venue',
                    'venue', NULL::text
                )
            )
            FROM history_payloads AS stored, publication_checkpoints AS checkpoint
            WHERE manifest.payload_id = stored.id
              AND manifest.publication_checkpoint_id = checkpoint.id
              AND checkpoint.snapshot_available = true
              AND manifest.table_name = 'exhibitions'
              AND stored.payload->>'legacy_source' = 'legacy-public-vita'
              AND NOT jsonb_exists(stored.payload, 'organizer')
              AND NULLIF(btrim(stored.payload->>'venue'), '') IS NOT NULL
            SQL);

        // Migration 2026_10_20_180000 reconstructed a documented two-venue
        // address for one unedited legacy exhibition. Replay only that exact
        // source identity and its original null/city-only field values.
        DB::update(<<<'SQL'
            UPDATE publication_version_row_manifests AS manifest
            SET payload_id = resolve_history_payload(
                stored.payload || jsonb_build_object(
                    'location_text', 'Hans Albers Eck, Hans-Albers-Platz 20 & Hans-Albers-Klause, Friedrichstraße 19.',
                    'city', 'Hamburg'
                )
            )
            FROM history_payloads AS stored, publication_checkpoints AS checkpoint
            WHERE manifest.payload_id = stored.id
              AND manifest.publication_checkpoint_id = checkpoint.id
              AND checkpoint.snapshot_available = true
              AND manifest.table_name = 'exhibitions'
              AND stored.payload->>'legacy_source' = 'legacy-public-vita'
              AND stored.payload->>'title' = 'Der blonde Hans'
              AND stored.payload->>'description' = 'Hans Albers Eck, Hans-Albers-Platz 20 & Hans-Albers-Klause, Friedrichstraße 19.'
              AND (stored.payload->>'location_text' IS NULL OR stored.payload->>'location_text' = 'Hamburg')
              AND (stored.payload->>'city' IS NULL OR stored.payload->>'city' = 'Hamburg')
              AND stored.payload->>'map_enabled' = 'false'
            SQL);

        // PostgreSQL migrations update Working and Committed columns, but
        // restorable historical payloads remain immutable old-schema rows.
        // Backfill only absent keys using the actual schema's static defaults.
        // Never overwrite an existing historical value with current content.
        foreach (PublicationSnapshot::TABLES as $table) {
            $columns = DB::select(
                "SELECT a.attname AS column_name,
                        CASE WHEN a.attnotnull THEN 1 ELSE 0 END AS is_required,
                        pg_get_expr(d.adbin, d.adrelid) AS default_expression
                 FROM pg_catalog.pg_attribute AS a
                 JOIN pg_catalog.pg_class AS c ON c.oid = a.attrelid
                 JOIN pg_catalog.pg_namespace AS n ON n.oid = c.relnamespace
                 LEFT JOIN pg_catalog.pg_attrdef AS d
                    ON d.adrelid = a.attrelid AND d.adnum = a.attnum
                 WHERE n.nspname = 'committed' AND c.relname = ?
                   AND a.attnum > 0 AND NOT a.attisdropped
                 ORDER BY a.attnum",
                [$table],
            );

            foreach ($columns as $column) {
                $name = (string) $column->column_name;
                if (preg_match('/\\A[a-z_][a-z0-9_]*\\z/D', $name) !== 1) {
                    throw new \RuntimeException('Unsupported historical column identity.');
                }

                $missing = DB::table('publication_version_row_manifests as manifest')
                    ->join('history_payloads as stored', 'stored.id', '=', 'manifest.payload_id')
                    ->join('publication_checkpoints as checkpoint', 'checkpoint.id', '=', 'manifest.publication_checkpoint_id')
                    ->where('checkpoint.snapshot_available', true)
                    ->where('manifest.table_name', $table)
                    ->whereRaw('NOT jsonb_exists(stored.payload, ?)', [$name])
                    ->exists();

                if (! $missing) {
                    continue;
                }

                // The number-format migration derives its backfill from the
                // then-current regional format, not its schema default.
                if ($table === 'exhibitions' && $name === 'show_vernissage') {
                    // 2026_10_20_010000 also enabled the opening flag for
                    // legacy events with a date or non-empty opening text.
                    $fallback = "CASE WHEN stored.payload->>'vernissage_at' IS NOT NULL "
                        ."OR NULLIF(btrim(stored.payload->>'opening_text'), '') IS NOT NULL "
                        ."THEN true ELSE false END";
                } elseif ($table === 'public_content_settings' && $name === 'number_format') {
                    $fallback = "CASE WHEN COALESCE(stored.payload->>'regional_format', 'de-DE') = 'de-DE' THEN 'de-DE' ELSE 'en-US' END";
                } elseif ($table === 'public_content_settings' && $name === 'header_divider_mode') {
                    // Historic joined/split headers were mapped to divider modes.
                    $fallback = "CASE WHEN stored.payload->>'header_content_joined' = 'false' THEN 'separate' ELSE 'fine' END";
                } elseif (is_string($column->default_expression)
                    && preg_match("/\\A(?:true|false|-?[0-9]+(?:\\.[0-9]+)?|'(?:[^']|'')*'(?:::[a-z_][a-z_ ]*)?)\\z/iD", $column->default_expression) === 1) {
                    $fallback = $column->default_expression;
                } elseif (! (bool) $column->is_required && $column->default_expression === null) {
                    $fallback = 'NULL::text';
                } else {
                    throw new \RuntimeException("Cannot safely reconstruct historical default for {$table}.{$name}.");
                }

                DB::update(
                    'UPDATE publication_version_row_manifests AS manifest '
                    .'SET payload_id = resolve_history_payload(stored.payload || jsonb_build_object(?::text, to_jsonb('.$fallback.'))) '
                    .'FROM history_payloads AS stored, publication_checkpoints AS checkpoint '
                    .'WHERE manifest.payload_id = stored.id '
                    .'AND manifest.publication_checkpoint_id = checkpoint.id '
                    .'AND checkpoint.snapshot_available = true '
                    .'AND manifest.table_name = ? '
                    .'AND NOT jsonb_exists(stored.payload, ?)',
                    [$name, $table, $name],
                );
            }
        }
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

        foreach (PublicationSnapshot::RESTORE_TABLES as $table) {
            DB::statement(
                "INSERT INTO public.{$table} SELECT (jsonb_populate_record(NULL::public.{$table}, version_row.payload)).* FROM publication_version_rows AS version_row WHERE version_row.publication_checkpoint_id = ? AND version_row.table_name = ? ORDER BY version_row.id",
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
            $row = DB::selectOne(
                "WITH target AS (SELECT (jsonb_populate_record(NULL::public.{$table}, version_row.payload)).* FROM publication_version_rows AS version_row WHERE version_row.publication_checkpoint_id = ? AND version_row.table_name = ?) SELECT COUNT(*)::int AS aggregate FROM public.{$table} AS working FULL OUTER JOIN target AS committed USING (id) WHERE ".PublicationSnapshot::ROW_DIFFERENCE_SQL,
                [(int) $checkpoint->getKey(), $table],
            );
            $total += (int) ($row->aggregate ?? 0);
        }

        return $total + $this->rightsSelections->pendingCount($checkpoint);
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
