<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('exhibitions', function (Blueprint $table): void {
            $table->boolean('gallery_enabled')->default(false);
            $table->boolean('map_enabled')->default(false);
            $table->string('map_shape', 16)->default('wide');
        });

        DB::statement("ALTER TABLE exhibitions ADD CONSTRAINT exhibitions_map_shape_check CHECK (map_shape IN ('wide', 'square'))");

        DB::statement(<<<'SQL'
            UPDATE exhibitions
            SET archived_from_state = CASE
                WHEN published_at IS NOT NULL THEN 'published'
                ELSE 'draft'
            END
            WHERE state = 'archived'
              AND archived_from_state IS NULL
        SQL);

        DB::statement(<<<'SQL'
            UPDATE exhibitions
            SET location_text = NULL
            WHERE location_text IS NOT NULL
              AND trim(location_text) <> ''
              AND (
                    (city IS NOT NULL AND lower(trim(location_text)) = lower(trim(city)))
                 OR (country IS NOT NULL AND lower(trim(location_text)) = lower(trim(country)))
                 OR lower(trim(location_text)) = lower(trim(concat_ws(', ', nullif(trim(city), ''), nullif(trim(country), ''))))
                 OR lower(trim(location_text)) = lower(trim(concat_ws(',', nullif(trim(city), ''), nullif(trim(country), ''))))
              )
        SQL);

        DB::statement(<<<'SQL'
            UPDATE exhibitions AS e
            SET gallery_enabled = TRUE
            WHERE EXISTS (
                SELECT 1
                FROM journal_entry_media AS jem
                WHERE jem.exhibition_id = e.id
                  AND jem.role = 'gallery'
            )
        SQL);

        DB::statement(<<<'SQL'
            UPDATE exhibitions
            SET map_enabled = TRUE
            WHERE latitude IS NOT NULL
              AND longitude IS NOT NULL
              AND starts_on IS NOT NULL
              AND (
                    starts_on > CURRENT_DATE
                 OR (
                        starts_on <= CURRENT_DATE
                    AND (
                           (ends_on IS NOT NULL AND ends_on >= CURRENT_DATE)
                        OR (ends_on IS NULL AND starts_on = CURRENT_DATE)
                    )
                 )
              )
        SQL);
    }

    public function down(): void
    {
        DB::statement('ALTER TABLE exhibitions DROP CONSTRAINT IF EXISTS exhibitions_map_shape_check');

        Schema::table('exhibitions', function (Blueprint $table): void {
            $table->dropColumn(['gallery_enabled', 'map_enabled', 'map_shape']);
        });

        // Forward-only data corrections are intentionally not reversed.
    }
};
