<?php

namespace App\Domain\Artwork;

use App\Domain\Admin\AdminAuditService;
use App\Domain\Media\MediaIngestService;
use App\Domain\Media\MediaTypePolicy;
use App\Models\Artwork;
use App\Models\ArtworkCategory;
use App\Models\ArtworkMedia;
use App\Models\MediaAsset;
use App\Models\SiteSection;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final class ArtworkPublicationService
{
    public function __construct(private readonly AdminAuditService $audit) {}

    public function publish(Artwork $artwork): Artwork
    {
        $actor = $this->audit->requireActor();
        /** @var Artwork $fresh */
        $fresh = Artwork::query()
            ->with(['category.siteSection', 'artworkMedia.mediaAsset.variants'])
            ->findOrFail($artwork->getKey());
        /** @var Collection<int, ArtworkMedia> $primaries */
        $primaries = $fresh->getRelation('artworkMedia')->where('role', 'primary')->values();
        $primary = $primaries->count() === 1 ? $primaries->first() : null;
        /** @var ArtworkCategory|null $category */
        $category = $fresh->getRelationValue('category');
        /** @var SiteSection|null $siteSection */
        $siteSection = $category?->getRelationValue('siteSection');
        $mediaAsset = $primary?->getRelationValue('mediaAsset');

        if (
            ! $category
            || ! $siteSection instanceof SiteSection
            || $siteSection->getAttribute('state') !== 'published'
            || ! $primary instanceof ArtworkMedia
            || ! $mediaAsset instanceof MediaAsset
            || ! $this->mediaReady($mediaAsset)
        ) {
            throw ValidationException::withMessages(['state' => 'This artwork is not ready to publish.']);
        }

        $wasPublished = $fresh->getAttribute('state') === 'published';
        DB::transaction(function () use ($fresh, $actor, $wasPublished): void {
            $fresh->forceFill([
                'state' => 'published',
                'published_at' => $fresh->getAttribute('published_at') ?? now(),
            ])->save();

            if (! $wasPublished) {
                $this->audit->record($actor, 'artwork.published', 'artwork', $fresh->getKey());
            }
        });

        return $fresh->fresh(['category', 'artworkMedia.mediaAsset.variants']);
    }

    public function unpublish(Artwork $artwork): Artwork
    {
        $actor = $this->audit->requireActor();
        /** @var Artwork $fresh */
        $fresh = Artwork::query()->findOrFail($artwork->getKey());
        $wasPublished = $fresh->getAttribute('state') === 'published';

        DB::transaction(function () use ($fresh, $actor, $wasPublished): void {
            $fresh->forceFill(['state' => 'draft'])->save();
            if ($wasPublished) {
                $this->audit->record($actor, 'artwork.unpublished', 'artwork', $fresh->getKey());
            }
        });

        return $fresh->fresh(['category', 'artworkMedia.mediaAsset.variants']);
    }

    public function mediaReady(MediaAsset $asset): bool
    {
        if ($asset->getAttribute('state') !== 'available' || blank($asset->getAttribute('alt_text'))) {
            return false;
        }

        $mime = (string) $asset->getAttribute('mime_type');
        if (MediaTypePolicy::isVideo($mime)) {
            return true;
        }
        if (! MediaTypePolicy::isImage($mime)) {
            return false;
        }

        $asset->loadMissing('variants');

        return $asset->getRelation('variants')->filter(
            static fn ($variant): bool => $variant->getAttribute('variant_kind') === MediaIngestService::THUMBNAIL_KIND
                && $variant->getAttribute('transform_profile') === MediaIngestService::TRANSFORM_PROFILE
                && $variant->getAttribute('state') === 'available',
        )->count() === 1;
    }
}
