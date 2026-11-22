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
                if (! is_array($blocks) || ! array_is_list($blocks)) {
                    continue;
                }

                $next = [];
                foreach ($blocks as $block) {
                    if (! is_array($block) || array_key_exists('source_page_id', $block)) {
                        $next[] = $block;

                        continue;
                    }

                    $type = $block['type'] ?? null;
                    if (! in_array($type, ['gallery', 'hero_artworks', 'exhibitions', 'blog'], true)) {
                        $next[] = $block;

                        continue;
                    }

                    $pages = match ($type) {
                        'gallery' => $this->galleryPages($schema, $block),
                        'hero_artworks' => $this->heroPages($schema, $block),
                        'exhibitions' => $this->entryPages($schema, 'exhibitions', $block['exhibition_ids'] ?? []),
                        'blog' => $this->entryPages($schema, 'blog_posts', $block['post_ids'] ?? []),
                    };
                    $canonical = $block;
                    unset(
                        $canonical['gallery_id'],
                        $canonical['gallery_ids'],
                        $canonical['source_rule'],
                        $canonical['exhibition_ids'],
                        $canonical['post_ids'],
                    );
                    if ($pages === []) {
                        $next[] = [...$canonical, 'source_page_id' => null];

                        continue;
                    }
                    foreach ($pages as $position => $pageId) {
                        $next[] = [
                            ...$canonical,
                            'source_page_id' => $pageId,
                            ...($position > 0 ? ['layout_divider_before' => 'none'] : []),
                        ];
                    }
                }

                if ($next !== $blocks) {
                    DB::statement(
                        "UPDATE {$schema}.custom_page_settings SET blocks = ?::jsonb WHERE id = ?",
                        [json_encode($next, JSON_THROW_ON_ERROR), (int) $row->id],
                    );
                }
            }
        }
    }

    /** @return list<int> */
    private function galleryPages(string $schema, array $block): array
    {
        $galleryId = $block['gallery_id'] ?? null;
        if (! is_numeric($galleryId)) {
            return [];
        }
        $id = DB::table($schema.'.site_sections')
            ->where('type', 'gallery')->where('template', 'standard')
            ->where('artwork_category_id', (int) $galleryId)->value('id');

        if ($id === null) {
            throw new \RuntimeException('An embedded Gallery has no matching canonical source page.');
        }

        return [(int) $id];
    }

    /** @return list<int> */
    private function heroPages(string $schema, array $block): array
    {
        $galleries = $block['gallery_ids'] ?? [];
        if (! is_array($galleries) || $galleries === []) {
            return [];
        }
        $expected = array_map('intval', $galleries);
        sort($expected);
        $candidates = DB::table($schema.'.site_sections')
            ->where('type', 'gallery')->where('template', 'hero_artworks')->pluck('id')->all();

        $matches = [];
        foreach (DB::table($schema.'.gallery_hero_settings')
            ->whereIn('site_section_id', $candidates)->get(['site_section_id', 'configuration']) as $setting) {
            $configuration = is_string($setting->configuration)
                ? json_decode($setting->configuration, true, 512, JSON_THROW_ON_ERROR)
                : $setting->configuration;
            $enabled = is_array($configuration) ? ($configuration['enabled_gallery_ids'] ?? []) : [];
            if (! is_array($enabled)) {
                continue;
            }
            $ids = array_map('intval', $enabled);
            sort($ids);
            if ($ids === $expected) {
                $matches[] = (int) $setting->site_section_id;
            }
        }
        if (count($matches) !== 1) {
            throw new \RuntimeException('Embedded Hero Artworks requires one unambiguous existing Hero page for migration.');
        }

        return $matches;
    }

    /** @return list<int> */
    private function entryPages(string $schema, string $table, mixed $entries): array
    {
        if (! is_array($entries) || $entries === []) {
            return [];
        }
        $ids = array_values(array_unique(array_map('intval', $entries)));
        $rows = DB::table($schema.'.'.$table)->whereIn('id', $ids)
            ->get(['id', 'site_section_id'])->keyBy('id');

        if ($rows->count() !== count($ids)) {
            throw new \RuntimeException('An embedded Journal entry no longer exists; manual reconciliation is required.');
        }
        $pages = [];
        foreach ($ids as $id) {
            $pageId = (int) $rows[$id]->site_section_id;
            if (! in_array($pageId, $pages, true)) {
                $pages[] = $pageId;
            }
        }

        return $pages;
    }

    public function down(): void
    {
        // A reverse transformation would silently discard complete page-template content.
    }
};
