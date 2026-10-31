<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('site_sections', function (Blueprint $table): void {
            $table->text('meta_description')->nullable();
        });

        // Preserve every previously authored Gallery Search description in the
        // page-level owner before the Gallery settings field is retired.
        DB::statement(<<<'SQL'
            UPDATE site_sections AS section
            SET meta_description = gallery.description
            FROM artwork_categories AS gallery
            WHERE section.artwork_category_id = gallery.id
              AND section.type = 'gallery'
              AND gallery.description IS NOT NULL
              AND btrim(gallery.description) <> ''
            SQL);
        // Keep the legacy column solely for historical snapshot compatibility.
        // Production reads and edits now use site_sections.meta_description only.
    }

    public function down(): void
    {
        DB::statement(<<<'SQL'
            UPDATE artwork_categories AS gallery
            SET description = section.meta_description
            FROM site_sections AS section
            WHERE section.artwork_category_id = gallery.id
              AND section.type = 'gallery'
              AND section.meta_description IS NOT NULL
            SQL);

        Schema::table('site_sections', function (Blueprint $table): void {
            $table->dropColumn('meta_description');
        });
    }
};
