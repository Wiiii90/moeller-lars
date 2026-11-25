<?php

namespace App\Http\Controllers;

use App\Domain\Artwork\PublicArtworkQuery;
use App\Domain\Artwork\GalleryPresentation;
use App\Domain\Artwork\PublicArtworkViewerItem;
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
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;

class PublicArtworkController extends Controller
{
    public function __construct(
        private readonly PublicArtworkQuery $artworks,
        private readonly PublicMedia $media,
        private readonly PublicArtworkViewerItem $viewerItem,
        private readonly SafeRichTextRenderer $richText,
        private readonly SitePreviewContext $preview,
    ) {}

    public function category(string $category): View|RedirectResponse|JsonResponse
    {
        $sectionQuery = SiteSection::query()
            ->where('type', SiteSectionType::Gallery->value)
            ->where('template', GalleryTemplate::Standard->value)
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

    /** A Standard may be the protected Home page at / rather than a slug route. */
    public function categoryForSection(SiteSection $section): View|JsonResponse
    {
        abort_unless(
            $section->nodeType() === SiteSectionType::Gallery
                && $section->galleryTemplate() === GalleryTemplate::Standard,
            404,
        );

        $section->load('artworkCategory');
        /** @var ArtworkCategory|null $categoryRecord */
        $categoryRecord = $section->getRelation('artworkCategory');
        abort_unless($categoryRecord !== null, 404);

        $slug = (string) $categoryRecord->getAttribute('slug');
        if (request()->query('gallery_viewer') === '1'
            && request()->header('X-Gallery-Viewer') === '1') {
            // Complete Viewer navigation is requested only when an Artwork is
            // opened, never during the Gallery's initial lightweight response.
            $items = $this->artworks->category($slug, $section)
                ->map(fn ($artwork): array => $this->viewerItem->forArtwork($artwork, $this->media))
                ->values()->all();

            return response()->json(['items' => $items])->header('Cache-Control', 'private, no-store');
        }

        $requestedPage = request()->query('gallery_page', '1');
        abort_unless(is_scalar($requestedPage)
            && preg_match('/^[1-9][0-9]{0,4}$/', (string) $requestedPage) === 1, 404);
        $page = (int) $requestedPage;

        $batch = $this->artworks->categoryBatch($slug, $section, $page);
        $isChunk = request()->header('X-Gallery-Chunk') === '1';
        $galleryPresentation = $isChunk && request()->header('X-Gallery-Presentation') === 'two_columns'
            ? 'two_columns' : 'sequence';
        $divider = GalleryPresentation::divider($categoryRecord, $galleryPresentation);
        $presentation = [
            'category' => $categoryRecord,
            'section' => $section,
            'artworks' => $batch['artworks'],
            'outline' => $batch['outline'],
            'hasMore' => $batch['hasMore'],
            'page' => $page,
            'media' => $this->media,
            'divider' => $divider,
            'galleryOffsets' => GalleryPresentation::indexCardOffsets($batch['outline'], $categoryRecord),
            'galleryGaps' => GalleryPresentation::indexCardGapSteps($batch['outline'], $categoryRecord),
        ];

        if ($isChunk) {
            // Reuse precisely the same Artwork cards and nonce-protected
            // component styles as the full Gallery. Read the stack inside
            // the render callback, before Laravel flushes the view state.
            // The browser attaches these styles using the document's nonce.
            $styles = '';
            /** @var \Illuminate\View\View $batchView */
            $batchView = view('pages.artworks.partials.batch', $presentation);
            $html = $batchView->render(function () use (&$styles): void {
                $styles = app('view')->yieldPushContent('public-component-styles');
            });

            return response()->json([
                'html' => $html,
                'styles' => $styles,
                'nextPage' => $batch['hasMore'] ? $page + 1 : null,
            ])->header('Cache-Control', 'private, no-store');
        }

        return view('pages.artworks.index', $presentation);
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
