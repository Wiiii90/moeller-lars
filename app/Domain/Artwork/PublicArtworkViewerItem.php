<?php

namespace App\Domain\Artwork;

use App\Domain\Content\SitePreviewContext;
use App\Domain\Media\PublicMedia;
use App\Models\Artwork;

/**
 * One canonical Artwork Viewer data projection for on-page triggers and the
 * on-demand complete Gallery sequence. Never exposes draft-only media on LIVE.
 */
final class PublicArtworkViewerItem
{
    public function __construct(private readonly SitePreviewContext $preview) {}

    /**
     * @return array{key:string,analyticsKey:string,kind:string,mime:string,src:string,alt:string,title:string,page:?string,navigation:bool,cyclic:bool}
     */
    public function forArtwork(Artwork $artwork, PublicMedia $media, ?bool $enableViewer = null): array
    {
        $category = $artwork->getRelationValue('category');
        $detailUrl = $this->preview->url(route('artworks.show', $artwork->slug));

        return [
            'key' => (string) $artwork->slug,
            'analyticsKey' => (string) $artwork->analytics_key,
            'kind' => $media->kind($artwork),
            'mime' => $media->mimeType($artwork),
            'src' => $media->originalUrl($artwork),
            'alt' => $media->altText($artwork),
            'title' => (string) $artwork->title,
            'page' => GalleryPresentation::artworkPagesEnabled($category) ? $detailUrl : null,
            'navigation' => $enableViewer !== null || GalleryPresentation::viewerNavigationEnabled($category),
            'cyclic' => $enableViewer === null && GalleryPresentation::viewerNavigationCyclic($category),
        ];
    }
}
