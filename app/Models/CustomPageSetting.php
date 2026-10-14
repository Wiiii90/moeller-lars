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
        'text',
        'list',
        'divider',
        'contact',
        'legal_disclaimer',
    ];

    /** Only prose-capable children can wrap beside a decorative Image. */
    public const IMAGE_CHILD_TYPES = ['text', 'list', 'contact', 'legal_disclaimer'];

    public const CONTACT_CHILD_TYPES = [
        'public_email',
        'social_links',
        'contact_form',
    ];

    public const DIVIDER_VARIANTS = \App\Domain\Content\DividerVariant::VALUES;

    public const DEFAULT_IMAGE_SCALE_PERCENT = 100;

    public const MIN_IMAGE_SCALE_PERCENT = 25;

    public const MAX_IMAGE_SCALE_PERCENT = 200;

    protected function casts(): array
    {
        return ['blocks' => 'array'];
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

    /** Shared editorial validation for Custom Page and Home component sequences. */
    public function assertEditableComponents(): void
    {
        $this->validateBlocks(requirePublicMedia: false);
    }

    /** @return list<array<string, mixed>> */
    public function components(): array
    {
        $blocks = $this->getAttribute('blocks');

        return is_array($blocks) && array_is_list($blocks) ? $blocks : [];
    }

    /** A missing or malformed historical value must never break public layout. */
    public static function imageScalePercent(array $block): int
    {
        $value = $block['image_scale_percent'] ?? self::DEFAULT_IMAGE_SCALE_PERCENT;

        return is_int($value) && $value >= self::MIN_IMAGE_SCALE_PERCENT && $value <= self::MAX_IMAGE_SCALE_PERCENT
            ? $value
            : self::DEFAULT_IMAGE_SCALE_PERCENT;
    }

    public static function componentPublished(array $block): bool
    {
        return ($block['published'] ?? true) === true;
    }

    public static function listItemPublished(array $item): bool
    {
        return ($item['published'] ?? true) === true;
    }

    /** Read old Date / Organisation / Location fields as one Context. */
    public static function listItemContext(array $item): ?string
    {
        $value = $item['context'] ?? null;
        if (is_string($value) && trim($value) !== '') {
            return trim($value);
        }
        $parts = [];
        foreach (['date', 'meta', 'location'] as $key) {
            $part = $item[$key] ?? null;
            if (is_string($part) && trim($part) !== '') {
                $parts[] = trim($part);
            }
        }

        return $parts === [] ? null : implode(' · ', $parts);
    }

    /** Preserve existing optional links as part of Details when migrating. */
    public static function listItemDetails(array $item): ?string
    {
        $body = is_string($item['body'] ?? null) ? trim($item['body']) : '';
        $url = is_string($item['url'] ?? null) ? trim($item['url']) : '';
        if ($url !== '' && filter_var($url, FILTER_VALIDATE_URL)
            && in_array(parse_url($url, PHP_URL_SCHEME), ['http', 'https'], true)) {
            $link = '[More information]('.str_replace(['(', ')'], ['%28', '%29'], $url).')';
            $body = $body !== '' ? $body."\n\n".$link : $link;
        }

        return $body === '' ? null : $body;
    }

    public static function contactChildPublished(array $child): bool
    {
        return ($child['published'] ?? true) === true;
    }

    /** @param array<string, mixed> $block
     * @return list<array<string, mixed>>
     */
    public function contactChildren(array $block): array
    {
        return $this->normalizeContactChildren($block);
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

            $published = self::componentPublished($block);
            $normalized[] = match ($type) {
                'image' => [
                    'type' => 'image',
                    'published' => $published,
                    'media_asset_id' => is_numeric($block['media_asset_id'] ?? null) ? (int) $block['media_asset_id'] : null,
                    'image_decorative' => (bool) ($block['image_decorative'] ?? false),
                    ...$this->normalizedImageScale($block),
                    ...$this->normalizedImageChildren($block),
                ],
                'text' => [
                    'type' => 'text',
                    'published' => $published,
                    'title' => $this->nullableTrimmedString($block['title'] ?? null),
                    'body' => $block['body'] ?? null,
                ],
                'list' => [
                    'type' => 'list',
                    'published' => $published,
                    'title' => $this->nullableTrimmedString($block['title'] ?? null),
                    'media_asset_id' => is_numeric($block['media_asset_id'] ?? null) ? (int) $block['media_asset_id'] : null,
                    'items' => $this->normalizeListItems($block['items'] ?? []),
                ],
                'divider' => [
                    'type' => 'divider',
                    'published' => $published,
                    'variant' => in_array($block['variant'] ?? null, self::DIVIDER_VARIANTS, true) ? $block['variant'] : 'thin',
                ],
                'contact' => [
                    'type' => 'contact',
                    'published' => $published,
                    'title' => $this->nullableTrimmedString($block['title'] ?? null),
                    'children' => $this->normalizeContactChildren($block),
                ],
                'legal_disclaimer' => ['type' => 'legal_disclaimer', 'published' => $published],
            };
        }

        return $normalized;
    }

    /** Normalize the optional single nesting level using the exact same
     * component schemas as top-level prose components. */
    private function normalizedImageChildren(array $image): array
    {
        if (! array_key_exists('children', $image)) {
            return [];
        }
        $children = $image['children'];
        if (! is_array($children) || ! array_is_list($children)) {
            throw ValidationException::withMessages(['blocks' => 'Image children must be ordered components.']);
        }
        $nested = new self;
        $nested->setAttribute('blocks', $children);

        return ['children' => $nested->normalizedBlocks()];
    }

    /**
     * Keep the historical 100% size implicit so an unchanged legacy image
     * remains a no-op when its component editor is opened and submitted.
     *
     * @param array<string, mixed> $block
     * @return array<string, int>
     */
    private function normalizedImageScale(array $block): array
    {
        if (! array_key_exists('image_scale_percent', $block)) {
            return [];
        }

        $scale = filter_var($block['image_scale_percent'], FILTER_VALIDATE_INT, [
            'options' => [
                'min_range' => self::MIN_IMAGE_SCALE_PERCENT,
                'max_range' => self::MAX_IMAGE_SCALE_PERCENT,
            ],
        ]);
        if ($scale === false) {
            throw ValidationException::withMessages(['blocks' => 'Image size must be a whole percentage between 25 and 200.']);
        }

        return $scale === self::DEFAULT_IMAGE_SCALE_PERCENT ? [] : ['image_scale_percent' => $scale];
    }

    private function validateBlocks(bool $requirePublicMedia): void
    {
        $blocks = $this->getAttribute('blocks');
        if (! is_array($blocks) || ! array_is_list($blocks)) {
            throw ValidationException::withMessages(['blocks' => 'Page components must be an ordered list.']);
        }

        $legalDisclaimerCount = 0;
        foreach ($blocks as $blockIndex => $block) {
            if (! is_array($block)) {
                throw ValidationException::withMessages(['blocks' => 'Each page component must be structured data.']);
            }

            $type = $block['type'] ?? null;
            if (! is_string($type) || ! in_array($type, self::COMPONENT_TYPES, true)) {
                throw ValidationException::withMessages(['blocks' => 'A page component has an unsupported type.']);
            }
            if (! is_bool($block['published'] ?? true)) {
                throw ValidationException::withMessages(['blocks' => 'Component publication state must be boolean.']);
            }

            $public = $requirePublicMedia && self::componentPublished($block);

            if ($type === 'image') {
                $this->validateImageComponent($block, $public);
                $children = $block['children'] ?? [];
                if (! is_array($children) || ! array_is_list($children)) {
                    throw ValidationException::withMessages(['blocks' => 'Image children must be ordered components.']);
                }
                if ($children !== [] && ! ($block['image_decorative'] ?? false)) {
                    throw ValidationException::withMessages(['blocks' => 'Only decorative Images can contain text children.']);
                }
                foreach ($children as $child) {
                    $childType = is_array($child) ? ($child['type'] ?? null) : null;
                    if (! in_array($childType, self::IMAGE_CHILD_TYPES, true)) {
                        throw ValidationException::withMessages(['blocks' => 'Images may only contain Text, List, Contact details or Rechtliches.']);
                    }
                    if ($childType === 'list' && ($child['media_asset_id'] ?? null) !== null) {
                        throw ValidationException::withMessages(['blocks' => 'Lists beside an image cannot contain another image.']);
                    }
                    if ($childType === 'contact') {
                        foreach ($child['children'] ?? [] as $contactChild) {
                            if (is_array($contactChild) && ($contactChild['type'] ?? null) === 'contact_form'
                                && self::contactChildPublished($contactChild)) {
                                throw ValidationException::withMessages(['blocks' => 'Contact Form must remain outside an image.']);
                            }
                        }
                    }
                    if ($childType === 'legal_disclaimer') {
                        $legalDisclaimerCount++;
                    }
                }
                $nested = new self;
                $nested->setAttribute('blocks', $children);
                $nested->validateBlocks($public);
            }
            if ($type === 'text') {
                $title = $block['title'] ?? null;
                if ($title !== null && (! is_string($title) || mb_strlen($title) > 160)) {
                    throw ValidationException::withMessages(['blocks' => 'Text component headings must be short text.']);
                }
                $this->validateRichText($block['body'] ?? null, 'blocks.'.$blockIndex.'.body', $public);
            }
            if ($type === 'list') {
                $this->validateListComponent($block, $blockIndex, $public);
            }
            if ($type === 'divider') {
                $variant = $block['variant'] ?? 'thin';
                if (! is_string($variant) || ! in_array($variant, self::DIVIDER_VARIANTS, true)) {
                    throw ValidationException::withMessages(['blocks' => 'Divider style is invalid.']);
                }
            }
            if ($type === 'contact') {
                $title = $block['title'] ?? null;
                if ($title !== null && (! is_string($title) || mb_strlen($title) > 160)) {
                    throw ValidationException::withMessages(['blocks' => 'Contact headings must be short text.']);
                }
                $this->validateContactComponent($block);
            }
            if ($type === 'legal_disclaimer') {
                $legalDisclaimerCount++;
                if ($legalDisclaimerCount > 1) {
                    throw ValidationException::withMessages(['blocks' => 'A page can contain at most one Rechtliches component.']);
                }
            }
        }
        if ($legalDisclaimerCount > 1) {
            throw ValidationException::withMessages(['blocks' => 'A page can contain at most one Rechtliches component.']);
        }
    }

    /** @param array<string, mixed> $block */
    private function validateImageComponent(array $block, bool $requirePublicMedia): void
    {
        $scale = $block['image_scale_percent'] ?? self::DEFAULT_IMAGE_SCALE_PERCENT;
        if (! is_int($scale) || $scale < self::MIN_IMAGE_SCALE_PERCENT || $scale > self::MAX_IMAGE_SCALE_PERCENT) {
            throw ValidationException::withMessages(['blocks' => 'Image size must be a whole percentage between 25 and 200.']);
        }

        $mediaId = $block['media_asset_id'] ?? null;
        if ($mediaId === null) {
            if ($requirePublicMedia) {
                throw ValidationException::withMessages(['blocks' => 'Published image components must reference an image from Media.']);
            }

            return;
        }

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
    private function validateListComponent(array $block, int $blockIndex, bool $requirePublicMedia): void
    {
        $title = $block['title'] ?? null;
        if ($title !== null && (! is_string($title) || mb_strlen($title) > 160)) {
            throw ValidationException::withMessages(['blocks' => 'List component headings must be short text.']);
        }

        $mediaId = $block['media_asset_id'] ?? null;
        if ($mediaId !== null) {
            if (filter_var($mediaId, FILTER_VALIDATE_INT) === false) {
                throw ValidationException::withMessages(['blocks' => 'List images must reference an image from Media.']);
            }
            /** @var MediaAsset|null $asset */
            $asset = MediaAsset::query()->find((int) $mediaId);
            if (! $asset instanceof MediaAsset || ! str_starts_with((string) $asset->getAttribute('mime_type'), 'image/')) {
                throw ValidationException::withMessages(['blocks' => 'List images must reference an image from Media.']);
            }
            if ($requirePublicMedia) {
                $alt = $asset->getAttribute('alt_text');
                if ((string) $asset->getAttribute('state') !== 'available') {
                    throw ValidationException::withMessages(['blocks' => 'Published list images must be available.']);
                }
                if (! is_string($alt) || trim($alt) === '') {
                    throw ValidationException::withMessages(['blocks' => 'Published list images must have canonical ALT text in Media.']);
                }
            }
        }

        $items = $block['items'] ?? [];
        if (! is_array($items) || ! array_is_list($items)) {
            throw ValidationException::withMessages(['blocks' => 'List component entries must be ordered.']);
        }

        foreach ($items as $itemIndex => $item) {
            if (! is_array($item)) {
                throw ValidationException::withMessages(['blocks' => 'A list component entry is invalid.']);
            }
            if (! is_bool($item['published'] ?? true)) {
                throw ValidationException::withMessages(['blocks' => 'List entry publication state must be boolean.']);
            }
            $itemTitle = $item['title'] ?? null;
            if (! is_string($itemTitle) || trim($itemTitle) === '' || mb_strlen($itemTitle) > 240) {
                throw ValidationException::withMessages(['blocks' => 'Each list entry requires a short title.']);
            }
            $this->validateRichText($item['body'] ?? null, 'blocks.'.$blockIndex.'.items.'.$itemIndex.'.body', $requirePublicMedia && self::listItemPublished($item));
            if (isset($item['context']) && (! is_string($item['context']) || mb_strlen($item['context']) > 600)) {
                throw ValidationException::withMessages(['blocks' => 'List entry context may not exceed 600 characters.']);
            }
        }
    }

    /** @param array<string, mixed> $block */
    private function validateContactComponent(array $block): void
    {
        $children = $this->normalizeContactChildren($block);
        $seen = [];
        foreach ($children as $child) {
            $type = $child['type'] ?? null;
            if (! is_string($type) || ! in_array($type, self::CONTACT_CHILD_TYPES, true)) {
                throw ValidationException::withMessages(['blocks' => 'Contact contains an unsupported child component.']);
            }
            if (isset($seen[$type])) {
                throw ValidationException::withMessages(['blocks' => 'Each Contact child component can only be used once.']);
            }
            $seen[$type] = true;
            if (! is_bool($child['published'] ?? true)) {
                throw ValidationException::withMessages(['blocks' => 'Contact child publication state must be boolean.']);
            }
            if ($type === 'social_links') {
                $platforms = $child['social_platforms'] ?? [];
                if (! is_array($platforms) || ! array_is_list($platforms)) {
                    throw ValidationException::withMessages(['blocks' => 'Contact social links must be an ordered selection.']);
                }
                foreach ($platforms as $platform) {
                    if (! is_string($platform) || ! SocialLinks::supports($platform)) {
                        throw ValidationException::withMessages(['blocks' => 'A Contact component references an unsupported social platform.']);
                    }
                }
            }
            if ($type === 'contact_form') {
                $state = $child['form_state'] ?? 'enabled';
                if (! is_string($state) || ! in_array($state, ['enabled', 'under_construction'], true)) {
                    throw ValidationException::withMessages(['blocks' => 'Contact form presentation state is invalid.']);
                }
                $statusText = $child['status_text'] ?? null;
                if ($state === 'under_construction' && (! is_string($statusText) || trim($statusText) === '')) {
                    throw ValidationException::withMessages(['blocks' => 'Contact Form needs status text while under construction.']);
                }
                if ($statusText !== null && (! is_string($statusText) || mb_strlen($statusText) > 500)) {
                    throw ValidationException::withMessages(['blocks' => 'Contact form status text is too long.']);
                }
            }
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
                'published' => self::listItemPublished($item),
                'title' => $this->nullableTrimmedString($item['title'] ?? null),
                'context' => self::listItemContext($item),
                'body' => self::listItemDetails($item),
            ];
        }, $items);
    }

    /** @param array<string, mixed> $block
     * @return list<array<string, mixed>>
     */
    private function normalizeContactChildren(array $block): array
    {
        $children = $block['children'] ?? null;
        if (! is_array($children) || ! array_is_list($children)) {
            return [];
        }

        $normalized = [];
        $seen = [];
        foreach ($children as $child) {
            if (! is_array($child)) {
                continue;
            }
            $type = $child['type'] ?? null;
            if (! is_string($type) || ! in_array($type, self::CONTACT_CHILD_TYPES, true) || isset($seen[$type])) {
                continue;
            }
            $seen[$type] = true;
            $published = self::contactChildPublished($child);
            $normalized[] = match ($type) {
                'public_email' => ['type' => 'public_email', 'published' => $published],
                'social_links' => [
                    'type' => 'social_links',
                    'published' => $published,
                    'social_platforms' => array_values(array_unique(array_filter(
                        is_array($child['social_platforms'] ?? null) ? $child['social_platforms'] : [],
                        static fn (mixed $platform): bool => is_string($platform),
                    ))),
                ],
                'contact_form' => [
                    'type' => 'contact_form',
                    'published' => $published,
                    'form_state' => ($child['form_state'] ?? 'enabled') === 'under_construction' ? 'under_construction' : 'enabled',
                    'status_text' => $this->nullableTrimmedString($child['status_text'] ?? null),
                ],
            };
        }

        return $normalized;
    }

    private function nullableTrimmedString(mixed $value): ?string
    {
        if (! is_string($value)) {
            return null;
        }
        $value = trim($value);

        return $value === '' ? null : $value;
    }

    private function validateRichText(mixed $value, string $field, bool $requirePublicMedia): void
    {
        if ($value === null || $value === '') {
            return;
        }
        if (! is_string($value)) {
            throw ValidationException::withMessages([$field => 'Component rich text must be text.']);
        }

        app(SafeRichTextRenderer::class)->assertValid($value, allowEmbeddedMedia: true, requirePublicMedia: $requirePublicMedia);
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
