<?php

use App\Domain\Publication\PublicationSnapshot;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use RuntimeException;

return new class extends Migration
{
    public function up(): void
    {
        DB::transaction(function (): void {
            DB::select('SELECT pg_advisory_xact_lock(?)', [PublicationSnapshot::LOCK_KEY]);
            foreach (['public', 'committed'] as $schema) {
                $invalid = (int) DB::selectOne("
                    SELECT COUNT(*) AS aggregate FROM {$schema}.artworks WHERE
                        (work_date IS NOT NULL AND work_year IS NOT NULL AND work_year <> EXTRACT(YEAR FROM work_date))
                        OR (date_precision IN ('day', 'month') AND work_date IS NULL)
                        OR (date_precision = 'month' AND EXTRACT(DAY FROM work_date) <> 1)
                        OR (date_precision = 'year' AND (work_date IS NOT NULL OR work_year IS NULL))
                        OR (date_precision = 'unknown' AND work_date IS NOT NULL)
                ")->aggregate;
                if ($invalid > 0) {
                    throw new RuntimeException("Artwork date cutover blocked: {$invalid} ambiguous rows in {$schema}.artworks.");
                }
            }
            foreach (['public', 'committed'] as $schema) {
                DB::statement("ALTER TABLE {$schema}.artworks
                    ADD COLUMN year SMALLINT NULL, ADD COLUMN month SMALLINT NULL, ADD COLUMN day SMALLINT NULL");
                DB::statement("UPDATE {$schema}.artworks SET
                    year = COALESCE(work_year, EXTRACT(YEAR FROM work_date)::int),
                    month = CASE WHEN date_precision IN ('month', 'day') THEN EXTRACT(MONTH FROM work_date)::int ELSE NULL END,
                    day = CASE WHEN date_precision = 'day' THEN EXTRACT(DAY FROM work_date)::int ELSE NULL END");
                DB::statement("ALTER TABLE {$schema}.artworks DROP CONSTRAINT IF EXISTS artworks_date_precision_check,
                    DROP COLUMN work_year, DROP COLUMN work_date, DROP COLUMN date_precision");
                DB::statement("ALTER TABLE {$schema}.artworks ADD CONSTRAINT artworks_date_parts_check CHECK (
                    (year IS NULL OR year BETWEEN 1000 AND 9999)
                    AND (month IS NULL OR (year IS NOT NULL AND month BETWEEN 1 AND 12))
                    AND (day IS NULL OR CASE
                        WHEN year BETWEEN 1000 AND 9999 AND month BETWEEN 1 AND 12
                        THEN day BETWEEN 1 AND EXTRACT(DAY FROM (make_date(year::int, month::int, 1) + INTERVAL '1 month - 1 day'))
                        ELSE FALSE END)
                )");
                DB::statement("CREATE INDEX artworks_state_year_index ON {$schema}.artworks (state, year)");
                DB::statement("CREATE INDEX artworks_gallery_date_index ON {$schema}.artworks (artwork_category_id, state, year, month, day, position)");
            }
        });
    }

    public function down(): void
    {
        DB::transaction(function (): void {
            DB::select('SELECT pg_advisory_xact_lock(?)', [PublicationSnapshot::LOCK_KEY]);
            foreach (['public', 'committed'] as $schema) {
                DB::statement("DROP INDEX IF EXISTS {$schema}.artworks_state_year_index");
                DB::statement("DROP INDEX IF EXISTS {$schema}.artworks_gallery_date_index");
                DB::statement("ALTER TABLE {$schema}.artworks DROP CONSTRAINT IF EXISTS artworks_date_parts_check,
                    ADD COLUMN work_year SMALLINT NULL, ADD COLUMN work_date DATE NULL,
                    ADD COLUMN date_precision VARCHAR(16) NOT NULL DEFAULT 'unknown'");
                DB::statement("UPDATE {$schema}.artworks SET
                    work_year = year,
                    work_date = CASE WHEN month IS NOT NULL THEN make_date(year::int, month::int, COALESCE(day,1)::int) ELSE NULL END,
                    date_precision = CASE WHEN day IS NOT NULL THEN 'day' WHEN month IS NOT NULL THEN 'month'
                        WHEN year IS NOT NULL THEN 'year' ELSE 'unknown' END");
                DB::statement("ALTER TABLE {$schema}.artworks DROP COLUMN year, DROP COLUMN month, DROP COLUMN day,
                    ADD CONSTRAINT artworks_date_precision_check CHECK (date_precision IN ('unknown', 'year', 'month', 'day'))");
                DB::statement("CREATE INDEX artworks_state_work_year_index ON {$schema}.artworks (state, work_year)");
                DB::statement("CREATE INDEX artworks_artwork_category_id_state_work_date_position_index ON {$schema}.artworks (artwork_category_id, state, work_date, position)");
                DB::statement("CREATE INDEX artworks_state_work_date_position_index ON {$schema}.artworks (state, work_date, position)");
            }
        });
    }
};
