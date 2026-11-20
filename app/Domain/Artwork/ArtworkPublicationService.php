<?php

namespace App\Domain\Artwork;

use App\Domain\Admin\AdminAuditService;
use App\Domain\Media\MediaPreviewVariant;
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
        if (! $this->isPublishable($fresh)) {
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

    /** Auto-publish is opportunistic. An unfinished Artwork remains a draft. */
    public function canPublish(Artwork $artwork): bool
    {
        /** @var Artwork $fresh */
        $fresh = Artwork::query()
            ->with(['category.siteSection', 'artworkMedia.mediaAsset.variants'])
            ->findOrFail($artwork->getKey());

        return $this->isPublishable($fresh);
    }

    private function isPublishable(Artwork $artwork): bool
    {
        /** @var Collection<int, ArtworkMedia> $primaries */
        $primaries = $artwork->getRelation('artworkMedia')->where('role', 'primary')->values();
        $primary = $primaries->count() === 1 ? $primaries->first() : null;
        /** @var ArtworkCategory|null $category */
        $category = $artwork->getRelationValue('category');
        /** @var SiteSection|null $siteSection */
        $section = $category?->getRelationValue('siteSection');
        $asset = $primary?->getRelationValue('mediaAsset');

        return $category instanceof ArtworkCategory
            && $section instanceof SiteSection
            && $section->getAttribute('state') === 'published'
            && $primary instanceof ArtworkMedia
            && $asset instanceof MediaAsset
            && $this->mediaReady($asset, $primary->getAttribute('alt_text_override'));
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

    public function mediaReady(MediaAsset $asset, ?string $altOverride = null): bool
    {
        if ($asset->getAttribute('state') !== 'available' || (blank($altOverride) && blank($asset->getAttribute('alt_text')))) {
            return false;
        }

        $mime = (string) $asset->getAttribute('mime_type');
        if (MediaTypePolicy::isVideo($mime)) {
            return MediaPreviewVariant::videoPoster($asset) !== null;
        }
        if (! MediaTypePolicy::isImage($mime)) {
            return false;
        }

        return MediaPreviewVariant::imageThumbnail($asset) !== null;
    }
}
