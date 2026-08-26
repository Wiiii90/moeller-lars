<?php

use App\Domain\Content\RichTextMediaReference;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use RuntimeException;

return new class extends Migration
{
    private const LEGACY_INLINE_PATTERN = '/\[\[journal-image:([0-9a-f]{8}-[0-9a-f]{4}-[1-5][0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12})\]\]/i';

    public function up(): void
    {
        DB::transaction(function (): void {
            $this->convertEntries('blog_posts', 'body', 'blog_post_id');
            $this->convertEntries('exhibitions', 'description', 'exhibition_id');

            if (DB::table('journal_entry_media')->where('role', 'inline')->exists()) {
                throw new RuntimeException('Journal inline media migration left unresolved inline usage rows.');
            }
        });

        DB::statement('ALTER TABLE journal_entry_media DROP CONSTRAINT IF EXISTS journal_entry_media_embed_check');
        DB::statement('ALTER TABLE journal_entry_media DROP CONSTRAINT IF EXISTS journal_entry_media_embed_key_unique');
        DB::statement('ALTER TABLE journal_entry_media DROP CONSTRAINT IF EXISTS journal_entry_media_role_check');
        DB::statement("ALTER TABLE journal_entry_media ADD CONSTRAINT journal_entry_media_role_check CHECK (role IN ('cover', 'gallery'))");

        if (Schema::hasColumn('journal_entry_media', 'embed_key')) {
            Schema::table('journal_entry_media', function ($table): void {
                $table->dropColumn('embed_key');
            });
        }
    }

    public function down(): void
    {
        // Forward-only canonicalization. UUID embed keys cannot be reconstructed from canonical Markdown media references.
    }

    private function convertEntries(string $table, string $contentColumn, string $ownerColumn): void
    {
        DB::table($table)
            ->select(['id', $contentColumn])
            ->orderBy('id')
            ->chunkById(100, function ($entries) use ($table, $contentColumn, $ownerColumn): void {
                foreach ($entries as $entry) {
                    $source = is_string($entry->{$contentColumn} ?? null) ? $entry->{$contentColumn} : '';
                    $inline = DB::table('journal_entry_media')
                        ->where($ownerColumn, $entry->id)
                        ->where('role', 'inline')
                        ->orderBy('position')
                        ->orderBy('id')
                        ->get();

                    if ($inline->isEmpty() && preg_match(self::LEGACY_INLINE_PATTERN, $source) !== 1) {
                        continue;
                    }

                    $byKey = [];
                    foreach ($inline as $usage) {
                        $key = strtolower((string) ($usage->embed_key ?? ''));
                        if ($key === '' || isset($byKey[$key])) {
                            throw new RuntimeException('Journal inline media contains a missing or duplicate embed key.');
                        }

                        $mediaAssetId = (int) ($usage->media_asset_id ?? 0);
                        if ($mediaAssetId <= 0 || ! DB::table('media_assets')->where('id', $mediaAssetId)->exists()) {
                            throw new RuntimeException('Journal inline media points to a missing Media Asset.');
                        }

                        $byKey[$key] = $usage;
                    }

                    $seen = [];
                    $converted = preg_replace_callback(
                        self::LEGACY_INLINE_PATTERN,
                        function (array $matches) use ($byKey, &$seen): string {
                            $key = strtolower((string) ($matches[1] ?? ''));
                            $usage = $byKey[$key] ?? null;
                            if ($usage === null || isset($seen[$key])) {
                                throw new RuntimeException('Journal content contains an unresolved or duplicate inline image marker.');
                            }
                            $seen[$key] = true;

                            $mediaAssetId = (int) $usage->media_asset_id;
                            $override = is_string($usage->alt_text_override ?? null) && trim($usage->alt_text_override) !== ''
                                ? trim($usage->alt_text_override)
                                : null;

                            return RichTextMediaReference::markdown($mediaAssetId, $override);
                        },
                        $source,
                    );

                    if (! is_string($converted)) {
                        throw new RuntimeException('Journal inline media content could not be converted.');
                    }

                    foreach ($byKey as $key => $_usage) {
                        if (! isset($seen[$key])) {
                            throw new RuntimeException('Journal inline media contains an orphan usage row.');
                        }
                    }

                    if ($converted !== $source) {
                        DB::table($table)->where('id', $entry->id)->update([
                            $contentColumn => $converted === '' ? null : $converted,
                            'updated_at' => now(),
                        ]);
                    }

                    DB::table('journal_entry_media')
                        ->where($ownerColumn, $entry->id)
                        ->where('role', 'inline')
                        ->delete();
                }
            }, 'id');
    }
};
