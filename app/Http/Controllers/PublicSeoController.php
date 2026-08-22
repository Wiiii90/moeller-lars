<?php

namespace App\Http\Controllers;

use App\Domain\Blog\BlogEditorialService;
use App\Domain\Content\CanonicalUrl;
use App\Domain\Content\JournalTemplate;
use App\Domain\Content\SiteNodeType;
use App\Models\Artwork;
use App\Models\BlogPost;
use App\Models\SiteSection;
use App\Routing\SiteNodeRoute;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\Response;

final class PublicSeoController extends Controller
{
    public function __construct(
        private readonly CanonicalUrl $canonical,
        private readonly SiteNodeRoute $siteNodeRoute,
    ) {}

    public function sitemap(): Response
    {
        /** @var Collection<int, SiteSection> $sections */
        $sections = SiteSection::query()
            ->where('state', 'published')
            ->orderBy('position')
            ->orderBy('id')
            ->get();

        $urls = $sections
            ->map(fn (SiteSection $section): ?string => $this->siteNodeRoute->path($section))
            ->filter(fn (?string $path): bool => $path !== null)
            ->map(fn (string $path): string => $this->canonical->forPath($path))
            ->values()
            ->all();

        /** @var Collection<int, Artwork> $artworks */
        $artworks = Artwork::query()
            ->where('state', 'published')
            ->whereHas('category.siteSection', static fn (Builder $query): Builder => $query->where('state', 'published'))
            ->get(['slug']);

        foreach ($artworks as $artwork) {
            $urls[] = $this->canonical->forPath('/artworks/'.$artwork->getAttribute('slug'));
        }

        /** @var Collection<int, SiteSection> $blogJournals */
        $blogJournals = $sections
            ->where('type', SiteNodeType::Journal->value)
            ->where('template', JournalTemplate::Blog->value)
            ->values();

        foreach ($blogJournals as $journal) {
            /** @var Collection<int, BlogPost> $posts */
            $posts = BlogEditorialService::publicQuery()
                ->where('site_section_id', $journal->getKey())
                ->orderBy('position')
                ->orderBy('id')
                ->get(['slug']);
            foreach ($posts as $post) {
                $urls[] = $this->canonical->forPath('/'.$journal->getAttribute('slug').'/'.$post->getAttribute('slug'));
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
