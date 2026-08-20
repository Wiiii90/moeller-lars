<?php

namespace App\Http\Controllers;

use App\Domain\Artwork\ArtworkCategoryPathPolicy;
use App\Domain\Artwork\PublicArtworkQuery;
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
    ) {}

    public function home(): View
    {
        return view('pages.home', [
            'artwork' => $this->artworks->latestForHome(),
            'media' => $this->media,
        ]);
    }

    public function category(string $category): View|RedirectResponse
    {
        /** @var SiteSection|null $section */
        $section = SiteSection::query()
            ->ofType(SiteSection::TYPE_GALLERY)
            ->published()
            ->where('slug', $category)
            ->with('artworkCategory')
            ->first();

        if ($section === null) {
            $redirect = Redirect::query()
                ->where('source_path', '/'.$category)
                ->where('enabled', true)
                ->where('reason', ArtworkCategoryPathPolicy::CATEGORY_SLUG_REDIRECT_REASON)
                ->first();

            abort_unless($redirect !== null, 404);

            return redirect($redirect->getAttribute('target_path'), (int) $redirect->getAttribute('status_code'));
        }

        /** @var ArtworkCategory|null $categoryRecord */
        $categoryRecord = $section->getRelation('artworkCategory');
        abort_unless($categoryRecord !== null, 404);

        return view('pages.artworks.index', [
            'category' => $categoryRecord,
            'artworks' => $this->artworks->category($category),
            'media' => $this->media,
        ]);
    }

    public function show(string $slug): View
    {
        $artwork = $this->artworks->publishedBySlug($slug);
        abort_unless($artwork !== null, 404);

        /** @var ArtworkCategory $categoryRecord */
        $categoryRecord = $artwork->getRelationValue('category');
        abort_unless(
            SiteSection::query()
                ->ofType(SiteSection::TYPE_GALLERY)
                ->published()
                ->where('artwork_category_id', $categoryRecord->getKey())
                ->exists(),
            404,
        );

        return view('pages.artworks.show', [
            'artwork' => $artwork,
            'media' => $this->media,
            'viewerArtworks' => $this->artworks->category($categoryRecord->getAttribute('slug')),
        ]);
    }
}
