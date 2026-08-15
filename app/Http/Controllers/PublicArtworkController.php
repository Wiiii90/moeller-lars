<?php

namespace App\Http\Controllers;

use App\Domain\Artwork\PublicArtworkQuery;
use App\Domain\Media\PublicMedia;
use App\Models\ArtworkCategory;
use Illuminate\Contracts\View\View;

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

    public function category(string $category): View
    {
        abort_unless(in_array($category, PublicArtworkQuery::CATEGORY_SLUGS, true), 404);
        $categoryRecord = ArtworkCategory::query()
            ->where('slug', $category)
            ->where('state', 'published')
            ->firstOrFail();

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

        return view('pages.artworks.show', [
            'artwork' => $artwork,
            'media' => $this->media,
        ]);
    }
}
