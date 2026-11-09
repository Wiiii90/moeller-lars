<?php

namespace App\Domain\Content;

use App\Domain\Media\MediaPreviewVariant;
use App\Domain\Media\MediaTypePolicy;
use App\Models\Artwork;
use App\Models\ArtworkCategory;
use App\Models\MediaAsset;
use App\Models\MediaVariant;

/**
 * A single typed Hero member, either an editorial Artwork or a directly
 * selected Storage file. Never synthesize an Artwork/Gallery for a loose file.
 */
final readonly class HomeHeroCandidate
{
    private function __construct(
        public string $kind,
        public Artwork|MediaAsset $record,
    ) {}

    public static function fromArtwork(Artwork $artwork): self
    {
        return new self('artwork', $artwork);
    }

    public static function fromMedia(MediaAsset $asset): ?self
    {
        if (! self::mediaIsEligible($asset)) {
            return null;
        }

        return new self('media', $asset);
    }

    public static function mediaIsEligible(MediaAsset $asset): bool
    {
        if ($asset->getAttribute('state') !== 'available') {
            return false;
        }
        $mime = (string) $asset->getAttribute('mime_type');
        $alt = trim((string) ($asset->getAttribute('alt_text') ?? ''));
        if ($alt === '' || mb_strlen($alt) > 500) {
            return false;
        }

        if (MediaTypePolicy::isVideo($mime)) {
            return true;
        }

        return MediaTypePolicy::isImage($mime)
            && MediaPreviewVariant::imageThumbnail($asset) instanceof MediaVariant;
    }

    public function key(): string
    {
        return $this->kind.':'.$this->record->getKey();
    }

    public function artwork(): ?Artwork
    {
        return $this->record instanceof Artwork ? $this->record : null;
    }

    public function mediaAsset(): ?MediaAsset
    {
        return $this->record instanceof MediaAsset ? $this->record : null;
    }

    public function title(): string
    {
        return $this->artwork() instanceof Artwork
            ? (string) $this->record->getAttribute('title')
            : (string) $this->record->getAttribute('original_filename');
    }

    public function gallery(): ?ArtworkCategory
    {
        $artwork = $this->artwork();
        if ($artwork instanceof Artwork) {
            $artwork->loadMissing('category');
            $gallery = $artwork->getRelationValue('category');

            return $gallery instanceof ArtworkCategory ? $gallery : null;
        }

        $asset = $this->mediaAsset();
        if (! $asset instanceof MediaAsset) {
            return null;
        }
        $asset->loadMissing('artworks.category');
        $linked = $asset->getRelation('artworks')->sortBy('id')->first();
        $gallery = $linked instanceof Artwork ? $linked->getRelationValue('category') : null;

        return $gallery instanceof ArtworkCategory ? $gallery : null;
    }
}
