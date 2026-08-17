<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('public_content_settings', function (Blueprint $table): void {
            $table->string('exhibitions_navigation_label', 120)->default('Exhibitions');
            $table->integer('exhibitions_navigation_position')->default(4);
        });

        Schema::table('exhibitions', function (Blueprint $table): void {
            $table->string('location_text', 500)->nullable()->after('country');
        });

        DB::statement('ALTER TABLE public_content_settings DROP CONSTRAINT IF EXISTS public_content_settings_navigation_label_check');
        DB::statement("ALTER TABLE public_content_settings ADD CONSTRAINT public_content_settings_cv_navigation_label_check CHECK (NOT cv_enabled OR btrim(cv_navigation_label) <> '')");
        DB::statement("ALTER TABLE public_content_settings ADD CONSTRAINT public_content_settings_exhibitions_navigation_label_check CHECK (NOT exhibitions_enabled OR btrim(exhibitions_navigation_label) <> '')");
        DB::statement('ALTER TABLE public_content_settings ADD CONSTRAINT public_content_settings_exhibitions_navigation_position_check CHECK (exhibitions_navigation_position >= 0)');

        $legacyExhibitions = DB::table('cv_entries')
            ->where('legacy_source', 'legacy-public-vita')
            ->where('section', 'Exhibitions')
            ->orderBy('position')
            ->get();

        $nextPosition = ((int) DB::table('exhibitions')->max('position')) + (DB::table('exhibitions')->exists() ? 1 : 0);

        foreach ($legacyExhibitions as $row) {
            $legacyId = (int) $row->legacy_id;
            $alreadyMoved = DB::table('exhibitions')
                ->where('legacy_source', 'legacy-public-vita')
                ->where('legacy_id', $legacyId)
                ->exists();

            if (! $alreadyMoved) {
                $slugBase = Str::slug((string) $row->title);
                $slug = ($slugBase !== '' ? $slugBase : 'exhibition').'-legacy-'.$legacyId;

                DB::table('exhibitions')->insert([
                    'slug' => $slug,
                    'title' => $row->title,
                    'state' => $row->state,
                    'position' => $nextPosition++,
                    'kind' => null,
                    'venue' => $row->organisation,
                    'city' => null,
                    'country' => null,
                    'location_text' => $row->location,
                    'description' => $row->body,
                    'external_url' => $row->external_url,
                    'directions_url' => null,
                    'starts_on' => $row->starts_on,
                    'ends_on' => $row->ends_on,
                    'date_text' => $row->year_text,
                    'legacy_id' => $row->legacy_id,
                    'legacy_source' => $row->legacy_source,
                    'migration_batch_id' => $row->migration_batch_id,
                    'migrated_at' => $row->migrated_at,
                    'published_at' => $row->published_at,
                    'created_at' => $row->created_at,
                    'updated_at' => now(),
                ]);
            }
        }

        DB::table('cv_entries')
            ->where('legacy_source', 'legacy-public-vita')
            ->where('section', 'Exhibitions')
            ->delete();

        DB::table('public_content_settings')
            ->where('id', 1)
            ->update([
                'cv_navigation_label' => 'CV',
                'exhibitions_enabled' => $legacyExhibitions->isNotEmpty(),
                'exhibitions_navigation_label' => 'EXHIBITIONS',
                'exhibitions_navigation_position' => 4,
                'updated_at' => now(),
            ]);
    }

    public function down(): void
    {
        DB::statement('ALTER TABLE public_content_settings DROP CONSTRAINT IF EXISTS public_content_settings_exhibitions_navigation_position_check');
        DB::statement('ALTER TABLE public_content_settings DROP CONSTRAINT IF EXISTS public_content_settings_exhibitions_navigation_label_check');
        DB::statement('ALTER TABLE public_content_settings DROP CONSTRAINT IF EXISTS public_content_settings_cv_navigation_label_check');
        DB::statement("ALTER TABLE public_content_settings ADD CONSTRAINT public_content_settings_navigation_label_check CHECK ((NOT cv_enabled AND NOT exhibitions_enabled) OR btrim(cv_navigation_label) <> '')");

        Schema::table('exhibitions', function (Blueprint $table): void {
            $table->dropColumn('location_text');
        });

        Schema::table('public_content_settings', function (Blueprint $table): void {
            $table->dropColumn(['exhibitions_navigation_label', 'exhibitions_navigation_position']);
        });
    }
};
