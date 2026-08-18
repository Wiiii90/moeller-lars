<?php

namespace App\Providers;

use App\Models\ArtworkCategory;
use App\Models\BlogSetting;
use App\Models\PublicContentSetting;
use Filament\Support\Assets\Css;
use Filament\Support\Facades\FilamentAsset;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
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
        FilamentAsset::register([
            Css::make('analytics-dashboard', asset('css/filament-analytics.css')),
        ]);

        View::composer('layouts.app', function ($view): void {
            /** @var EloquentCollection<int, ArtworkCategory> $categories */
            $categories = ArtworkCategory::query()
                ->where('state', 'published')
                ->where('show_in_navigation', true)
                ->orderBy('position')
                ->orderBy('id')
                ->get(['id', 'name', 'slug', 'position']);

            /** @var Collection<int, array{position:int,tie_breaker:int,label:string,url:string,current:bool}> $navigationItems */
            $navigationItems = $categories->map(static fn (ArtworkCategory $category): array => [
                'position' => (int) $category->getAttribute('position'),
                'tie_breaker' => (int) $category->getKey(),
                'label' => (string) $category->getAttribute('name'),
                'url' => route('artworks.category', ['category' => $category->getAttribute('slug')]),
                'current' => request()->routeIs('artworks.category')
                    && request()->route('category') === $category->getAttribute('slug'),
            ]);

            $settings = PublicContentSetting::query()->findOrFail(1);
            if ((bool) $settings->getAttribute('cv_enabled')) {
                $navigationItems->push([
                    'position' => (int) $settings->getAttribute('cv_navigation_position'),
                    'tie_breaker' => 900001,
                    'label' => (string) $settings->getAttribute('cv_navigation_label'),
                    'url' => route('cv'),
                    'current' => request()->routeIs('cv'),
                ]);
            }
            if ((bool) $settings->getAttribute('exhibitions_enabled')) {
                $navigationItems->push([
                    'position' => (int) $settings->getAttribute('exhibitions_navigation_position'),
                    'tie_breaker' => 900002,
                    'label' => (string) $settings->getAttribute('exhibitions_navigation_label'),
                    'url' => route('exhibitions.index'),
                    'current' => request()->routeIs('exhibitions.*'),
                ]);
            }

            $blogSettings = BlogSetting::query()->findOrFail(1);
            if ((bool) $blogSettings->getAttribute('public_enabled')) {
                $navigationItems->push([
                    'position' => (int) $blogSettings->getAttribute('navigation_position'),
                    'tie_breaker' => 900003,
                    'label' => (string) $blogSettings->getAttribute('navigation_label'),
                    'url' => route('blog.index'),
                    'current' => request()->routeIs('blog.*'),
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
