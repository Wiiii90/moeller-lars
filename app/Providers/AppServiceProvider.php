<?php

namespace App\Providers;

use App\Models\ArtworkCategory;
use Illuminate\Support\Facades\View;
use Illuminate\Support\ServiceProvider;

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
            $view->with('navigationCategories', ArtworkCategory::query()
                ->where('state', 'published')
                ->where('show_in_navigation', true)
                ->orderBy('position')
                ->get(['name', 'slug']));
        });
    }
}
