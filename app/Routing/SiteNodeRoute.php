<?php

namespace App\Routing;

use App\Domain\Content\SiteSectionType;
use App\Models\SiteSection;
use Illuminate\Http\Request;
use LogicException;

final class SiteNodeRoute
{
    public function path(SiteSection $section): ?string
    {
        return match ($section->nodeType()) {
            SiteSectionType::Home => '/',
            SiteSectionType::NavigationNode => null,
            SiteSectionType::Gallery,
            SiteSectionType::Journal,
            SiteSectionType::CustomPage => '/'.$this->slug($section),
        };
    }

    public function url(SiteSection $section): ?string
    {
        return match ($section->nodeType()) {
            SiteSectionType::Home => route('home'),
            SiteSectionType::NavigationNode => null,
            SiteSectionType::Gallery,
            SiteSectionType::Journal,
            SiteSectionType::CustomPage => route('site.section', ['section' => $this->slug($section)]),
        };
    }

    public function isCurrent(SiteSection $section, ?Request $request = null): bool
    {
        $request ??= request();

        return match ($section->nodeType()) {
            SiteSectionType::Home => $request->routeIs('home', 'preview.home'),
            SiteSectionType::NavigationNode => false,
            SiteSectionType::Gallery,
            SiteSectionType::CustomPage => $request->routeIs('site.section', 'preview.site.section')
                && $request->route('section') === $this->slug($section),
            SiteSectionType::Journal => $request->routeIs(
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
