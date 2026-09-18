<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('custom_page_settings') || ! Schema::hasTable('cv_entries')) {
            return;
        }

        $items = DB::table('cv_entries')
            ->where('state', '<>', 'archived')
            ->orderBy('position')
            ->orderBy('id')
            ->get()
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

        DB::table('custom_page_settings')
            ->select(['id', 'blocks'])
            ->orderBy('id')
            ->chunkById(100, function ($settings) use ($items): void {
                foreach ($settings as $setting) {
                    $blocks = json_decode((string) $setting->blocks, true);
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

                    DB::table('custom_page_settings')
                        ->where('id', $setting->id)
                        ->update([
                            'blocks' => json_encode($blocks, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE),
                            'updated_at' => now(),
                        ]);
                }
            });
    }

    public function down(): void
    {
        // The historical cv_entries table is deliberately retained as the rollback source.
        // Recreating the removed runtime component would reintroduce the architecture this
        // migration removes, so rollback is intentionally data-preserving and non-destructive.
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
