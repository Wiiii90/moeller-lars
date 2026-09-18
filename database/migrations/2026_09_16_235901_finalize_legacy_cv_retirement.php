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
            if (! $this->tableExists($schema, 'custom_page_settings') || ! $this->tableExists($schema, 'cv_entries')) {
                continue;
            }

            $items = collect(DB::select(
                "SELECT state, year_text, title, organisation, location, external_url, body
                 FROM {$schema}.cv_entries
                 WHERE state <> 'archived'
                 ORDER BY position, id",
            ))
                ->map(static function (object $entry): array {
                    return [
                        'published' => ($entry->state ?? null) === 'published',
                        'date' => self::nullableString($entry->year_text ?? null),
                        'title' => self::nullableString($entry->title ?? null),
                        'meta' => self::nullableString($entry->organisation ?? null),
                        'location' => self::nullableString($entry->location ?? null),
                        'url' => self::nullableString($entry->external_url ?? null),
                        'body' => $entry->body ?? null,
                    ];
                })
                ->filter(static fn (array $item): bool => $item['title'] !== null)
                ->values()
                ->all();

            $rows = DB::select("SELECT id, blocks FROM {$schema}.custom_page_settings ORDER BY id");
            foreach ($rows as $row) {
                $blocks = $this->decodeJson($row->blocks ?? null);
                if (! is_array($blocks) || ! array_is_list($blocks)) {
                    continue;
                }

                $changed = false;
                foreach ($blocks as $index => $block) {
                    if (! is_array($block) || ($block['type'] ?? null) !== 'cv_list') {
                        continue;
                    }

                    $blocks[$index] = [
                        'type' => 'list',
                        'published' => ($block['published'] ?? true) === true,
                        'title' => null,
                        'media_asset_id' => is_numeric($block['media_asset_id'] ?? null)
                            ? (int) $block['media_asset_id']
                            : null,
                        'items' => $items,
                    ];
                    $changed = true;
                }

                if (! $changed) {
                    continue;
                }

                DB::statement(
                    "UPDATE {$schema}.custom_page_settings SET blocks = ?::jsonb WHERE id = ?",
                    [json_encode($blocks, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE), (int) $row->id],
                );
            }
        }

        DB::statement('DROP TABLE IF EXISTS committed.cv_entries');
        DB::statement('DROP TABLE IF EXISTS public.cv_entries');
    }

    public function down(): void
    {
        // CV rows are now canonical Custom Page list items. Recreating the retired
        // table would reintroduce a second source of truth and is intentionally omitted.
    }

    private function tableExists(string $schema, string $table): bool
    {
        return DB::selectOne('SELECT to_regclass(?) AS name', ["{$schema}.{$table}"])?->name !== null;
    }

    private function decodeJson(mixed $value): mixed
    {
        if (is_array($value)) {
            return $value;
        }

        if (! is_string($value)) {
            return $value;
        }

        return json_decode($value, true, 512, JSON_THROW_ON_ERROR);
    }

    private static function nullableString(mixed $value): ?string
    {
        if (! is_string($value)) {
            return null;
        }

        $value = trim($value);

        return $value === '' ? null : $value;
    }
};
