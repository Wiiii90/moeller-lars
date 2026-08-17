<?php

namespace App\Providers;

use App\Models\ArtworkCategory;
use App\Models\BlogSetting;
use App\Models\PublicContentSetting;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\View;
use Illuminate\Support\ServiceProvider;
use LogicException;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        //
    }

    public function boot(): void
    {
        View::composer('layouts.app', function ($view): void {
            /** @var EloquentCollection<int, ArtworkCategory> $categories */
            $categories = ArtworkCategory::query()
                ->where('state', 'published')
                ->where('show_in_navigation', true)
                ->orderBy('position')
                ->get(['name', 'slug', 'position']);

            /** @var Collection<int, array{position:int,label:string,url:string,current:bool}> $navigationItems */
            $navigationItems = $categories->map(static fn (ArtworkCategory $category): array => [
                'position' => (int) $category->getAttribute('position'),
                'label' => (string) $category->getAttribute('name'),
                'url' => route('artworks.category', ['category' => $category->getAttribute('slug')]),
                'current' => request()->routeIs('artworks.category')
                    && request()->route('category') === $category->getAttribute('slug'),
            ]);

            $settings = PublicContentSetting::query()->findOrFail(1);
            if ((bool) $settings->getAttribute('cv_enabled')) {
                $navigationItems->push([
                    'position' => (int) $settings->getAttribute('cv_navigation_position'),
                    'label' => (string) $settings->getAttribute('cv_navigation_label'),
                    'url' => route('cv'),
                    'current' => request()->routeIs('cv'),
                ]);
            }
            if ((bool) $settings->getAttribute('exhibitions_enabled')) {
                $navigationItems->push([
                    'position' => (int) $settings->getAttribute('exhibitions_navigation_position'),
                    'label' => (string) $settings->getAttribute('exhibitions_navigation_label'),
                    'url' => route('exhibitions.index'),
                    'current' => request()->routeIs('exhibitions.*'),
                ]);
            }

            $blogSettings = BlogSetting::query()->findOrFail(1);
            if ((bool) $blogSettings->getAttribute('public_enabled')) {
                $navigationItems->push([
                    'position' => (int) $blogSettings->getAttribute('navigation_position'),
                    'label' => (string) $blogSettings->getAttribute('navigation_label'),
                    'url' => route('blog.index'),
                    'current' => request()->routeIs('blog.*'),
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
