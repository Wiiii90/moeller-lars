<?php

namespace App\Domain\Media;

use App\Domain\Blog\BlogEditorialService;
use App\Models\Artwork;
use App\Models\ArtworkMedia;
use App\Models\BlogSetting;
use App\Models\CvEntry;
use App\Models\ExhibitionMedia;
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

        if (ArtworkMedia::query()
            ->where('media_asset_id', $asset->getKey())
            ->where('role', 'primary')
            ->whereHas('artwork', fn ($query) => $query
                ->where('state', 'published')
                ->whereHas('category', fn ($categoryQuery) => $categoryQuery->where('state', 'published')))
            ->exists()) {
            return true;
        }

        $settings = PublicContentSetting::query()->findOrFail(1);
        if ((bool) $settings->getAttribute('cv_enabled')
            && CvEntry::query()->where('state', 'published')->where('image_media_asset_id', $asset->getKey())->exists()) {
            return true;
        }

        if ((bool) $settings->getAttribute('exhibitions_enabled')
            && ExhibitionMedia::query()
                ->where('media_asset_id', $asset->getKey())
                ->whereHas('exhibition', fn ($query) => $query->where('state', 'published'))
                ->exists()) {
            return true;
        }

        $blogSettings = BlogSetting::query()->findOrFail(1);
        return (bool) $blogSettings->getAttribute('public_enabled')
            && BlogEditorialService::publicQuery()
                ->where('cover_media_asset_id', $asset->getKey())
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

    public function altText(Artwork $artwork): string
    {
        $media = $this->primaryMedia($artwork);

        return $this->altTextForAsset($this->primaryAsset($artwork), $media->getAttribute('alt_text_override'));
    }

    public function thumbnailUrl(Artwork $artwork): string
    {
        return $this->thumbnailUrlForAsset($this->primaryAsset($artwork));
    }

    public function originalUrl(Artwork $artwork): string
    {
        return $this->originalUrlForAsset($this->primaryAsset($artwork));
    }

    public function altTextForAsset(MediaAsset $asset, mixed $override = null): string
    {
        if ($override !== null) {
            if (! is_string($override) || trim($override) === '') {
                throw new LogicException('Media ALT override must be non-empty text when provided.');
            }

            return $override;
        }

        $altText = $asset->getAttribute('alt_text');
        if (! is_string($altText) || trim($altText) === '') {
            throw new LogicException('Public media requires explicit ALT text.');
        }

        return $altText;
    }

    public function thumbnailUrlForAsset(MediaAsset $asset): string
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

        return route('media.variant', $variant);
    }

    public function originalUrlForAsset(MediaAsset $asset): string
    {
        $this->assertAvailable($asset);

        return route('media.original', $asset);
    }

    private function primaryAsset(Artwork $artwork): MediaAsset
    {
        $asset = $this->primaryMedia($artwork)->getRelationValue('mediaAsset');
        if (! $asset instanceof MediaAsset) {
            throw new LogicException('Published artwork requires an available primary media asset.');
        }

        $this->assertAvailable($asset);

        return $asset;
    }

    private function assertAvailable(MediaAsset $asset): void
    {
        if ($asset->getAttribute('state') !== 'available') {
            throw new LogicException('Public media requires an available media asset.');
        }
    }
}
