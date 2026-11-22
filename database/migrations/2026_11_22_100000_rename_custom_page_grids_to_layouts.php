<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        if (DB::connection()->getDriverName() !== 'pgsql') {
            return;
        }

        foreach (['public', 'committed'] as $schema) {
            foreach (DB::select("SELECT id, blocks FROM {$schema}.custom_page_settings ORDER BY id") as $row) {
                $blocks = is_string($row->blocks)
                    ? json_decode($row->blocks, true, 512, JSON_THROW_ON_ERROR)
                    : $row->blocks;
                if (! is_array($blocks)) {
                    continue;
                }

                $changed = false;
                foreach ($blocks as &$block) {
                    if (is_array($block) && ($block['type'] ?? null) === 'grid') {
                        $block['type'] = 'layout';
                        $block['layout_mode'] = 'rows';
                        $changed = true;
                    }
                }
                unset($block);
                if (! $changed) {
                    continue;
                }

                DB::statement(
                    "UPDATE {$schema}.custom_page_settings SET blocks = ?::jsonb WHERE id = ?",
                    [json_encode($blocks, JSON_THROW_ON_ERROR), (int) $row->id],
                );
            }
        }
    }

    public function down(): void
    {
        // Preserve the artist's published layout and immutable publication history.
    }
};
