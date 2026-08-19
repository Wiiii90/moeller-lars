<?php

namespace App\Domain\Content;

use App\Models\SiteSection;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Support\Collection;

final class PublicNavigationService
{
    /**
     * @return Collection<int, array{
     *     position:int,
     *     tie_breaker:int,
     *     label:string,
     *     url:string,
     *     current:bool,
     *     active:bool,
     *     children:list<array{label:string,url:string,current:bool}>
     * }>
     */
    public function items(): Collection
    {
        /** @var EloquentCollection<int, SiteSection> $sections */
        $sections = SiteSection::query()
            ->whereNull('parent_id')
            ->where('state', 'published')
            ->where('show_in_navigation', true)
            ->with(['children' => static function (Relation $relation): void {
                $query = $relation->getQuery();
                $query->where('state', 'published');
                $query->where('show_in_navigation', true);
                $query->orderBy('position');
                $query->orderBy('id');
            }])
            ->orderBy('position')
            ->orderBy('id')
            ->get();

        return $sections->map(static function (SiteSection $section): array {
            /** @var EloquentCollection<int, SiteSection> $childSections */
            $childSections = $section->getRelation('children');
            $children = $childSections->map(static fn (SiteSection $child): array => [
                'label' => (string) $child->getAttribute('navigation_label'),
                'url' => $child->publicUrl(),
                'current' => $child->isCurrentRequest(),
            ])->values()->all();
            $childCurrent = collect($children)->contains(static fn (array $child): bool => $child['current']);
            $current = $section->isCurrentRequest();

            return [
                'position' => (int) $section->getAttribute('position'),
                'tie_breaker' => (int) $section->getKey(),
                'label' => (string) $section->getAttribute('navigation_label'),
                'url' => $section->publicUrl(),
                'current' => $current,
                'active' => $current || $childCurrent,
                'children' => $children,
            ];
        })->values();
    }
}
