<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // Current working/published settings must use a selectable position.
        // Immutable publication checkpoints remain unchanged; the domain
        // normalizer can still read their legacy value.
        foreach (['public', 'committed'] as $schema) {
            DB::table($schema.'.public_content_settings')
                ->where('header_name_position', 'outside')
                ->update(['header_name_position' => 'inside']);
        }
    }

    public function down(): void
    {
        // The removed value cannot be reconstructed after consolidation.
    }
};
