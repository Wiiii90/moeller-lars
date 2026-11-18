<?php

namespace App\Http\Controllers;

use App\Domain\Artwork\PublicArtworkQuery;
use App\Domain\Artwork\GalleryPresentation;
use App\Domain\Content\GalleryTemplate;
use App\Domain\Content\SafeRichTextRenderer;
use App\Domain\Content\SitePreviewContext;
use App\Domain\Content\SiteSectionPathPolicy;
use App\Domain\Content\SiteSectionType;
use App\Domain\Media\PublicMedia;
use App\Models\ArtworkCategory;
use App\Models\Redirect;
use App\Models\SiteSection;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;

class PublicArtworkController extends Controller
{
    public function __construct(
        private readonly PublicArtworkQuery $artworks,
        private readonly PublicMedia $media,
        private readonly SafeRichTextRenderer $richText,
        private readonly SitePreviewContext $preview,
    ) {}

    public function category(string $category): View|RedirectResponse
    {
        $sectionQuery = SiteSection::query()
            ->where('type', SiteSectionType::Gallery->value)
            ->where('template', GalleryTemplate::Showcase->value)
            ->where('slug', $category)
            ->with('artworkCategory');
        $this->preview->constrainSectionQuery($sectionQuery);
        /** @var SiteSection|null $section */
        $section = $sectionQuery->first();

        if ($section === null) {
            if ($this->preview->active()) {
                abort(404);
            }

            $redirect = Redirect::query()
                ->where('source_path', '/'.$category)
                ->where('enabled', true)
                ->where('reason', SiteSectionPathPolicy::GALLERY_SLUG_REDIRECT_REASON)
                ->first();

            abort_unless($redirect !== null, 404);

            return redirect($redirect->getAttribute('target_path'), (int) $redirect->getAttribute('status_code'));
        }

        return $this->categoryForSection($section);
    }

    /** A Showcase may be the protected Home page at / rather than a slug route. */
    public function categoryForSection(SiteSection $section): View
    {
        abort_unless(
            $section->nodeType() === SiteSectionType::Gallery
                && $section->galleryTemplate() === GalleryTemplate::Showcase,
            404,
        );

        $section->load('artworkCategory');
        /** @var ArtworkCategory|null $categoryRecord */
        $categoryRecord = $section->getRelation('artworkCategory');
        abort_unless($categoryRecord !== null, 404);

        return view('pages.artworks.index', [
            'category' => $categoryRecord,
            'section' => $section,
            'artworks' => $this->artworks->category((string) $categoryRecord->getAttribute('slug'), $section),
            'media' => $this->media,
        ]);
    }

    public function show(string $slug): View|RedirectResponse
    {
        $artwork = $this->artworks->publishedBySlug($slug);
        abort_unless($artwork !== null, 404);

        /** @var ArtworkCategory $categoryRecord */
        $categoryRecord = $artwork->getRelationValue('category');
        if (! GalleryPresentation::artworkPagesEnabled($categoryRecord)) {
            // Preserve old bookmarked detail URLs without advertising pages
            // that the artist deliberately disabled for this Gallery.
            /** @var SiteSection|null $page */
            $page = $categoryRecord->siteSection()->first();

            return redirect($this->preview->url(
                $page instanceof SiteSection
                    ? (app(\App\Routing\SiteNodeRoute::class)->url($page) ?? route('home'))
                    : route('home'),
            ));
        }
        $sectionQuery = SiteSection::query()
            ->where('type', SiteSectionType::Gallery->value)
            ->where('artwork_category_id', $categoryRecord->getKey());
        $this->preview->constrainSectionQuery($sectionQuery);
        abort_unless($sectionQuery->exists(), 404);

        return view('pages.artworks.show', [
            'artwork' => $artwork,
            'media' => $this->media,
            'richText' => $this->richText,
            'viewerArtworks' => $this->artworks->category($categoryRecord->getAttribute('slug')),
        ]);
    }
}
