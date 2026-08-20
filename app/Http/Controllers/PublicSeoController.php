<?php

namespace App\Http\Controllers;

use App\Domain\Blog\BlogEditorialService;
use App\Domain\Content\CanonicalUrl;
use App\Models\Artwork;
use App\Models\BlogPost;
use App\Models\SiteSection;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\Response;

final class PublicSeoController extends Controller
{
    public function __construct(private readonly CanonicalUrl $canonical) {}

    public function sitemap(): Response
    {
        /** @var Collection<int, SiteSection> $sections */
        $sections = SiteSection::query()
            ->published()
            ->orderBy('position')
            ->orderBy('id')
            ->get();

        $urls = $sections
            ->map(fn (SiteSection $section): string => $this->canonical->forPath($section->publicPath()))
            ->values()
            ->all();

        /** @var Collection<int, Artwork> $artworks */
        $artworks = Artwork::query()
            ->where('state', 'published')
            ->whereHas('category.siteSection', static fn (Builder $query): Builder => $query->published())
            ->get(['slug']);

        foreach ($artworks as $artwork) {
            $urls[] = $this->canonical->forPath('/artworks/'.$artwork->getAttribute('slug'));
        }

        if ($sections->contains(fn (SiteSection $section): bool => $section->getAttribute('type') === SiteSection::TYPE_BLOG)) {
            /** @var Collection<int, BlogPost> $posts */
            $posts = BlogEditorialService::publicQuery()->orderBy('position')->get(['slug']);
            foreach ($posts as $post) {
                $urls[] = $this->canonical->forPath('/blog/'.$post->getAttribute('slug'));
            }
        }

        return response()
            ->view('pages.sitemap', ['urls' => array_values(array_unique($urls))])
            ->header('Content-Type', 'application/xml; charset=UTF-8');
    }

    public function robots(): Response
    {
        $body = implode("\n", [
            'User-agent: *',
            'Allow: /',
            'Disallow: /admin',
            'Sitemap: '.$this->canonical->forPath('/sitemap.xml'),
            '',
        ]);

        return response($body)->header('Content-Type', 'text/plain; charset=UTF-8');
    }
}
