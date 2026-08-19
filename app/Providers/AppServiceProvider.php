<?php

namespace App\Providers;

use App\Models\ArtworkCategory;
use App\Models\BlogSetting;
use App\Models\PublicContentSetting;
use Filament\Support\Assets\Css;
use Filament\Support\Facades\FilamentAsset;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\View;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        //
    }

    public function boot(): void
    {
        $analyticsStylesheet = app()->environment('production')
            ? secure_asset('css/filament-analytics.css')
            : asset('css/filament-analytics.css');

        FilamentAsset::register([
            Css::make('analytics-dashboard', $analyticsStylesheet),
        ]);

        View::composer('layouts.app', function ($view): void {
            /** @var Builder<ArtworkCategory> $categoryQuery */
            $categoryQuery = ArtworkCategory::query();
            $categoryQuery->whereNull('parent_id');
            $categoryQuery->where('state', 'published');
            $categoryQuery->where('show_in_navigation', true);
            $categoryQuery->with(['children' => static function (HasMany $query): void {
                $query->where('state', 'published');
                $query->where('show_in_navigation', true);
                $query->orderBy('position');
                $query->orderBy('id');
            }]);
            $categoryQuery->orderBy('position');
            $categoryQuery->orderBy('id');

            /** @var EloquentCollection<int, ArtworkCategory> $categories */
            $categories = $categoryQuery->get(['id', 'name', 'slug', 'position']);

            /** @var Collection<int, array{position:int,tie_breaker:int,label:string,url:string,current:bool,active:bool,children:list<array{label:string,url:string,current:bool}>}> $navigationItems */
            $navigationItems = $categories->map(static function (ArtworkCategory $category): array {
                $current = request()->routeIs('artworks.category')
                    && request()->route('category') === $category->getAttribute('slug');
                /** @var EloquentCollection<int, ArtworkCategory> $childCategories */
                $childCategories = $category->getRelation('children');
                $children = $childCategories->map(static fn (ArtworkCategory $child): array => [
                    'label' => (string) $child->getAttribute('name'),
                    'url' => route('artworks.category', ['category' => $child->getAttribute('slug')]),
                    'current' => request()->routeIs('artworks.category')
                        && request()->route('category') === $child->getAttribute('slug'),
                ])->values()->all();
                $childCurrent = collect($children)->contains(static fn (array $child): bool => $child['current']);

                return [
                    'position' => (int) $category->getAttribute('position'),
                    'tie_breaker' => (int) $category->getKey(),
                    'label' => (string) $category->getAttribute('name'),
                    'url' => route('artworks.category', ['category' => $category->getAttribute('slug')]),
                    'current' => $current,
                    'active' => $current || $childCurrent,
                    'children' => $children,
                ];
            });

            $settings = PublicContentSetting::query()->findOrFail(1);
            if ((bool) $settings->getAttribute('cv_enabled')) {
                $current = request()->routeIs('cv');
                $navigationItems->push([
                    'position' => (int) $settings->getAttribute('cv_navigation_position'),
                    'tie_breaker' => 900001,
                    'label' => (string) $settings->getAttribute('cv_navigation_label'),
                    'url' => route('cv'),
                    'current' => $current,
                    'active' => $current,
                    'children' => [],
                ]);
            }
            if ((bool) $settings->getAttribute('exhibitions_enabled')) {
                $current = request()->routeIs('exhibitions.*');
                $navigationItems->push([
                    'position' => (int) $settings->getAttribute('exhibitions_navigation_position'),
                    'tie_breaker' => 900002,
                    'label' => (string) $settings->getAttribute('exhibitions_navigation_label'),
                    'url' => route('exhibitions.index'),
                    'current' => $current,
                    'active' => $current,
                    'children' => [],
                ]);
            }

            $blogSettings = BlogSetting::query()->findOrFail(1);
            if ((bool) $blogSettings->getAttribute('public_enabled')) {
                $current = request()->routeIs('blog.*');
                $navigationItems->push([
                    'position' => (int) $blogSettings->getAttribute('navigation_position'),
                    'tie_breaker' => 900003,
                    'label' => (string) $blogSettings->getAttribute('navigation_label'),
                    'url' => route('blog.index'),
                    'current' => $current,
                    'active' => $current,
                    'children' => [],
                ]);
            }

            $ambiguousPositions = $navigationItems
                ->groupBy('position')
                ->filter(static fn (Collection $items): bool => $items->count() > 1);

            if ($ambiguousPositions->isNotEmpty()) {
                Log::warning('Public navigation contains duplicate positions; rendering with deterministic tie-breakers.', [
                    'positions' => $ambiguousPositions
                        ->map(static fn (Collection $items): array => $items->pluck('label')->values()->all())
                        ->all(),
                ]);
            }

            $view->with(
                'navigationItems',
                $navigationItems
                    ->sortBy(static fn (array $item): string => sprintf('%010d:%010d', $item['position'], $item['tie_breaker']))
                    ->values(),
            );
        });
    }
}
