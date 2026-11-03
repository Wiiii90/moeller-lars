<?php

use App\Domain\Content\ExhibitionDate;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Source strings are checked before DDL, and all changes are transactional.
     * Validation has different artist data: any unknown or contradictory date
     * aborts the entire migration, never partially changing artist records.
     */
    public function up(): void
    {
        if (DB::getDriverName() !== 'pgsql') {
            throw new RuntimeException('Exhibition date conversion requires PostgreSQL.');
        }

        DB::transaction(function (): void {
            // Lock before inventorying artist values. Otherwise a concurrent
            // Admin edit could arrive between our preflight and the update
            // and be overwritten by an older normalized date. The checkpoint
            // table must also stay unchanged until the scan and DDL finish.
            DB::statement('LOCK TABLE public.exhibitions, committed.exhibitions, publication_version_rows IN ACCESS EXCLUSIVE MODE');

            $sources = ['public.exhibitions', 'committed.exhibitions'];
            $planned = [];
            foreach ($sources as $table) {
                if (DB::selectOne('SELECT to_regclass(?) AS relation_name', [$table])?->relation_name === null) {
                    throw new RuntimeException('Missing publication table: '.$table);
                }

                $rows = DB::table($table)->orderBy('id')->get([
                    'id', 'title', 'state', 'starts_on', 'ends_on', 'date_text',
                ]);
                foreach ($rows as $record) {
                    try {
                        $normalized = ExhibitionDate::fromLegacy(
                            $record->starts_on,
                            $record->ends_on,
                            $record->date_text,
                        );
                    } catch (InvalidArgumentException $exception) {
                        throw new RuntimeException(
                            "{$table} exhibition #{$record->id} ({$record->title}) has an unconvertible artist date: ".
                            $exception->getMessage(),
                            previous: $exception,
                        );
                    }
                    if ($record->state === 'published' && $normalized['starts_on'] === null) {
                        throw new RuntimeException("{$table} exhibition #{$record->id} is published but has no recoverable date.");
                    }
                    $planned[$table][(int) $record->id] = $normalized;
                }
            }

            // Immutable publication checkpoints are never edited. Their
            // legacy payloads must still be legible to the historical
            // projection/restore owner after the active schema cutover.
            $history = DB::table('publication_version_rows')
                ->where('table_name', 'exhibitions')
                ->orderBy('publication_checkpoint_id')
                ->orderBy('row_key')
                ->get(['publication_checkpoint_id', 'row_key', 'payload']);
            foreach ($history as $row) {
                $payload = is_array($row->payload)
                    ? $row->payload
                    : json_decode((string) $row->payload, true, 512, JSON_THROW_ON_ERROR);
                if (! is_array($payload)
                    || ! array_key_exists('starts_on', $payload)
                    || ! array_key_exists('ends_on', $payload)
                    || ! array_key_exists('date_text', $payload)) {
                    throw new RuntimeException("Publication checkpoint #{$row->publication_checkpoint_id} has an unsupported exhibition date shape.");
                }
                try {
                    $normalized = ExhibitionDate::fromLegacy(
                        $payload['starts_on'],
                        $payload['ends_on'],
                        $payload['date_text'],
                    );
                } catch (InvalidArgumentException $exception) {
                    throw new RuntimeException(
                        "Publication checkpoint #{$row->publication_checkpoint_id}, exhibition #{$row->row_key} contains an unconvertible date: ".
                        $exception->getMessage(),
                        previous: $exception,
                    );
                }
                if (($payload['state'] ?? null) === 'published' && $normalized['starts_on'] === null) {
                    throw new RuntimeException("Publication checkpoint #{$row->publication_checkpoint_id}, exhibition #{$row->row_key} would lose its date.");
                }
            }

            foreach ($sources as $table) {
                DB::statement("ALTER TABLE {$table} DROP CONSTRAINT IF EXISTS exhibitions_dates_check");
                foreach (['starts_on', 'ends_on'] as $field) {
                    DB::statement("ALTER TABLE {$table} ALTER COLUMN {$field} TYPE VARCHAR(10)
                        USING CASE WHEN {$field} IS NULL THEN NULL ELSE to_char({$field}, 'YYYY-MM-DD') END");
                }
                foreach ($planned[$table] as $id => $dates) {
                    DB::table($table)->where('id', $id)->update($dates);
                }
                DB::statement("ALTER TABLE {$table} DROP COLUMN date_text");
                DB::statement("ALTER TABLE {$table} ADD CONSTRAINT exhibitions_partial_dates_check
                    CHECK (
                        (starts_on IS NULL OR starts_on ~ '^[1-9][0-9]{3}(-[0-9]{2}(-[0-9]{2})?)?$')
                        AND (ends_on IS NULL OR ends_on ~ '^[1-9][0-9]{3}(-[0-9]{2}(-[0-9]{2})?)?$')
                        AND (ends_on IS NULL OR starts_on IS NOT NULL)
                    )");
            }
        });
    }

    public function down(): void
    {
        // Partial precision cannot safely be represented as SQL DATE. In
        // particular, an authored year/month must not become a fake first day.
        throw new RuntimeException('Exhibition partial dates cannot be rolled back without losing artist precision.');
    }
};
