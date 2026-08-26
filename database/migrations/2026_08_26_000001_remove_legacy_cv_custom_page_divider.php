<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $section = DB::table('site_sections')
            ->where('type', 'custom')
            ->where('slug', 'cv')
            ->first();
        if ($section === null) {
            return;
        }

        $settings = DB::table('custom_page_settings')
            ->where('site_section_id', $section->id)
            ->first();
        if ($settings === null) {
            return;
        }

        $blocks = $this->jsonList($settings->blocks ?? null);
        foreach ($blocks as $index => $block) {
            if (! is_array($block)
                || count($block) !== 1
                || ($block['type'] ?? null) !== 'divider') {
                continue;
            }

            $previous = $blocks[$index - 1] ?? null;
            $next = $blocks[$index + 1] ?? null;
            if (! is_array($previous)
                || ($previous['type'] ?? null) !== 'cv_list'
                || ! is_array($next)
                || ($next['type'] ?? null) !== 'contact') {
                continue;
            }

            // 000013 stored the old Vita list's layout border as `divider => true`.
            // 000015 promoted that flag into this standalone divider while replacing
            // the copied list with the canonical cv_list marker. It was migration
            // presentation provenance, not an artist-authored Divider component.
            unset($blocks[$index]);

            DB::table('custom_page_settings')
                ->where('id', $settings->id)
                ->update([
                    'blocks' => json_encode(array_values($blocks), JSON_THROW_ON_ERROR),
                    'updated_at' => now(),
                ]);

            return;
        }
    }

    public function down(): void
    {
        // Deliberately irreversible: restoring obsolete Vita layout provenance as
        // semantic page content would recreate the defect and could conflict with
        // Divider components the artist adds after this cleanup.
    }

    /** @return list<mixed> */
    private function jsonList(mixed $value): array
    {
        if (is_array($value)) {
            return array_is_list($value) ? $value : [];
        }
        if (! is_string($value) || trim($value) === '') {
            return [];
        }

        $decoded = json_decode($value, true);

        return is_array($decoded) && array_is_list($decoded) ? $decoded : [];
    }
};
