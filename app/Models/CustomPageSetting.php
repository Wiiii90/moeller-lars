<?php

namespace App\Models;

use App\Domain\Content\SafeLinkPolicy;
use App\Domain\Content\SafeRichTextRenderer;
use App\Domain\Content\SocialLinks;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Guarded;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Validation\ValidationException;

#[Fillable(['blocks'])]
#[Guarded(['id', 'site_section_id'])]
final class CustomPageSetting extends Model
{
    public const COMPONENT_TYPES = [
        'image',
        'cv_list',
        'text',
        'list',
        'divider',
        'contact',
    ];

    protected function casts(): array
    {
        return [
            'blocks' => 'array',
        ];
    }

    protected static function booted(): void
    {
        self::saving(function (self $settings): void {
            $settings->setAttribute('blocks', $settings->normalizedBlocks());
            $settings->validateBlocks(requirePublicMedia: false);
        });
    }

    /** @return BelongsTo<SiteSection, $this> */
    public function siteSection(): BelongsTo
    {
        return $this->belongsTo(SiteSection::class);
    }

    public function assertReadyForPublic(): void
    {
        $this->validateBlocks(requirePublicMedia: true);
    }

    /** @return list<array<string, mixed>> */
    public function components(): array
    {
        $blocks = $this->getAttribute('blocks');

        return is_array($blocks) && array_is_list($blocks) ? $blocks : [];
    }

    /** @return list<array<string, mixed>> */
    private function normalizedBlocks(): array
    {
        $blocks = $this->getAttribute('blocks');
        if (! is_array($blocks) || ! array_is_list($blocks)) {
            return [];
        }

        $normalized = [];
        foreach ($blocks as $block) {
            if (! is_array($block)) {
                continue;
            }

            $type = $block['type'] ?? null;
            if (! is_string($type) || ! in_array($type, self::COMPONENT_TYPES, true)) {
                $normalized[] = ['type' => $type];

                continue;
            }

            $normalized[] = match ($type) {
                'image' => [
                    'type' => 'image',
                    'media_asset_id' => is_numeric($block['media_asset_id'] ?? null)
                        ? (int) $block['media_asset_id']
                        : null,
                    'image_decorative' => (bool) ($block['image_decorative'] ?? false),
                ],
                'cv_list' => ['type' => 'cv_list'],
                'text' => [
                    'type' => 'text',
                    'title' => $this->nullableTrimmedString($block['title'] ?? null),
                    'body' => $block['body'] ?? null,
                ],
                'list' => [
                    'type' => 'list',
                    'title' => $this->nullableTrimmedString($block['title'] ?? null),
                    'items' => $this->normalizeListItems($block['items'] ?? []),
                ],
                'divider' => ['type' => 'divider'],
                'contact' => [
                    'type' => 'contact',
                    'show_email' => (bool) ($block['show_email'] ?? true),
                    'show_form' => (bool) ($block['show_form'] ?? true),
                    'social_platforms' => array_values(array_unique(array_filter(
                        is_array($block['social_platforms'] ?? null) ? $block['social_platforms'] : [],
                        static fn (mixed $platform): bool => is_string($platform),
                    ))),
                    'form_state' => is_string($block['form_state'] ?? null)
                        ? $block['form_state']
                        : 'enabled',
                    'status_text' => $this->nullableTrimmedString($block['status_text'] ?? null),
                ],
            };
        }

        return $normalized;
    }

    private function validateBlocks(bool $requirePublicMedia): void
    {
        $blocks = $this->getAttribute('blocks');
        if (! is_array($blocks) || ! array_is_list($blocks)) {
            throw ValidationException::withMessages(['blocks' => 'Page components must be an ordered list.']);
        }

        foreach ($blocks as $blockIndex => $block) {
            if (! is_array($block)) {
                throw ValidationException::withMessages(['blocks' => 'Each page component must be structured data.']);
            }

            $type = $block['type'] ?? null;
            if (! is_string($type) || ! in_array($type, self::COMPONENT_TYPES, true)) {
                throw ValidationException::withMessages(['blocks' => 'A page component has an unsupported type.']);
            }

            if ($type === 'image') {
                $this->validateImageComponent($block, $requirePublicMedia);
            }

            if ($type === 'cv_list' && array_key_exists('items', $block)) {
                throw ValidationException::withMessages(['blocks' => 'CV List components reference canonical CV records and cannot store copied entries.']);
            }

            if ($type === 'text') {
                $title = $block['title'] ?? null;
                if ($title !== null && (! is_string($title) || mb_strlen($title) > 160)) {
                    throw ValidationException::withMessages(['blocks' => 'Text component headings must be short text.']);
                }
                $this->validateRichText($block['body'] ?? null, 'blocks.'.$blockIndex.'.body');
            }

            if ($type === 'list') {
                $this->validateListComponent($block, $blockIndex);
            }

            if ($type === 'contact') {
                $this->validateContactComponent($block);
            }
        }
    }

    /** @param array<string, mixed> $block */
    private function validateImageComponent(array $block, bool $requirePublicMedia): void
    {
        $mediaId = $block['media_asset_id'] ?? null;
        if (filter_var($mediaId, FILTER_VALIDATE_INT) === false) {
            throw ValidationException::withMessages(['blocks' => 'Image components must reference an image from Media.']);
        }

        /** @var MediaAsset|null $asset */
        $asset = MediaAsset::query()->find((int) $mediaId);
        if (! $asset instanceof MediaAsset || ! str_starts_with((string) $asset->getAttribute('mime_type'), 'image/')) {
            throw ValidationException::withMessages(['blocks' => 'Image components must reference an image from Media.']);
        }

        if (! is_bool($block['image_decorative'] ?? false)) {
            throw ValidationException::withMessages(['blocks' => 'Image presentation settings must be boolean.']);
        }

        if (! $requirePublicMedia) {
            return;
        }

        $decorative = (bool) ($block['image_decorative'] ?? false);
        $alt = $asset->getAttribute('alt_text');
        if ((string) $asset->getAttribute('state') !== 'available') {
            throw ValidationException::withMessages(['blocks' => 'Published custom-page images must be available.']);
        }
        if (! $decorative && (! is_string($alt) || trim($alt) === '')) {
            throw ValidationException::withMessages(['blocks' => 'Published non-decorative images must have canonical ALT text in Media.']);
        }
    }

    /** @param array<string, mixed> $block */
    private function validateListComponent(array $block, int $blockIndex): void
    {
        $title = $block['title'] ?? null;
        if ($title !== null && (! is_string($title) || mb_strlen($title) > 160)) {
            throw ValidationException::withMessages(['blocks' => 'List component headings must be short text.']);
        }

        $items = $block['items'] ?? [];
        if (! is_array($items) || ! array_is_list($items)) {
            throw ValidationException::withMessages(['blocks' => 'List component entries must be ordered.']);
        }

        foreach ($items as $itemIndex => $item) {
            if (! is_array($item)) {
                throw ValidationException::withMessages(['blocks' => 'A list component entry is invalid.']);
            }
            if (! is_bool($item['visible'] ?? true)) {
                throw ValidationException::withMessages(['blocks' => 'List entry visibility must be boolean.']);
            }

            $itemTitle = $item['title'] ?? null;
            if (! is_string($itemTitle) || trim($itemTitle) === '' || mb_strlen($itemTitle) > 240) {
                throw ValidationException::withMessages(['blocks' => 'Each list entry requires a short title.']);
            }

            $this->validateRichText($item['body'] ?? null, 'blocks.'.$blockIndex.'.items.'.$itemIndex.'.body');
            $this->validateUrl($item['url'] ?? null, 'blocks.'.$blockIndex.'.items.'.$itemIndex.'.url');
        }
    }

    /** @param array<string, mixed> $block */
    private function validateContactComponent(array $block): void
    {
        foreach (['show_email', 'show_form'] as $toggle) {
            if (! is_bool($block[$toggle] ?? true)) {
                throw ValidationException::withMessages(['blocks' => 'Contact component toggles must be boolean.']);
            }
        }

        $platforms = $block['social_platforms'] ?? [];
        if (! is_array($platforms) || ! array_is_list($platforms)) {
            throw ValidationException::withMessages(['blocks' => 'Contact social links must be an ordered selection.']);
        }
        foreach ($platforms as $platform) {
            if (! is_string($platform) || ! SocialLinks::supports($platform)) {
                throw ValidationException::withMessages(['blocks' => 'A Contact component references an unsupported social platform.']);
            }
        }

        $state = $block['form_state'] ?? 'enabled';
        if (! is_string($state) || ! in_array($state, ['enabled', 'under_construction', 'hidden'], true)) {
            throw ValidationException::withMessages(['blocks' => 'Contact form presentation state is invalid.']);
        }

        $statusText = $block['status_text'] ?? null;
        if ($state === 'under_construction' && (! is_string($statusText) || trim($statusText) === '')) {
            throw ValidationException::withMessages(['blocks' => 'Contact components need status text while the form is under construction.']);
        }
        if ($statusText !== null && (! is_string($statusText) || mb_strlen($statusText) > 500)) {
            throw ValidationException::withMessages(['blocks' => 'Contact status text is too long.']);
        }
    }

    /** @return list<array<string, mixed>> */
    private function normalizeListItems(mixed $items): array
    {
        if (! is_array($items) || ! array_is_list($items)) {
            return [];
        }

        return array_map(function (mixed $item): array {
            if (! is_array($item)) {
                return [];
            }

            return [
                'visible' => (bool) ($item['visible'] ?? true),
                'date' => $this->nullableTrimmedString($item['date'] ?? null),
                'title' => $this->nullableTrimmedString($item['title'] ?? null),
                'meta' => $this->nullableTrimmedString($item['meta'] ?? null),
                'location' => $this->nullableTrimmedString($item['location'] ?? null),
                'url' => $this->nullableTrimmedString($item['url'] ?? null),
                'body' => $item['body'] ?? null,
            ];
        }, $items);
    }

    private function nullableTrimmedString(mixed $value): ?string
    {
        if (! is_string($value)) {
            return null;
        }

        $value = trim($value);

        return $value === '' ? null : $value;
    }

    private function validateRichText(mixed $value, string $field): void
    {
        if ($value === null || $value === '') {
            return;
        }
        if (! is_string($value)) {
            throw ValidationException::withMessages([$field => 'Component rich text must be text.']);
        }

        app(SafeRichTextRenderer::class)->assertValid($value);
    }

    private function validateUrl(mixed $value, string $field): void
    {
        if ($value === null || $value === '') {
            return;
        }
        if (! is_string($value)
            || ! in_array(strtolower((string) parse_url($value, PHP_URL_SCHEME)), ['http', 'https'], true)
            || ! app(SafeLinkPolicy::class)->isAllowed($value)) {
            throw ValidationException::withMessages([$field => 'Component links must be valid HTTP or HTTPS URLs.']);
        }
    }
}
