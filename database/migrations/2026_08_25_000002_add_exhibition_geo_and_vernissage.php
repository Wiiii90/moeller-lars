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
            $table->timestampTz('vernissage_at')->nullable();
            $table->decimal('latitude', 10, 7)->nullable();
            $table->decimal('longitude', 10, 7)->nullable();
            $table->timestampTz('geocoded_at')->nullable();
        });

        DB::statement(<<<'SQL'
            UPDATE exhibitions
            SET location_text = concat_ws(', ', NULLIF(btrim(city), ''), NULLIF(btrim(country), '')),
                updated_at = NOW()
            WHERE (location_text IS NULL OR btrim(location_text) = '')
              AND (NULLIF(btrim(city), '') IS NOT NULL OR NULLIF(btrim(country), '') IS NOT NULL)
        SQL);

        DB::statement('ALTER TABLE exhibitions DROP CONSTRAINT IF EXISTS exhibitions_published_content_check');
        DB::statement("ALTER TABLE exhibitions ADD CONSTRAINT exhibitions_published_content_check CHECK (state <> 'published' OR (btrim(title) <> '' AND ((date_text IS NOT NULL AND btrim(date_text) <> '') OR starts_on IS NOT NULL)))");
        DB::statement("ALTER TABLE exhibitions ADD CONSTRAINT exhibitions_coordinates_check CHECK ((latitude IS NULL AND longitude IS NULL) OR (latitude BETWEEN -90 AND 90 AND longitude BETWEEN -180 AND 180))");
        DB::statement("ALTER TABLE exhibitions ADD CONSTRAINT exhibitions_coordinate_pair_check CHECK ((latitude IS NULL) = (longitude IS NULL))");
    }

    public function down(): void
    {
        // Forward-only: do not destroy normalized artist geodata or vernissage timestamps.
    }
};
