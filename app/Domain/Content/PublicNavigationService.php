<?php

namespace App\Domain\Content;

use App\Models\SiteSection;
use App\Routing\SiteNodeRoute;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Support\Collection;

final class PublicNavigationService
{
    public function __construct(
        private readonly SitePreviewContext $preview,
        private readonly SiteNodeRoute $routes,
        private readonly HomePresentationResolver $home,
    ) {}

    /** @return Collection<int, array<string, mixed>> */
    public function items(): Collection
    {
        /** @var Builder<SiteSection> $query */
        $query = SiteSection::query();

        if (! $this->preview->active()) {
            $query->where('state', 'published');
        }

        // Home alone has an additional menu switch. Skip Home remains a
        // separate routing choice that always excludes Home from the menu.
        $query->where(static function (Builder $sections): void {
            $sections->where('type', '<>', SiteSectionType::Home->value)
                ->orWhere('show_in_navigation', true);
        });
        if ($this->home->skipEnabled()) {
            $query->where('type', '<>', SiteSectionType::Home->value);
        }

        /** @var EloquentCollection<int, SiteSection> $sections */
        $sections = $query->orderBy('position')->orderBy('id')->get();
        $included = $sections->keyBy(fn (SiteSection $section): int => (int) $section->getKey());

        /** @var array<int, list<SiteSection>> $children */
        $children = [];
        /** @var list<SiteSection> $roots */
        $roots = [];

        foreach ($sections as $section) {
            $parentId = $section->getAttribute('parent_id');
            $parentId = is_numeric($parentId) ? (int) $parentId : null;

            if ($parentId !== null && $included->has($parentId)) {
                $children[$parentId][] = $section;
            } else {
                // A published child does not become invisible merely because
                // its editorial parent is unpublished or Home is skipped.
                $roots[] = $section;
            }
        }

        $items = [];
        foreach ($roots as $section) {
            $childItems = array_map(fn (SiteSection $child): array => [
                'label' => $this->label($child),
                'url' => $this->sectionUrl($child),
                'current' => $this->routes->isCurrent($child),
            ], $children[(int) $section->getKey()] ?? []);

            $current = $this->routes->isCurrent($section);
            $items[] = [
                'position' => (int) $section->getAttribute('position'),
                'tie_breaker' => (int) $section->getKey(),
                'label' => $this->label($section),
                'url' => $this->sectionUrl($section),
                'current' => $current,
                'active' => $current || collect($childItems)->contains(static fn (array $item): bool => $item['current']),
                'children' => $childItems,
            ];
        }

        return collect($items);
    }

    private function label(SiteSection $section): string
    {
        return (string) ($section->getAttribute('navigation_label') ?: $section->getAttribute('title'));
    }

    private function sectionUrl(SiteSection $section): ?string
    {
        $url = $this->routes->url($section);

        return $url === null ? null : $this->preview->url($url);
    }
}
