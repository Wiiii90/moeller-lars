<?php

use App\Domain\Content\HistoricalCustomPageBlockCanonicalizer;
use App\Domain\Media\MediaTypePolicy;
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
            $videoIds = array_fill_keys(
                DB::table($schema.'.media_assets')->whereIn('mime_type', MediaTypePolicy::VIDEO_MIME_TYPES)
                    ->pluck('id')->map(static fn ($id): int => (int) $id)->all(),
                true,
            );
            $rows = DB::select("SELECT id, blocks FROM {$schema}.custom_page_settings ORDER BY id");
            foreach ($rows as $row) {
                $blocks = is_string($row->blocks)
                    ? json_decode($row->blocks, true, 512, JSON_THROW_ON_ERROR)
                    : $row->blocks;
                $canonical = HistoricalCustomPageBlockCanonicalizer::canonicalize(
                    $blocks, static fn (int $id): bool => isset($videoIds[$id]),
                );
                if ($canonical === $blocks) {
                    continue;
                }
                DB::statement(
                    "UPDATE {$schema}.custom_page_settings SET blocks = ?::jsonb WHERE id = ?",
                    [json_encode($canonical, JSON_THROW_ON_ERROR), (int) $row->id],
                );
            }
        }
    }

    public function down(): void
    {
        // Former editor types are not recreated; publication history is retained.
    }
};
