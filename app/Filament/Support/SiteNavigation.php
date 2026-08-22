<?php

namespace App\Filament\Support;

use App\Models\SiteSection;
use Filament\Navigation\NavigationItem;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;

use function Filament\Support\original_request;

final class SiteNavigation
{
    public function __construct(private readonly SiteNodePresentation $presentation) {}

    /** @return array<NavigationItem> */
    public function items(): array
    {
        /** @var EloquentCollection<int, SiteSection> $sections */
        $sections = SiteSection::query()
            ->with('customPageSetting')
            ->orderBy('position')
            ->orderBy('id')
            ->get();

        /** @var array<int, list<SiteSection>> $childrenByParent */
        $childrenByParent = [];
        foreach ($sections as $section) {
            $parentId = $section->getAttribute('parent_id');
            if (is_numeric($parentId)) {
                $childrenByParent[(int) $parentId][] = $section;
            }
        }

        $items = [];
        foreach ($sections as $section) {
            if ($section->getAttribute('parent_id') === null) {
                $items[] = $this->item($section, $childrenByParent, 0);
            }
        }

        return $items;
    }

    /** @param array<int, list<SiteSection>> $childrenByParent */
    private function item(SiteSection $section, array $childrenByParent, int $depth): NavigationItem
    {
        $type = $section->nodeType();
        $label = trim((string) ($section->getAttribute('navigation_label') ?: $section->getAttribute('title')));
        $children = $childrenByParent[(int) $section->getKey()] ?? [];
        $url = $this->presentation->workspaceUrl($section);

        $item = NavigationItem::make($label)
            ->key('site-section-'.$section->getKey())
            ->icon($this->presentation->icon($type))
            ->url($url)
            ->isActiveWhen(fn (): bool => $this->urlIsActive($url))
            ->extraAttributes([
                'data-artist-site-section' => (string) $section->getKey(),
                'data-artist-site-section-depth' => (string) $depth,
                'data-artist-site-section-type' => $type->value,
                'data-artist-tree-branch' => $children === [] ? 'false' : 'true',
            ]);

        if ($children !== []) {
            $item->childItems(array_map(
                fn (SiteSection $child): NavigationItem => $this->item($child, $childrenByParent, $depth + 1),
                $children,
            ));
        }

        return $item;
    }

    private function urlIsActive(?string $url): bool
    {
        if ($url === null) {
            return false;
        }

        $target = parse_url($url);
        $targetPath = $target['path'] ?? null;
        if (! is_string($targetPath) || $targetPath === '') {
            return false;
        }

        $request = original_request();
        $requestPath = '/'.ltrim($request->path(), '/');
        if (rtrim($requestPath, '/') !== rtrim($targetPath, '/')) {
            return false;
        }

        $query = [];
        if (isset($target['query'])) {
            parse_str($target['query'], $query);
        }

        foreach ($query as $key => $value) {
            if ((string) $request->query((string) $key) !== (string) $value) {
                return false;
            }
        }

        return true;
    }
}
