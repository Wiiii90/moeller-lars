<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use RuntimeException;

return new class extends Migration
{
    public function up(): void
    {
        $contactSettings = DB::table('public_content_settings')->where('scope', 'contact')->first();
        $contactState = is_string($contactSettings?->contact_state ?? null)
            ? (string) $contactSettings->contact_state
            : 'enabled';
        $contactStatusText = is_string($contactSettings?->contact_status_text ?? null)
            ? trim((string) $contactSettings->contact_status_text)
            : null;

        $customSettings = DB::table('custom_page_settings')
            ->join('site_sections', 'site_sections.id', '=', 'custom_page_settings.site_section_id')
            ->select([
                'custom_page_settings.id',
                'custom_page_settings.site_section_id',
                'custom_page_settings.blocks',
                'site_sections.slug',
            ])
            ->orderBy('custom_page_settings.id')
            ->get();

        foreach ($customSettings as $setting) {
            $blocks = $this->normalizeBlocks(
                $this->jsonList($setting->blocks),
                ($setting->slug ?? null) === 'cv',
                $contactState,
                $contactStatusText,
            );

            DB::table('custom_page_settings')->where('id', $setting->id)->update([
                'blocks' => json_encode($blocks, JSON_THROW_ON_ERROR),
                'updated_at' => now(),
            ]);
        }

        $contactSection = DB::table('site_sections')
            ->where('type', 'custom')
            ->where('slug', 'contact')
            ->first();

        if ($contactSection !== null) {
            $sourceSetting = DB::table('custom_page_settings')->where('site_section_id', $contactSection->id)->first();
            $sourceBlocks = $sourceSetting === null ? [] : $this->jsonList($sourceSetting->blocks);

            $targetSection = DB::table('site_sections')
                ->where('type', 'custom')
                ->where('id', '<>', $contactSection->id)
                ->orderByRaw("CASE WHEN slug = 'cv' THEN 0 ELSE 1 END")
                ->orderBy('position')
                ->orderBy('id')
                ->first();

            if ($targetSection === null) {
                throw new RuntimeException('Standalone Contact content cannot be removed until another Custom Page exists to own its component.');
            }

            $targetSetting = DB::table('custom_page_settings')->where('site_section_id', $targetSection->id)->first();
            if ($targetSetting === null) {
                throw new RuntimeException('The Contact component target Custom Page has no settings record.');
            }

            $targetBlocks = $this->jsonList($targetSetting->blocks);
            $contactBlock = collect($sourceBlocks)->first(
                static fn (mixed $block): bool => is_array($block) && ($block['type'] ?? null) === 'contact',
            );
            $extraBlocks = array_values(array_filter(
                $sourceBlocks,
                static fn (mixed $block): bool => ! is_array($block) || ($block['type'] ?? null) !== 'contact',
            ));

            $hasContact = false;
            foreach ($targetBlocks as $index => $block) {
                if (! is_array($block) || ($block['type'] ?? null) !== 'contact') {
                    continue;
                }

                $hasContact = true;
                if (is_array($contactBlock)) {
                    $targetBlocks[$index] = array_replace($block, $contactBlock);
                }
            }

            if (! $hasContact) {
                $targetBlocks[] = is_array($contactBlock) ? $contactBlock : $this->contactBlock($contactState, $contactStatusText);
            }

            foreach ($extraBlocks as $block) {
                $targetBlocks[] = $block;
            }

            DB::table('custom_page_settings')->where('id', $targetSetting->id)->update([
                'blocks' => json_encode(array_values($targetBlocks), JSON_THROW_ON_ERROR),
                'updated_at' => now(),
            ]);

            DB::table('site_sections')
                ->where('parent_id', $contactSection->id)
                ->update([
                    'parent_id' => $contactSection->parent_id,
                    'updated_at' => now(),
                ]);

            DB::table('site_sections')->where('id', $contactSection->id)->delete();
        }

        DB::table('public_content_settings')->whereIn('scope', ['contact', 'vita'])->delete();

        DB::statement('ALTER TABLE public_content_settings DROP CONSTRAINT IF EXISTS public_content_settings_scope_check');
        DB::statement('ALTER TABLE public_content_settings DROP CONSTRAINT IF EXISTS public_content_settings_contact_state_check');
        DB::statement('ALTER TABLE public_content_settings DROP CONSTRAINT IF EXISTS public_content_settings_contact_icon_check');
        DB::statement('ALTER TABLE public_content_settings DROP CONSTRAINT IF EXISTS public_content_settings_contact_status_check');
        DB::statement("ALTER TABLE public_content_settings ADD CONSTRAINT public_content_settings_scope_check CHECK (scope = 'general')");

        Schema::table('public_content_settings', function (Blueprint $table): void {
            $table->dropColumn([
                'contact_state',
                'contact_status_text',
                'contact_icon',
                'profile_text_blocks',
            ]);
        });
    }

    public function down(): void
    {
        DB::statement('ALTER TABLE public_content_settings DROP CONSTRAINT IF EXISTS public_content_settings_scope_check');

        Schema::table('public_content_settings', function (Blueprint $table): void {
            $table->string('contact_state', 32)->default('hidden');
            $table->string('contact_status_text', 500)->nullable();
            $table->string('contact_icon', 32)->default('construction');
            $table->jsonb('profile_text_blocks')->nullable();
        });

        DB::statement("ALTER TABLE public_content_settings ADD CONSTRAINT public_content_settings_scope_check CHECK (scope IN ('general', 'contact', 'vita'))");
        DB::statement("ALTER TABLE public_content_settings ADD CONSTRAINT public_content_settings_contact_state_check CHECK (contact_state IN ('enabled', 'under_construction', 'hidden'))");
        DB::statement("ALTER TABLE public_content_settings ADD CONSTRAINT public_content_settings_contact_icon_check CHECK (contact_icon IN ('construction', 'mail', 'info'))");
        DB::statement("ALTER TABLE public_content_settings ADD CONSTRAINT public_content_settings_contact_status_check CHECK (contact_state <> 'under_construction' OR (contact_status_text IS NOT NULL AND btrim(contact_status_text) <> ''))");

        DB::table('public_content_settings')->insert([
            [
                'id' => 2,
                'scope' => 'contact',
                'contact_state' => 'hidden',
                'contact_icon' => 'construction',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'id' => 3,
                'scope' => 'vita',
                'contact_state' => 'hidden',
                'contact_icon' => 'construction',
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ]);
    }

    /**
     * @param  list<mixed>  $blocks
     * @return list<array<string, mixed>>
     */
    private function normalizeBlocks(array $blocks, bool $isCvPage, string $contactState, ?string $contactStatusText): array
    {
        $normalized = [];
        $hasCvList = false;

        foreach ($blocks as $block) {
            if (! is_array($block)) {
                continue;
            }

            $type = $block['type'] ?? null;
            if (! is_string($type)) {
                continue;
            }

            $mediaId = $block['media_asset_id'] ?? null;
            if (is_numeric($mediaId)) {
                $normalized[] = [
                    'type' => 'image',
                    'media_asset_id' => (int) $mediaId,
                    'image_decorative' => (bool) ($block['image_decorative'] ?? false),
                    'image_alt' => is_string($block['image_alt'] ?? null) ? trim($block['image_alt']) : null,
                ];
            }

            if ($isCvPage && $type === 'list' && $this->isDuplicatedCvList($block)) {
                $normalized[] = ['type' => 'cv_list'];
                $hasCvList = true;
            } elseif ($type === 'text') {
                $normalized[] = array_filter([
                    'type' => 'text',
                    'title' => $block['title'] ?? null,
                    'body' => $block['body'] ?? null,
                ], static fn (mixed $value): bool => $value !== null);
            } elseif ($type === 'list') {
                $normalized[] = [
                    'type' => 'list',
                    'title' => $block['title'] ?? null,
                    'items' => is_array($block['items'] ?? null) ? array_values($block['items']) : [],
                ];
            } elseif ($type === 'contact') {
                $normalized[] = array_replace(
                    $this->contactBlock($contactState, $contactStatusText),
                    [
                        'show_email' => (bool) ($block['show_email'] ?? true),
                        'show_form' => (bool) ($block['show_form'] ?? true),
                        'social_platforms' => is_array($block['social_platforms'] ?? null)
                            ? array_values($block['social_platforms'])
                            : [],
                    ],
                );
            } elseif (in_array($type, ['image', 'cv_list', 'divider'], true)) {
                $copy = $block;
                unset($copy['divider']);
                $normalized[] = $copy;
                $hasCvList = $hasCvList || $type === 'cv_list';
            }

            if (($block['divider'] ?? false) === true) {
                $normalized[] = ['type' => 'divider'];
            }
        }

        if ($isCvPage && ! $hasCvList) {
            $insertAt = isset($normalized[0]) && ($normalized[0]['type'] ?? null) === 'image' ? 1 : 0;
            array_splice($normalized, $insertAt, 0, [['type' => 'cv_list']]);
        }

        return array_values($normalized);
    }

    /** @param array<string, mixed> $block */
    private function isDuplicatedCvList(array $block): bool
    {
        $items = $block['items'] ?? null;
        if (! is_array($items) || ! array_is_list($items)) {
            return false;
        }

        $entries = DB::table('cv_entries')->orderBy('position')->orderBy('id')->get();
        if (count($items) !== $entries->count()) {
            return false;
        }

        foreach ($entries as $index => $entry) {
            $item = $items[$index] ?? null;
            if (! is_array($item)) {
                return false;
            }

            if (($item['title'] ?? null) !== (string) ($entry->title ?? '')
                || ($item['date'] ?? null) !== ($entry->year_text ?? null)
                || ($item['body'] ?? null) !== ($entry->body ?? null)) {
                return false;
            }
        }

        return true;
    }

    /** @return array<string, mixed> */
    private function contactBlock(string $state, ?string $statusText): array
    {
        $state = in_array($state, ['enabled', 'under_construction', 'hidden'], true) ? $state : 'enabled';

        return [
            'type' => 'contact',
            'show_email' => true,
            'show_form' => true,
            'social_platforms' => [],
            'form_state' => $state,
            'status_text' => $state === 'under_construction' && $statusText !== '' ? $statusText : null,
        ];
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
