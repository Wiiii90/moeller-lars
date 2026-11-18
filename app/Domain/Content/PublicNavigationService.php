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
    ) {}

    /** @return Collection<int, covariant array{position: int, tie_breaker: int, label: string, url: ?string, current: bool, active: bool, children: list<array{label: string, url: ?string, current: bool}>}> */
    public function items(): Collection
    {
        /** @var Builder<SiteSection> $query */
        $query = SiteSection::query();

        if (! $this->preview->active()) {
            $query->where('state', 'published');
        }

        // Only Home has an independent public-menu visibility switch.
        $query->where(static function (Builder $sections): void {
            $sections->where('is_home', false)
                ->orWhere('show_in_navigation', true);
        });

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
                // its editorial parent is unpublished.
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
