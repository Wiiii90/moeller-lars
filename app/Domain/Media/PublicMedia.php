<?php

namespace App\Domain\Media;

use App\Domain\Content\HomeTemplate;
use App\Domain\Content\JournalTemplate;
use App\Domain\Content\RichTextMediaReference;
use App\Domain\Content\SiteNodeType;
use App\Domain\Content\SitePreviewContext;
use App\Models\Artwork;
use App\Models\ArtworkMedia;
use App\Models\BlogPost;
use App\Models\CustomPageSetting;
use App\Models\CvEntry;
use App\Models\Exhibition;
use App\Models\HomePresentationSetting;
use App\Models\JournalEntryMedia;
use App\Models\MediaAsset;
use App\Models\MediaVariant;
use App\Models\PublicContentSetting;
use Illuminate\Database\Eloquent\Collection;
use LogicException;

class PublicMedia
{
    public const THUMBNAIL_KIND = 'thumbnail';
    public const PUBLIC_TRANSFORM_PROFILE = 'public-v1';

    public function __construct(private readonly SitePreviewContext $preview) {}

    public function isPublicAsset(MediaAsset $asset): bool
    {
        if ($asset->getAttribute('state') !== 'available') {
            return false;
        }

        if (PublicContentSetting::query()->where('favicon_media_asset_id', $asset->getKey())->exists()) {
            return true;
        }

        if (ArtworkMedia::query()
            ->where('media_asset_id', $asset->getKey())
            ->where('role', 'primary')
            ->whereHas('artwork', fn ($query) => $query
                ->where('state', 'published')
                ->whereHas('category.siteSection', fn ($section) => $section->where('state', 'published')))
            ->exists()) {
            return true;
        }

        if ($this->publishedCustomPageReferencesAsset((int) $asset->getKey())) {
            return true;
        }

        if ($this->activeHomeReferencesAsset((int) $asset->getKey())) {
            return true;
        }

        if ($this->publishedJournalRichTextReferencesAsset((int) $asset->getKey())) {
            return true;
        }

        return JournalEntryMedia::query()
            ->where('media_asset_id', $asset->getKey())
            ->where(function ($usage): void {
                $usage->whereHas('blogPost', fn ($posts) => $posts
                    ->publiclyVisible()
                    ->whereHas('siteSection', fn ($section) => $section
                        ->where('type', SiteNodeType::Journal->value)
                        ->where('template', JournalTemplate::Blog->value)
                        ->where('state', 'published')))
                    ->orWhereHas('exhibition', fn ($entries) => $entries
                        ->where('state', 'published')
                        ->whereHas('siteSection', fn ($section) => $section
                            ->where('type', SiteNodeType::Journal->value)
                            ->where('template', JournalTemplate::Exhibitions->value)
                            ->where('state', 'published')));
            })
            ->exists();
    }

    public function isPublicVariant(MediaVariant $variant): bool
    {
        if ($variant->getAttribute('state') !== 'available') {
            return false;
        }

        $asset = $variant->getRelationValue('mediaAsset');

        return $asset instanceof MediaAsset && $this->isPublicAsset($asset);
    }

    public function primaryMedia(Artwork $artwork): ArtworkMedia
    {
        /** @var Collection<int, ArtworkMedia> $rows */
        $rows = $artwork->getRelationValue('artworkMedia');
        $primaries = $rows->filter(fn (ArtworkMedia $row): bool => $row->getAttribute('role') === 'primary')->values();

        if ($primaries->count() !== 1) {
            throw new LogicException('Published artwork must have exactly one primary media usage.');
        }

        return $primaries->first();
    }

    public function primaryAsset(Artwork $artwork): MediaAsset
    {
        $asset = $this->primaryMedia($artwork)->getRelationValue('mediaAsset');
        if (! $asset instanceof MediaAsset) {
            throw new LogicException('Published artwork requires an available primary media asset.');
        }

        $this->assertAvailable($asset);

        return $asset;
    }

    public function isVideo(Artwork $artwork): bool
    {
        return MediaTypePolicy::isVideo((string) $this->primaryAsset($artwork)->getAttribute('mime_type'));
    }

    public function isImage(Artwork $artwork): bool
    {
        return MediaTypePolicy::isImage((string) $this->primaryAsset($artwork)->getAttribute('mime_type'));
    }

    public function kind(Artwork $artwork): string
    {
        return MediaTypePolicy::kind((string) $this->primaryAsset($artwork)->getAttribute('mime_type'));
    }

    public function mimeType(Artwork $artwork): string
    {
        return (string) $this->primaryAsset($artwork)->getAttribute('mime_type');
    }

    public function altText(Artwork $artwork): string
    {
        $usage = $this->primaryMedia($artwork);

        return $this->altTextForAsset($this->primaryAsset($artwork), $usage->getAttribute('alt_text_override'));
    }

    public function thumbnailVariant(Artwork $artwork): MediaVariant
    {
        if (! $this->isImage($artwork)) {
            throw new LogicException('Only image primary media has a public thumbnail variant.');
        }

        return $this->thumbnailVariantForAsset($this->primaryAsset($artwork));
    }

    public function thumbnailUrl(Artwork $artwork): string
    {
        return $this->variantUrl($this->thumbnailVariant($artwork));
    }

    public function originalUrl(Artwork $artwork): string
    {
        return $this->originalUrlForAsset($this->primaryAsset($artwork));
    }

    public function altTextForAsset(MediaAsset $asset, mixed $override = null): string
    {
        if ($override !== null) {
            if (! is_string($override) || trim($override) === '' || mb_strlen($override) > 500) {
                throw new LogicException('Media ALT override must be non-empty text of at most 500 characters when provided.');
            }

            return trim($override);
        }

        $alt = $asset->getAttribute('alt_text');
        if (! is_string($alt) || trim($alt) === '') {
            throw new LogicException('Public media requires explicit ALT text.');
        }

        return trim($alt);
    }

    public function thumbnailVariantForAsset(MediaAsset $asset): MediaVariant
    {
        $this->assertAvailable($asset);
        $asset->loadMissing('variants');
        $matching = $asset->getRelationValue('variants')->filter(
            fn (MediaVariant $variant): bool => $variant->getAttribute('variant_kind') === self::THUMBNAIL_KIND
                && $variant->getAttribute('transform_profile') === self::PUBLIC_TRANSFORM_PROFILE
                && $variant->getAttribute('state') === 'available',
        )->values();

        if ($matching->count() !== 1) {
            throw new LogicException('Public media requires exactly one available public thumbnail.');
        }

        return $matching->first();
    }

    public function thumbnailUrlForAsset(MediaAsset $asset): string
    {
        return $this->variantUrl($this->thumbnailVariantForAsset($asset));
    }

    public function variantUrl(MediaVariant $variant): string
    {
        return $this->preview->active()
            ? route('preview.media.variant', ['mediaVariant' => $variant])
            : route('media.variant', $variant);
    }

    public function originalUrlForAsset(MediaAsset $asset): string
    {
        $this->assertAvailable($asset);

        return $this->preview->active()
            ? route('preview.media.original', ['mediaAsset' => $asset])
            : route('media.original', $asset);
    }

    private function publishedCustomPageReferencesAsset(int $mediaAssetId): bool
    {
        $publishedCustomPages = CustomPageSetting::query()
            ->whereHas('siteSection', fn ($query) => $query
                ->where('type', SiteNodeType::CustomPage->value)
                ->where('state', 'published'))
            ->get(['id', 'blocks']);

        $publishedCvListExists = false;
        foreach ($publishedCustomPages as $settings) {
            foreach ($settings->components() as $block) {
                if (! is_array($block) || ! CustomPageSetting::componentPublished($block)) {
                    continue;
                }

                $type = $block['type'] ?? null;
                if ($type === 'image'
                    && is_numeric($block['media_asset_id'] ?? null)
                    && (int) $block['media_asset_id'] === $mediaAssetId) {
                    return true;
                }

                if ($type === 'text'
                    && is_string($block['body'] ?? null)
                    && in_array($mediaAssetId, RichTextMediaReference::ids($block['body']), true)) {
                    return true;
                }

                if ($type === 'list' && is_array($block['items'] ?? null)) {
                    foreach ($block['items'] as $item) {
                        if (! is_array($item) || ! CustomPageSetting::listItemPublished($item)) {
                            continue;
                        }
                        if (is_string($item['body'] ?? null)
                            && in_array($mediaAssetId, RichTextMediaReference::ids($item['body']), true)) {
                            return true;
                        }
                    }
                }

                if ($type === 'cv_list') {
                    $publishedCvListExists = true;
                }
            }
        }

        if (! $publishedCvListExists) {
            return false;
        }

        if (CvEntry::query()
            ->where('state', 'published')
            ->where('image_media_asset_id', $mediaAssetId)
            ->exists()) {
            return true;
        }

        foreach (CvEntry::query()->where('state', 'published')->whereNotNull('body')->pluck('body') as $body) {
            if (is_string($body) && in_array($mediaAssetId, RichTextMediaReference::ids($body), true)) {
                return true;
            }
        }

        return false;
    }

    private function activeHomeReferencesAsset(int $mediaAssetId): bool
    {
        /** @var HomePresentationSetting|null $settings */
        $settings = HomePresentationSetting::query()
            ->whereHas('siteSection', fn ($query) => $query->where('type', SiteNodeType::Home->value))
            ->first(['id', 'template', 'configuration']);
        if (! $settings instanceof HomePresentationSetting) {
            return false;
        }

        $template = $settings->template();
        if (! in_array($template, [HomeTemplate::UnderConstruction, HomeTemplate::Custom], true)) {
            return false;
        }

        foreach ($settings->components($template) as $component) {
            if (($component['type'] ?? null) === 'image'
                && is_numeric($component['media_asset_id'] ?? null)
                && (int) $component['media_asset_id'] === $mediaAssetId) {
                return true;
            }

            if (($component['type'] ?? null) === 'text'
                && is_string($component['body'] ?? null)
                && in_array($mediaAssetId, RichTextMediaReference::ids($component['body']), true)) {
                return true;
            }
        }

        return false;
    }

    private function publishedJournalRichTextReferencesAsset(int $mediaAssetId): bool
    {
        $blogBodies = BlogPost::query()
            ->publiclyVisible()
            ->whereNotNull('body')
            ->whereHas('siteSection', fn ($section) => $section
                ->where('type', SiteNodeType::Journal->value)
                ->where('template', JournalTemplate::Blog->value)
                ->where('state', 'published'))
            ->pluck('body');

        foreach ($blogBodies as $body) {
            if (is_string($body) && in_array($mediaAssetId, RichTextMediaReference::ids($body), true)) {
                return true;
            }
        }

        $descriptions = Exhibition::query()
            ->where('state', 'published')
            ->whereNotNull('description')
            ->whereHas('siteSection', fn ($section) => $section
                ->where('type', SiteNodeType::Journal->value)
                ->where('template', JournalTemplate::Exhibitions->value)
                ->where('state', 'published'))
            ->pluck('description');

        foreach ($descriptions as $description) {
            if (is_string($description) && in_array($mediaAssetId, RichTextMediaReference::ids($description), true)) {
                return true;
            }
        }

        return false;
    }

    private function assertAvailable(MediaAsset $asset): void
    {
        if ($asset->getAttribute('state') !== 'available') {
            throw new LogicException('Public media requires an available media asset.');
        }
    }
}
