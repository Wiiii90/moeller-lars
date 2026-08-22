<?php

namespace App\Domain\Content;

use App\Models\SiteSection;
use App\Routing\SiteNodeRoute;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Support\Collection;

final class PublicNavigationService
{
    public function __construct(
        private readonly SitePreviewContext $preview,
        private readonly SiteNodeRoute $routes,
    ) {}

    /**
     * @return Collection<int, array{
     *     position:int,
     *     tie_breaker:int,
     *     label:string,
     *     url:?string,
     *     current:bool,
     *     active:bool,
     *     children:list<array{label:string,url:?string,current:bool}>
     * }>
     */
    public function items(): Collection
    {
        /** @var Builder<SiteSection> $query */
        $query = SiteSection::query();
        $query->whereNull('parent_id');
        if (! $this->preview->active()) {
            $query->where('state', 'published');
        }
        $query->where('show_in_navigation', true);
        $preview = $this->preview;
        $query->with(['children' => static function (Relation $relation) use ($preview): void {
            $childQuery = $relation->getQuery();
            if (! $preview->active()) {
                $childQuery->where('state', 'published');
            }
            $childQuery->where('show_in_navigation', true);
            $childQuery->orderBy('position');
            $childQuery->orderBy('id');
        }]);
        $query->orderBy('position');
        $query->orderBy('id');

        /** @var EloquentCollection<int, SiteSection> $sections */
        $sections = $query->get();

        return $sections->map(function (SiteSection $section): array {
            /** @var EloquentCollection<int, SiteSection> $childSections */
            $childSections = $section->getRelation('children');
            $children = $childSections->map(fn (SiteSection $child): array => [
                'label' => (string) $child->getAttribute('navigation_label'),
                'url' => $this->sectionUrl($child),
                'current' => $this->routes->isCurrent($child),
            ])->values()->all();
            $childCurrent = collect($children)->contains(static fn (array $child): bool => $child['current']);
            $current = $this->routes->isCurrent($section);

            return [
                'position' => (int) $section->getAttribute('position'),
                'tie_breaker' => (int) $section->getKey(),
                'label' => (string) $section->getAttribute('navigation_label'),
                'url' => $this->sectionUrl($section),
                'current' => $current,
                'active' => $current || $childCurrent,
                'children' => $children,
            ];
        })->values();
    }

    private function sectionUrl(SiteSection $section): ?string
    {
        $url = $this->routes->url($section);

        return $url === null ? null : $this->preview->url($url);
    }
}
