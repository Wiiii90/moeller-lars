<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        foreach (['public', 'committed'] as $schema) {
            // Some installations may already have run the earlier default
            // migration. Preserve their currently visible Menu alignment
            // before removing the legacy automatic setting.
            DB::statement("UPDATE {$schema}.public_content_settings
                SET menu_alignment = CASE
                    WHEN COALESCE(header_name_visible, true)
                        AND COALESCE(header_name_position, 'inside') IN ('inside', 'outside')
                        AND COALESCE(header_name_alignment, 'left') <> 'right'
                    THEN 'right'
                    ELSE 'left'
                END
                WHERE menu_alignment = 'auto'");

            DB::statement("ALTER TABLE {$schema}.public_content_settings
                ALTER COLUMN menu_alignment SET DEFAULT 'right'");
        }
    }

    public function down(): void
    {
        // An automatic setting cannot be reconstructed once the artist
        // changes the explicit alignment. Never recreate that retired value.
    }
};
