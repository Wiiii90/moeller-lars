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
final readonly class GalleryHeroCandidate
{
    private function __construct(
        public string $kind,
        public Artwork|MediaAsset $record,
        public ?int $sourceId = null,
        public ?string $sourceName = null,
        public ?string $titleOverride = null,
        public ?int $year = null,
        public ?int $month = null,
        public ?int $day = null,
        /** @var array<string,mixed> Hero-only metadata and visual overrides */
        public array $heroOverrides = [],
        public ?MediaAsset $heroMediaAsset = null,
    ) {}

    public static function fromArtwork(Artwork $artwork, ?int $sourceId = null, ?string $sourceName = null): self
    {
        return new self('artwork', $artwork, $sourceId, $sourceName);
    }

    public static function fromMedia(
        MediaAsset $asset,
        ?int $sourceId = null,
        ?string $sourceName = null,
        ?string $title = null,
        ?int $year = null,
        ?int $month = null,
        ?int $day = null,
    ): ?self
    {
        if (! self::mediaIsEligible($asset)) {
            return null;
        }

        return new self('media', $asset, $sourceId, $sourceName, $title, $year, $month, $day);
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

    /** @param array<string,mixed> $overrides */
    public function withHeroOverrides(array $overrides, ?MediaAsset $media = null): self
    {
        return new self(
            $this->kind, $this->record, $this->sourceId, $this->sourceName,
            $this->titleOverride, $this->year, $this->month, $this->day,
            $overrides, $media,
        );
    }

    public function key(): string
    {
        return $this->kind.':'.$this->record->getKey();
    }

    /** A displayed image may be referenced both by Artwork and direct Storage. */
    public function visualKey(): string
    {
        if ($this->heroMediaAsset instanceof MediaAsset) {
            return 'media:'.$this->heroMediaAsset->getKey();
        }
        if ($this->record instanceof MediaAsset) {
            return 'media:'.$this->record->getKey();
        }

        $this->record->loadMissing('artworkMedia');
        $primary = $this->record->getRelation('artworkMedia')->first(
            static fn ($usage): bool => $usage->getAttribute('role') === 'primary',
        );

        return $primary === null
            ? $this->key()
            : 'media:'.(int) $primary->getAttribute('media_asset_id');
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
        return ($this->heroOverrides['title'] ?? null) ?? $this->titleOverride ?? ($this->artwork() instanceof Artwork
            ? (string) $this->record->getAttribute('title')
            : (string) $this->record->getAttribute('original_filename'));
    }

    public function gallery(): ?ArtworkCategory
    {
        $artwork = $this->artwork();
        if ($artwork instanceof Artwork) {
            $artwork->loadMissing('category');
            $gallery = $artwork->getRelationValue('category');

            return $gallery instanceof ArtworkCategory ? $gallery : null;
        }

        // Dummy Gallery members do not inherit an unrelated public Gallery
        // merely because their Storage asset is reused by an Artwork.
        if ($this->sourceId !== null && $this->sourceId < 0) {
            return null;
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
