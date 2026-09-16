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
            $rows = DB::select("SELECT id, blocks FROM {$schema}.custom_page_settings ORDER BY id");

            foreach ($rows as $row) {
                $blocks = $this->decodeJson($row->blocks ?? null);
                $canonical = $this->canonicalizeBlocks($blocks);
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
        // Canonical component data intentionally does not recreate retired aliases.
    }

    private function decodeJson(mixed $value): mixed
    {
        if (! is_string($value)) {
            return $value;
        }

        return json_decode($value, true, 512, JSON_THROW_ON_ERROR);
    }

    private function canonicalizeBlocks(mixed $blocks): mixed
    {
        if (! is_array($blocks) || ! array_is_list($blocks)) {
            return $blocks;
        }

        return array_map(function (mixed $block): mixed {
            if (! is_array($block)) {
                return $block;
            }

            if (($block['type'] ?? null) === 'list') {
                $block['items'] = $this->canonicalizeListItems($block['items'] ?? null);
            }

            if (($block['type'] ?? null) === 'contact') {
                $block = $this->canonicalizeContact($block);
            }

            return $block;
        }, $blocks);
    }

    private function canonicalizeListItems(mixed $items): mixed
    {
        if (! is_array($items) || ! array_is_list($items)) {
            return $items;
        }

        return array_map(static function (mixed $item): mixed {
            if (! is_array($item)) {
                return $item;
            }

            if (! array_key_exists('published', $item) && array_key_exists('visible', $item)) {
                $item['published'] = $item['visible'] === true;
            }
            unset($item['visible']);

            return $item;
        }, $items);
    }

    /** @param array<string, mixed> $block
     * @return array<string, mixed>
     */
    private function canonicalizeContact(array $block): array
    {
        $children = $block['children'] ?? null;
        if (! is_array($children) || ! array_is_list($children)) {
            $formState = is_string($block['form_state'] ?? null) ? $block['form_state'] : 'enabled';
            $platforms = array_values(array_unique(array_filter(
                is_array($block['social_platforms'] ?? null) ? $block['social_platforms'] : [],
                static fn (mixed $platform): bool => is_string($platform),
            )));
            $statusText = is_string($block['status_text'] ?? null) ? trim($block['status_text']) : null;
            if ($statusText === '') {
                $statusText = null;
            }

            $block['children'] = [
                [
                    'type' => 'public_email',
                    'published' => (bool) ($block['show_email'] ?? true),
                ],
                [
                    'type' => 'social_links',
                    'published' => true,
                    'social_platforms' => $platforms,
                ],
                [
                    'type' => 'contact_form',
                    'published' => (bool) ($block['show_form'] ?? true) && $formState !== 'hidden',
                    'form_state' => $formState === 'under_construction' ? 'under_construction' : 'enabled',
                    'status_text' => $statusText,
                ],
            ];
        }

        unset(
            $block['show_email'],
            $block['show_form'],
            $block['form_state'],
            $block['social_platforms'],
            $block['status_text'],
        );

        return $block;
    }
};
