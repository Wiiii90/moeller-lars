<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasColumn('exhibitions', 'gallery_presentation')) {
            return;
        }

        Schema::table('exhibitions', function (Blueprint $table): void {
            $table->string('gallery_presentation', 24)->default('grid');
        });
    }

    public function down(): void
    {
        // Forward-only repair: gallery_presentation belongs to the canonical Exhibition schema
        // introduced by 2026_08_26_000001_add_gallery_presentation_to_exhibitions.php.
    }
};
