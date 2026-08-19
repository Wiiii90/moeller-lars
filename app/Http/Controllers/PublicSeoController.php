<?php

namespace App\Http\Controllers;

use App\Domain\Blog\BlogEditorialService;
use App\Domain\Content\CanonicalUrl;
use App\Models\Artwork;
use App\Models\BlogPost;
use App\Models\PublicContentSetting;
use App\Models\SiteSection;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\Response;

final class PublicSeoController extends Controller
{
    public function __construct(private readonly CanonicalUrl $canonical) {}

    public function sitemap(): Response
    {
        $urls = [$this->canonical->forPath('/')];

        foreach (SiteSection::query()
            ->where('type', SiteSection::TYPE_GALLERY)
            ->where('state', 'published')
            ->orderBy('position')
            ->get(['slug']) as $section) {
            $urls[] = $this->canonical->forPath('/'.$section->getAttribute('slug'));
        }

        foreach (Artwork::query()
            ->where('state', 'published')
            ->whereHas('category.siteSection', static fn (Builder $query): Builder => $query->where('state', 'published'))
            ->get(['slug']) as $artwork) {
            $urls[] = $this->canonical->forPath('/artworks/'.$artwork->getAttribute('slug'));
        }

        if (SiteSection::query()->where('type', SiteSection::TYPE_VITA)->where('state', 'published')->exists()) {
            $urls[] = $this->canonical->forPath('/cv');
        }
        if (SiteSection::query()->where('type', SiteSection::TYPE_EXHIBITIONS)->where('state', 'published')->exists()) {
            $urls[] = $this->canonical->forPath('/exhibitions');
        }

        $publicSettings = PublicContentSetting::query()->findOrFail(1);
        if ($publicSettings->getAttribute('contact_state') === 'enabled') {
            $urls[] = $this->canonical->forPath('/contact');
        }

        if (SiteSection::query()->where('type', SiteSection::TYPE_BLOG)->where('state', 'published')->exists()) {
            $urls[] = $this->canonical->forPath('/blog');
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
