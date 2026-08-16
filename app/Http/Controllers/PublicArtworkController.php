<?php

namespace App\Http\Controllers;

use App\Domain\Artwork\ArtworkCategoryPathPolicy;
use App\Domain\Artwork\PublicArtworkQuery;
use App\Domain\Media\PublicMedia;
use App\Models\ArtworkCategory;
use App\Models\Redirect;
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
        $categoryRecord = ArtworkCategory::query()
            ->where('slug', $category)
            ->where('state', 'published')
            ->first();

        if ($categoryRecord === null) {
            $redirect = Redirect::query()
                ->where('source_path', '/'.$category)
                ->where('enabled', true)
                ->where('reason', ArtworkCategoryPathPolicy::CATEGORY_SLUG_REDIRECT_REASON)
                ->first();

            abort_unless($redirect !== null, 404);

            return redirect($redirect->getAttribute('target_path'), (int) $redirect->getAttribute('status_code'));
        }

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

        return view('pages.artworks.show', [
            'artwork' => $artwork,
            'media' => $this->media,
            'viewerArtworks' => $this->artworks->category($categoryRecord->getAttribute('slug')),
        ]);
    }
}
