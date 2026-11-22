<?php

namespace App\Models;

use App\Domain\Content\DividerVariant;
use App\Domain\Content\SafeRichTextRenderer;
use App\Domain\Content\SocialLinks;
use App\Domain\Media\MediaPreviewVariant;
use App\Domain\Media\MediaTypePolicy;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Guarded;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Validation\ValidationException;
use Illuminate\Support\Str;

#[Fillable(['blocks'])]
#[Guarded(['id', 'site_section_id'])]
final class CustomPageSetting extends Model
{
    public const COMPONENT_TYPES = [
        'image',
        'video',
        'text',
        'layout',
        'gallery',
        'hero_artworks',
        'exhibitions',
        'blog',
        'contact',
        'legal_disclaimer',
    ];

    /** Media kinds share the same canonical asset lifecycle. */
    public const MEDIA_COMPONENT_TYPES = ['image', 'video'];

    public const EMBEDDED_COMPONENT_TYPES = ['gallery', 'hero_artworks', 'exhibitions', 'blog'];

    public const LAYOUT_GAPS = ['none', 'small', 'medium', 'large'];

    public const LAYOUT_COLUMN_LAYOUTS = ['equal', 'left-wide', 'right-wide'];

    public const LAYOUT_MODES = ['rows', 'columns'];

    public const IMAGE_ALIGNMENTS = ['left', 'center', 'right'];

    public const CONTACT_CHILD_TYPES = [
        'public_email',
        'social_links',
        'contact_form',
    ];

    public const DIVIDER_VARIANTS = DividerVariant::VALUES;

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

    /**
     * Project stored content into layout areas without duplicating data ownership.
     * Each projected block retains its root index for the existing Preview anchors.
     *
     * @param list<array<string, mixed>> $blocks
     * @return list<array<string, mixed>>
     */
    public static function layoutProjection(array $blocks): array
    {
        $areas = [];
        foreach ($blocks as $block) {
            if (($block['type'] ?? null) !== 'layout') {
                continue;
            }
            foreach ($block['areas'] ?? [] as $area) {
                if (is_array($area) && is_string($area['id'] ?? null)) {
                    // Nested Blade includes may receive an already-projected
                    // Layout. Preserve its descendants instead of replacing
                    // those assigned blocks with an empty cell.
                    $areas[$area['id']] = is_array($area['blocks'] ?? null)
                        ? $area['blocks']
                        : [];
                }
            }
        }

        $roots = [];
        foreach ($blocks as $index => $block) {
            if (! is_array($block)) {
                continue;
            }
            $block['_root_index'] ??= $index;
            $areaId = $block['layout_area_id'] ?? null;
            if (is_string($areaId) && array_key_exists($areaId, $areas)) {
                $areas[$areaId][] = $block;
            } else {
                $roots[] = $block;
            }
        }

        $expand = static function (array $block) use (&$expand, $areas): array {
            if (($block['type'] ?? null) !== 'layout') {
                return $block;
            }
            $block['areas'] = array_map(static fn (array $area): array => [
                ...$area,
                'blocks' => array_map($expand, $areas[$area['id']] ?? []),
            ], $block['areas']);

            return $block;
        };

        return array_map($expand, $roots);
    }

    /**
     * Author new layout areas in the existing component document. Resizing
     * reuses every surviving stable cell ID; deleting a referenced cell is
     * rejected by document validation.
     *
     * @param array<string, mixed>|null $existing
     * @return array<string, mixed>
     */
    public static function layoutBlock(bool $published, int $columns = 2, int $rows = 1, string $gap = 'medium', ?array $existing = null, string $columnLayout = 'equal', string $layoutMode = 'rows'): array
    {
        if ($columns < 1 || $columns > 4 || $rows < 1 || $rows > 12
            || ! in_array($gap, self::LAYOUT_GAPS, true)
            || ! in_array($columnLayout, self::LAYOUT_COLUMN_LAYOUTS, true)
            || ! in_array($layoutMode, self::LAYOUT_MODES, true)
            || ($layoutMode === 'columns' && $rows !== 1)) {
            throw ValidationException::withMessages(['columns' => 'Choose one to four columns and one to twelve rows.']);
        }

        $count = $columns * $rows;
        $areas = array_slice(is_array($existing['areas'] ?? null) ? $existing['areas'] : [], 0, $count);
        while (count($areas) < $count) {
            $areas[] = ['id' => (string) Str::uuid(), 'border' => 'none'];
        }

        return [
            'type' => 'layout',
            'published' => $published,
            'id' => $existing['id'] ?? (string) Str::uuid(),
            'columns' => $columns,
            'layout_mode' => $layoutMode,
            'rows' => $rows,
            'gap' => $gap,
            'column_layout' => $columns === 2 ? $columnLayout : 'equal',
            'areas' => $areas,
        ];
    }

    /** A missing or malformed historical value must never break public layout. */
    public static function imageScalePercent(array $block): int
    {
        $value = $block['image_scale_percent'] ?? self::DEFAULT_IMAGE_SCALE_PERCENT;

        return is_int($value) && $value >= self::MIN_IMAGE_SCALE_PERCENT && $value <= self::MAX_IMAGE_SCALE_PERCENT
            ? $value
            : self::DEFAULT_IMAGE_SCALE_PERCENT;
    }

    /** Historical images default to left without storing a redundant value. */
    public static function imageAlignment(array $block): string
    {
        $alignment = $block['image_alignment'] ?? 'left';

        return is_string($alignment) && in_array($alignment, self::IMAGE_ALIGNMENTS, true) ? $alignment : 'left';
    }

    public static function videoControls(array $block): bool
    {
        return ($block['video_controls_enabled'] ?? true) !== false;
    }

    public static function componentPublished(array $block): bool
    {
        return ($block['published'] ?? true) === true;
    }

    /**
     * A Layout cannot be removed while it still owns page components.
     * Supply the remaining component sequence for an exact deletion check.
     *
     * @param list<array<string, mixed>> $remaining
     */
    public static function canDeleteComponent(array $component, array $remaining = []): bool
    {
        if (self::componentPublished($component)) {
            return false;
        }
        if (($component['type'] ?? null) !== 'layout') {
            return true;
        }

        $areaIds = array_column($component['areas'] ?? [], 'id');
        foreach ($remaining as $other) {
            if (is_array($other) && in_array($other['layout_area_id'] ?? null, $areaIds, true)) {
                return false;
            }
        }

        return true;
    }

    /** Reused by Home and Custom Page mutation owners, including bulk actions. */
    public static function assertComponentDeletable(array $component, array $remaining = []): void
    {
        if (self::componentPublished($component)) {
            throw ValidationException::withMessages(['component' => 'Unpublish the component before deleting it.']);
        }
        if (! self::canDeleteComponent($component, $remaining)) {
            throw ValidationException::withMessages([
                'component' => 'Move or delete the components inside this Layout before deleting the Layout.',
            ]);
        }
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
    public function normalizedBlocks(): array
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

            // A historical checkpoint can reintroduce entry-ID based embedding
            // after the database's forward migration. Reject that state instead
            // of silently dropping the artist's old selections on save.
            if (in_array($type, self::EMBEDDED_COMPONENT_TYPES, true)
                && ! array_key_exists('source_page_id', $block)
                && array_intersect(
                    ['gallery_id', 'gallery_ids', 'exhibition_ids', 'post_ids'],
                    array_keys($block),
                ) !== []) {
                throw ValidationException::withMessages([
                    'blocks' => 'Historical embedded content must be migrated to a whole source page before editing.',
                ]);
            }

            $published = self::componentPublished($block);
            $canonical = match ($type) {
                'image', 'video' => [
                    'type' => $type,
                    'published' => $published,
                    'media_asset_id' => is_numeric($block['media_asset_id'] ?? null) ? (int) $block['media_asset_id'] : null,
                    'image_decorative' => (bool) ($block['image_decorative'] ?? false),
                    ...($this->validatedImageAlignment($block) !== 'left' ? ['image_alignment' => $this->validatedImageAlignment($block)] : []),
                    ...($type === 'video' && ! self::videoControls($block) ? ['video_controls_enabled' => false] : []),
                    ...$this->normalizedImageScale($block),
                ],
                'layout' => [
                    'type' => 'layout',
                    'published' => $published,
                    'id' => $block['id'] ?? null,
                    'columns' => $block['columns'] ?? 2,
                    'layout_mode' => $block['layout_mode'] ?? 'rows',
                    'rows' => $block['rows'] ?? 1,
                    'gap' => $block['gap'] ?? 'medium',
                    'column_layout' => $block['column_layout'] ?? 'equal',
                    'areas' => $this->normalizedLayoutAreas($block),
                ],
                'gallery' => [
                    'type' => 'gallery',
                    'published' => $published,
                    'source_page_id' => is_numeric($block['source_page_id'] ?? null) ? (int) $block['source_page_id'] : null,
                    'gallery_presentation' => $block['gallery_presentation'] ?? 'sequence',
                ],
                'hero_artworks', 'exhibitions', 'blog' => [
                    'type' => $type,
                    'published' => $published,
                    'source_page_id' => is_numeric($block['source_page_id'] ?? null) ? (int) $block['source_page_id'] : null,
                ],
                'text' => [
                    'type' => 'text',
                    'published' => $published,
                    'title' => $this->nullableTrimmedString($block['title'] ?? null),
                    'body' => $block['body'] ?? null,
                ],
                'contact' => [
                    'type' => 'contact',
                    'published' => $published,
                    'title' => $this->nullableTrimmedString($block['title'] ?? null),
                    'children' => $this->normalizeContactChildren($block),
                ],
                'legal_disclaimer' => ['type' => 'legal_disclaimer', 'published' => $published],
            };
            if (array_key_exists('layout_area_id', $block) && $block['layout_area_id'] !== null) {
                $canonical['layout_area_id'] = $block['layout_area_id'];
            }
            if (array_key_exists('layout_divider_before', $block) && $block['layout_divider_before'] !== null) {
                $canonical['layout_divider_before'] = $block['layout_divider_before'];
            }
            $normalized[] = $canonical;
        }

        return $normalized;
    }

    /** @return list<array<string, mixed>> */
    private function normalizedLayoutAreas(array $layout): array
    {
        $areas = $layout['areas'] ?? [];
        if (! is_array($areas) || ! array_is_list($areas)) {
            throw ValidationException::withMessages(['blocks' => 'Layout areas must be an ordered list.']);
        }

        return array_map(static function (mixed $area): array {
            if (! is_array($area)) {
                throw ValidationException::withMessages(['blocks' => 'Layout cells must be structured data.']);
            }

            return ['id' => $area['id'] ?? null, 'border' => $area['border'] ?? 'none'];
        }, $areas);
    }

    private function validateLayoutComponent(array $block): void
    {
        $id = $block['id'] ?? null;
        $columns = $block['columns'] ?? null;
        $rows = $block['rows'] ?? null;
        $gap = $block['gap'] ?? null;
        $columnLayout = $block['column_layout'] ?? 'equal';
        $layoutMode = $block['layout_mode'] ?? 'rows';
        if (! in_array($layoutMode, self::LAYOUT_MODES, true)
            || ($layoutMode === 'columns' && $rows !== 1)
            || ! in_array($columnLayout, self::LAYOUT_COLUMN_LAYOUTS, true)
            || ($columns !== 2 && $columnLayout !== 'equal')) {
            throw ValidationException::withMessages(['blocks' => 'Layout column proportions are invalid.']);
        }
        if (! is_string($id) || ! preg_match('/^[a-z0-9-]{8,64}$/i', $id)
            || ! is_int($columns) || $columns < 1 || $columns > 4
            || ! is_int($rows) || $rows < 1 || $rows > 12
            || ! in_array($gap, self::LAYOUT_GAPS, true)) {
            throw ValidationException::withMessages(['blocks' => 'Layout configuration is invalid.']);
        }

        $areas = $block['areas'] ?? null;
        if (! is_array($areas) || ! array_is_list($areas) || count($areas) !== $columns * $rows) {
            throw ValidationException::withMessages(['blocks' => 'Layout areas must match the number of columns and rows.']);
        }
        $seen = [];
        foreach ($areas as $area) {
            $areaId = is_array($area) ? ($area['id'] ?? null) : null;
            $border = is_array($area) ? ($area['border'] ?? null) : null;
            if (! is_string($areaId) || ! preg_match('/^[a-z0-9-]{8,64}$/i', $areaId)
                || isset($seen[$areaId])
                || ! in_array($border, ['none', ...self::DIVIDER_VARIANTS], true)) {
                throw ValidationException::withMessages(['blocks' => 'Layout area identity or border is invalid.']);
            }
            $seen[$areaId] = true;
        }
    }

    private function validatedImageAlignment(array $block): string
    {
        $alignment = $block['image_alignment'] ?? 'left';
        if (! is_string($alignment) || ! in_array($alignment, self::IMAGE_ALIGNMENTS, true)) {
            throw ValidationException::withMessages(['blocks' => 'Image alignment must be Left, Center or Right.']);
        }

        return $alignment;
    }

    /**
     * Keep the historical 100% size implicit so an unchanged legacy image
     * remains a no-op when its component editor is opened and submitted.
     *
     * @param  array<string, mixed>  $block
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
        $layoutAreas = [];
        $layoutOwners = [];
        $layoutBlocks = [];
        foreach ($blocks as $layoutBlock) {
            if (! is_array($layoutBlock) || ($layoutBlock['type'] ?? null) !== 'layout') {
                continue;
            }
            $this->validateLayoutComponent($layoutBlock);
            if (isset($layoutBlocks[$layoutBlock['id']])) {
                throw ValidationException::withMessages(['blocks' => 'Layout IDs must be unique across the page.']);
            }
            $layoutBlocks[$layoutBlock['id']] = $layoutBlock;
            foreach ($layoutBlock['areas'] as $area) {
                $areaId = $area['id'];
                if (isset($layoutAreas[$areaId])) {
                    throw ValidationException::withMessages(['blocks' => 'Layout area IDs must be unique.']);
                }
                $layoutAreas[$areaId] = true;
                $layoutOwners[$areaId] = $layoutBlock['id'];
            }
        }

        // Parent chains must be finite, even if a cycle is unpublished.
        foreach ($layoutBlocks as $layoutId => $layoutBlock) {
            $seen = [$layoutId => true];
            $parent = $layoutBlock['layout_area_id'] ?? null;
            while (is_string($parent) && isset($layoutOwners[$parent])) {
                $ownerId = $layoutOwners[$parent];
                if (isset($seen[$ownerId])) {
                    throw ValidationException::withMessages(['blocks' => 'Layout layouts cannot contain themselves.']);
                }
                $seen[$ownerId] = true;
                $parent = $layoutBlocks[$ownerId]['layout_area_id'] ?? null;
            }
        }

        $areaVisible = static function (string $areaId) use ($layoutOwners, $layoutBlocks): bool {
            $ownerId = $layoutOwners[$areaId] ?? null;
            while (is_string($ownerId) && isset($layoutBlocks[$ownerId])) {
                $layout = $layoutBlocks[$ownerId];
                if (! self::componentPublished($layout)) {
                    return false;
                }
                $parentId = $layout['layout_area_id'] ?? null;
                $ownerId = is_string($parentId) ? ($layoutOwners[$parentId] ?? null) : null;
            }

            return true;
        };

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

            $areaId = $block['layout_area_id'] ?? null;
            if ($areaId !== null && (! is_string($areaId) || ! isset($layoutAreas[$areaId]))) {
                throw ValidationException::withMessages(['blocks' => 'Choose a valid Layout area for this component.']);
            }
            $divider = $block['layout_divider_before'] ?? 'none';
            if (! is_string($divider) || ! in_array($divider, ['none', ...self::DIVIDER_VARIANTS], true)) {
                throw ValidationException::withMessages(['blocks' => 'Choose a supported layout separator.']);
            }

            $public = $requirePublicMedia && self::componentPublished($block)
                && ($areaId === null || $areaVisible($areaId));

            if ($type === 'layout') {
                continue;
            }
            if (in_array($type, self::EMBEDDED_COMPONENT_TYPES, true)) {
                app(\App\Domain\Content\CustomPageEmbeddedContent::class)->assertValid($block, $public);
                continue;
            }
            if (in_array($type, self::MEDIA_COMPONENT_TYPES, true)) {
                if (array_key_exists('children', $block)) {
                    throw ValidationException::withMessages(['blocks' => 'Move older media children into Layout areas before editing this page.']);
                }
                $this->validateMediaComponent($block, $public);
            }
            if ($type === 'text') {
                $title = $block['title'] ?? null;
                if ($title !== null && (! is_string($title) || mb_strlen($title) > 160)) {
                    throw ValidationException::withMessages(['blocks' => 'Text component headings must be short text.']);
                }
                $this->validateRichText($block['body'] ?? null, 'blocks.'.$blockIndex.'.body', $public);
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
                    throw ValidationException::withMessages(['blocks' => 'A page can contain at most one Legal Disclaimer component.']);
                }
            }
        }
        if ($legalDisclaimerCount > 1) {
            throw ValidationException::withMessages(['blocks' => 'A page can contain at most one Legal Disclaimer component.']);
        }
    }

    /** @param array<string, mixed> $block */
    private function validateMediaComponent(array $block, bool $requirePublicMedia): void
    {
        $this->validatedImageAlignment($block);
        if (array_key_exists('video_controls_enabled', $block) && ! is_bool($block['video_controls_enabled'])) {
            throw ValidationException::withMessages(['blocks' => 'Video playback controls must be enabled or disabled.']);
        }
        $scale = $block['image_scale_percent'] ?? self::DEFAULT_IMAGE_SCALE_PERCENT;
        if (! is_int($scale) || $scale < self::MIN_IMAGE_SCALE_PERCENT || $scale > self::MAX_IMAGE_SCALE_PERCENT) {
            throw ValidationException::withMessages(['blocks' => 'Image size must be a whole percentage between 25 and 200.']);
        }

        $mediaId = $block['media_asset_id'] ?? null;
        if ($mediaId === null) {
            if ($requirePublicMedia) {
                throw ValidationException::withMessages(['blocks' => 'Published media components must reference an image or video from Storage.']);
            }

            return;
        }

        if (filter_var($mediaId, FILTER_VALIDATE_INT) === false) {
            throw ValidationException::withMessages(['blocks' => 'Media components must reference an image or video from Storage.']);
        }

        /** @var MediaAsset|null $asset */
        $asset = MediaAsset::query()->find((int) $mediaId);
        $type = $block['type'] ?? null;
        $mimeType = $asset instanceof MediaAsset ? (string) $asset->getAttribute('mime_type') : '';
        if (! $asset instanceof MediaAsset
            || ($type === 'image' && ! MediaTypePolicy::isImage($mimeType))
            || ($type === 'video' && ! MediaTypePolicy::isVideo($mimeType))) {
            throw ValidationException::withMessages(['blocks' => 'Choose a Storage asset matching the component media type.']);
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
            throw ValidationException::withMessages(['blocks' => 'Published custom-page media must be available.']);
        }
        if (! $decorative && (! is_string($alt) || trim($alt) === '')) {
            throw ValidationException::withMessages(['blocks' => 'Published non-decorative media must have canonical ALT text in Storage.']);
        }
        if (MediaTypePolicy::isImage((string) $asset->getAttribute('mime_type'))
            && MediaPreviewVariant::imageThumbnail($asset) === null) {
            throw ValidationException::withMessages(['blocks' => 'Published Custom Page images require one available public thumbnail.']);
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
        }
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
}
