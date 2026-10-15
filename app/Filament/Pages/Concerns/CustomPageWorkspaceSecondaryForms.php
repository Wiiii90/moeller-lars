<?php

namespace App\Filament\Pages\Concerns;

use App\Models\CustomPageSetting;
use Illuminate\Validation\ValidationException;

trait CustomPageWorkspaceSecondaryForms
{
    /** @param array<string,mixed> $block */
    private function componentEditorData(array $block): array
    {
        $data = [
            ...$block,
            'publication_state' => CustomPageSetting::componentPublished($block) ? 'published' : 'unpublished',
        ];
        if (($block['type'] ?? null) === 'image') {
            $data['image_scale_percent'] = CustomPageSetting::imageScalePercent($block);
            $data['image_alignment'] = CustomPageSetting::imageAlignment($block);
        }
        if (($block['type'] ?? null) === 'list') {
            $data['items'] = array_map(static fn (array $item): array => [
                'title' => $item['title'] ?? null,
                'context' => CustomPageSetting::listItemContext($item),
                'body' => CustomPageSetting::listItemDetails($item),
                'published' => CustomPageSetting::listItemPublished($item),
            ], array_values(array_filter($block['items'] ?? [], 'is_array')));
        }
        if (($block['type'] ?? null) === 'contact') {
            $children = collect($this->settings()->contactChildren($block))->keyBy('type');
            $email = $children->get('public_email');
            $social = $children->get('social_links');
            $form = $children->get('contact_form');
            $data['contact_email_enabled'] = is_array($email) && CustomPageSetting::contactChildPublished($email);
            $data['contact_social_enabled'] = is_array($social) && CustomPageSetting::contactChildPublished($social);
            $data['contact_form_enabled'] = is_array($form) && CustomPageSetting::contactChildPublished($form);
            $data['contact_social_platforms'] = is_array($social) ? ($social['social_platforms'] ?? []) : [];
            $data['contact_form_state'] = is_array($form) ? ($form['form_state'] ?? 'enabled') : 'enabled';
            $data['contact_status_text'] = is_array($form) ? ($form['status_text'] ?? null) : null;
        }
        if (($block['type'] ?? null) === 'divider') {
            $data['variant'] = in_array($block['variant'] ?? null, array_keys(self::DIVIDER_LABELS), true)
                ? $block['variant']
                : 'thin';
        }

        return $data;
    }

    /** @return array<string,mixed> */
    private function componentPayload(array $data, ?array $existing = null): array
    {
        $type = $data['type'] ?? null;
        if (! is_string($type) || ! array_key_exists($type, self::COMPONENT_LABELS)) {
            throw ValidationException::withMessages(['type' => 'Choose a supported component type.']);
        }
        $published = ($data['publication_state'] ?? 'published') === 'published';

        return match ($type) {
            'image' => [
                'type' => 'image',
                'published' => $published,
                'media_asset_id' => is_numeric($data['media_asset_id'] ?? null) ? (int) $data['media_asset_id'] : null,
                'image_decorative' => (bool) ($data['image_decorative'] ?? false),
                ...$this->imageAlignmentPayload($data, $existing),
                ...$this->imageScalePayload($data, $existing),
                ...$this->imageChildrenPayload($data, $existing),
            ],
            'text' => [
                'type' => 'text',
                'published' => $published,
                'title' => $data['title'] ?? null,
                'body' => $data['body'] ?? null,
            ],
            'list' => [
                'type' => 'list',
                'published' => $published,
                'title' => $data['title'] ?? null,
                'media_asset_id' => is_numeric($data['media_asset_id'] ?? null) ? (int) $data['media_asset_id'] : null,
                'items' => is_array($data['items'] ?? null) ? array_values($data['items']) : (is_array($existing['items'] ?? null) ? array_values($existing['items']) : []),
            ],
            'divider' => [
                'type' => 'divider',
                'published' => $published,
                'variant' => in_array($data['variant'] ?? null, array_keys(self::DIVIDER_LABELS), true) ? $data['variant'] : 'thin',
            ],
            'contact' => $this->contactComponentPayload($data, $published, $existing),
            'legal_disclaimer' => ['type' => 'legal_disclaimer', 'published' => $published],
        };
    }

    /** Image settings never rewrite children. Child edits go through their
     * own component Actions and the canonical content service. */
    private function imageChildrenPayload(array $data, ?array $existing): array
    {
        $children = $existing['children'] ?? [];
        return $children === [] ? [] : ['children' => $children];
    }

    /** Left is implicit to preserve the historical image representation. */
    private function imageAlignmentPayload(array $data, ?array $existing): array
    {
        $alignment = $data['image_alignment'] ?? ($existing['image_alignment'] ?? 'left');
        if (! is_string($alignment) || ! in_array($alignment, CustomPageSetting::IMAGE_ALIGNMENTS, true)) {
            throw ValidationException::withMessages(['image_alignment' => 'Choose Left or Right alignment.']);
        }

        return $alignment === 'left' ? [] : ['image_alignment' => 'right'];
    }

    /** @param array<string, mixed> $data
     * @param array<string, mixed>|null $existing
     * @return array<string, int>
     */
    private function imageScalePayload(array $data, ?array $existing): array
    {
        $value = $data['image_scale_percent']
            ?? ($existing['image_scale_percent'] ?? CustomPageSetting::DEFAULT_IMAGE_SCALE_PERCENT);
        $scale = filter_var($value, FILTER_VALIDATE_INT, [
            'options' => [
                'min_range' => CustomPageSetting::MIN_IMAGE_SCALE_PERCENT,
                'max_range' => CustomPageSetting::MAX_IMAGE_SCALE_PERCENT,
            ],
        ]);
        if ($scale === false) {
            throw ValidationException::withMessages(['image_scale_percent' => 'Image size must be a whole percentage between 25 and 200.']);
        }

        return $scale === CustomPageSetting::DEFAULT_IMAGE_SCALE_PERCENT
            ? []
            : ['image_scale_percent' => $scale];
    }

    /** @param array<string,mixed> $data
     * @param array<string,mixed>|null $existing
     * @return array<string,mixed>
     */
    private function contactComponentPayload(array $data, bool $published, ?array $existing): array
    {
        // A disabled toggle may hide its dependent fields. Preserve authored
        // options until they are explicitly edited, not when they are hidden.
        $children = $existing !== null ? $this->settings()->contactChildren($existing) : [];
        $social = collect($children)->firstWhere('type', 'social_links');
        $form = collect($children)->firstWhere('type', 'contact_form');
        $platforms = $data['contact_social_platforms']
            ?? (is_array($social) ? ($social['social_platforms'] ?? []) : array_keys($this->availableSocialPlatforms));
        $formState = $data['contact_form_state']
            ?? (is_array($form) ? ($form['form_state'] ?? 'enabled') : 'enabled');
        $statusText = $data['contact_status_text']
            ?? (is_array($form) ? ($form['status_text'] ?? null) : null);

        return [
            'type' => 'contact',
            'published' => $published,
            'title' => $data['title'] ?? null,
            'children' => [
                ['type' => 'public_email', 'published' => (bool) ($data['contact_email_enabled'] ?? true)],
                [
                    'type' => 'social_links',
                    'published' => (bool) ($data['contact_social_enabled'] ?? true),
                    'social_platforms' => $this->validatedSocialPlatforms($platforms),
                ],
                [
                    'type' => 'contact_form',
                    'published' => (bool) ($data['contact_form_enabled'] ?? true),
                    'form_state' => $formState === 'under_construction' ? 'under_construction' : 'enabled',
                    'status_text' => filled($statusText) ? trim((string) $statusText) : null,
                ],
            ],
        ];
    }
}
