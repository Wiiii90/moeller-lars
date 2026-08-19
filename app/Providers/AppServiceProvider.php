<?php

namespace App\Providers;

use App\Domain\Content\ArtworkCategorySiteSectionObserver;
use App\Domain\Content\PublicNavigationService;
use App\Models\ArtworkCategory;
use Filament\Support\Assets\Css;
use Filament\Support\Facades\FilamentAsset;
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

        ArtworkCategory::observe(ArtworkCategorySiteSectionObserver::class);

        View::composer('layouts.app', function ($view): void {
            $view->with('navigationItems', app(PublicNavigationService::class)->items());
        });
    }
}
