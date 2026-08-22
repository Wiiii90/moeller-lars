<?php

namespace App\Routing;

use App\Domain\Content\SiteNodeType;
use App\Models\SiteSection;
use Illuminate\Http\Request;
use LogicException;

final class SiteNodeRoute
{
    public function path(SiteSection $section): ?string
    {
        return match ($section->nodeType()) {
            SiteNodeType::Home => '/',
            SiteNodeType::NavigationNode => null,
            SiteNodeType::Gallery,
            SiteNodeType::Journal,
            SiteNodeType::CustomPage => '/'.$this->slug($section),
        };
    }

    public function url(SiteSection $section): ?string
    {
        return match ($section->nodeType()) {
            SiteNodeType::Home => route('home'),
            SiteNodeType::NavigationNode => null,
            SiteNodeType::Gallery,
            SiteNodeType::Journal,
            SiteNodeType::CustomPage => route('site.section', ['section' => $this->slug($section)]),
        };
    }

    public function isCurrent(SiteSection $section, ?Request $request = null): bool
    {
        $request ??= request();

        return match ($section->nodeType()) {
            SiteNodeType::Home => $request->routeIs('home', 'preview.home'),
            SiteNodeType::NavigationNode => false,
            SiteNodeType::Gallery,
            SiteNodeType::CustomPage => $request->routeIs('site.section', 'preview.site.section')
                && $request->route('section') === $this->slug($section),
            SiteNodeType::Journal => $request->routeIs(
                'site.section',
                'preview.site.section',
                'journal.show',
                'preview.journal.show',
            ) && $request->route('section') === $this->slug($section),
        };
    }

    private function slug(SiteSection $section): string
    {
        $slug = $section->getAttribute('slug');
        if (! is_string($slug) || $slug === '') {
            throw new LogicException($section->nodeType()->label().' site node is missing its required public slug.');
        }

        return $slug;
    }
}
