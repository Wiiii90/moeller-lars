<?php

namespace App\Providers;

use App\Models\ArtworkCategory;
use App\Models\PublicContentSetting;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\View;
use Illuminate\Support\ServiceProvider;
use LogicException;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        View::composer('layouts.app', function ($view): void {
            /** @var Collection<int, array{position:int,label:string,url:string,current:bool}> $navigationItems */
            $navigationItems = ArtworkCategory::query()
                ->where('state', 'published')
                ->where('show_in_navigation', true)
                ->orderBy('position')
                ->get(['name', 'slug', 'position'])
                ->map(static fn (ArtworkCategory $category): array => [
                    'position' => (int) $category->getAttribute('position'),
                    'label' => (string) $category->getAttribute('name'),
                    'url' => route('artworks.category', ['category' => $category->getAttribute('slug')]),
                    'current' => request()->routeIs('artworks.category')
                        && request()->route('category') === $category->getAttribute('slug'),
                ]);

            $settings = PublicContentSetting::query()->findOrFail(1);
            if ($settings->cvSurfaceEnabled()) {
                $navigationItems->push([
                    'position' => (int) $settings->getAttribute('cv_navigation_position'),
                    'label' => (string) $settings->getAttribute('cv_navigation_label'),
                    'url' => route('cv'),
                    'current' => request()->routeIs('cv'),
                ]);
            }

            $ambiguousPositions = $navigationItems
                ->groupBy('position')
                ->filter(static fn (Collection $items): bool => $items->count() !== 1);

            if ($ambiguousPositions->isNotEmpty()) {
                throw new LogicException('Public navigation positions must be unique.');
            }

            $view->with('navigationItems', $navigationItems->sortBy('position')->values());
        });
    }
}
