<?php

namespace App\Domain\Media;

use App\Domain\Blog\BlogEditorialService;
use App\Domain\Content\HomeTemplate;
use App\Domain\Content\JournalTemplate;
use App\Domain\Content\SiteNodeType;
use App\Models\Artwork;
use App\Models\ArtworkMedia;
use App\Models\CustomPageSetting;
use App\Models\ExhibitionMedia;
use App\Models\HomePresentationSetting;
use App\Models\MediaAsset;
use App\Models\MediaVariant;
use App\Models\PublicContentSetting;
use Illuminate\Database\Eloquent\Collection;
use LogicException;

class PublicMedia
{
    public const THUMBNAIL_KIND = 'thumbnail';

    public const PUBLIC_TRANSFORM_PROFILE = 'public-v1';

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

        if (CustomPageSetting::query()
            ->whereHas('siteSection', fn ($query) => $query
                ->where('type', SiteNodeType::CustomPage->value)
                ->where('state', 'published'))
            ->whereRaw('blocks @> ?::jsonb', [json_encode([['media_asset_id' => (int) $asset->getKey()]], JSON_THROW_ON_ERROR)])
            ->exists()) {
            return true;
        }

        if ($this->activeHomeReferencesAsset((int) $asset->getKey())) {
            return true;
        }

        if (ExhibitionMedia::query()
            ->where('media_asset_id', $asset->getKey())
            ->whereHas('exhibition', fn ($query) => $query
                ->where('state', 'published')
                ->whereHas('siteSection', fn ($section) => $section
                    ->where('type', SiteNodeType::Journal->value)
                    ->where('template', JournalTemplate::Exhibitions->value)
                    ->where('state', 'published')))
            ->exists()) {
            return true;
        }

        return BlogEditorialService::publicQuery()
            ->where('cover_media_asset_id', $asset->getKey())
            ->whereHas('siteSection', fn ($section) => $section
                ->where('type', SiteNodeType::Journal->value)
                ->where('template', JournalTemplate::Blog->value)
                ->where('state', 'published'))
            ->exists();
    }

    public function isPublicVariant(MediaVariant $variant): bool
    {
        if ($variant->getAttribute('state') !== 'available') {
            return false;
        }

        /** @var MediaAsset|null $asset */
        $asset = $variant->getRelationValue('mediaAsset');

        return $asset !== null && $this->isPublicAsset($asset);
    }

    public function primaryMedia(Artwork $artwork): ArtworkMedia
    {
        /** @var Collection<int, ArtworkMedia> $mediaRows */
        $mediaRows = $artwork->getRelationValue('artworkMedia');
        $primaries = $mediaRows->filter(
            static fn (ArtworkMedia $media): bool => $media->getAttribute('role') === 'primary',
        )->values();

        if ($primaries->count() !== 1) {
            throw new LogicException('Published artwork must have exactly one primary media usage.');
        }

        /** @var ArtworkMedia $primary */
        $primary = $primaries->first();

        return $primary;
    }

    public function primaryAsset(Artwork $artwork): MediaAsset
    {
        $asset = $this->primaryMedia($artwork)->getRelationValue('mediaAsset');
        if (($asset instanceof MediaAsset) === false) {
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
        $media = $this->primaryMedia($artwork);

        return $this->altTextForAsset($this->primaryAsset($artwork), $media->getAttribute('alt_text_override'));
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
        return route('media.variant', $this->thumbnailVariant($artwork));
    }

    public function originalUrl(Artwork $artwork): string
    {
        return $this->originalUrlForAsset($this->primaryAsset($artwork));
    }

    public function altTextForAsset(MediaAsset $asset, mixed $override = null): string
    {
        if ($override !== null) {
            if (is_string($override) === false || trim($override) === '') {
                throw new LogicException('Media ALT override must be non-empty text when provided.');
            }

            return $override;
        }

        $altText = $asset->getAttribute('alt_text');
        if (is_string($altText) === false || trim($altText) === '') {
            throw new LogicException('Public media requires explicit ALT text.');
        }

        return $altText;
    }

    public function thumbnailVariantForAsset(MediaAsset $asset): MediaVariant
    {
        $this->assertAvailable($asset);
        $asset->loadMissing('variants');

        /** @var Collection<int, MediaVariant> $variants */
        $variants = $asset->getRelationValue('variants');
        $matching = $variants->filter(fn (MediaVariant $variant): bool => $variant->getAttribute('variant_kind') === self::THUMBNAIL_KIND
            && $variant->getAttribute('transform_profile') === self::PUBLIC_TRANSFORM_PROFILE
            && $variant->getAttribute('state') === 'available'
        )->values();

        if ($matching->count() !== 1) {
            throw new LogicException('Public media requires exactly one available public thumbnail.');
        }

        /** @var MediaVariant $variant */
        $variant = $matching->first();

        return $variant;
    }

    public function thumbnailUrlForAsset(MediaAsset $asset): string
    {
        return route('media.variant', $this->thumbnailVariantForAsset($asset));
    }

    public function originalUrlForAsset(MediaAsset $asset): string
    {
        $this->assertAvailable($asset);

        return route('media.original', $asset);
    }

    private function activeHomeReferencesAsset(int $mediaAssetId): bool
    {
        /** @var HomePresentationSetting|null $settings */
        $settings = HomePresentationSetting::query()->first(['id', 'template', 'configuration']);
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