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
            'contact' => [
                'type' => 'contact',
                'published' => $published,
                'title' => $data['title'] ?? null,
                'children' => [
                    ['type' => 'public_email', 'published' => (bool) ($data['contact_email_enabled'] ?? true)],
                    [
                        'type' => 'social_links',
                        'published' => (bool) ($data['contact_social_enabled'] ?? true),
                        'social_platforms' => $this->validatedSocialPlatforms($data['contact_social_platforms'] ?? array_keys($this->availableSocialPlatforms)),
                    ],
                    [
                        'type' => 'contact_form',
                        'published' => (bool) ($data['contact_form_enabled'] ?? true),
                        'form_state' => ($data['contact_form_state'] ?? 'enabled') === 'under_construction' ? 'under_construction' : 'enabled',
                        'status_text' => filled($data['contact_status_text'] ?? null) ? trim((string) $data['contact_status_text']) : null,
                    ],
                ],
            ],
            'legal_disclaimer' => ['type' => 'legal_disclaimer', 'published' => $published],
        };
    }

}
