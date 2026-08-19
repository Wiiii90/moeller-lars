<?php

namespace App\Providers;

use App\Domain\Content\ArtworkCategorySiteSectionObserver;
use App\Domain\Content\BlogSettingSiteSectionObserver;
use App\Domain\Content\PublicContentSettingSiteSectionObserver;
use App\Domain\Content\PublicNavigationService;
use App\Models\ArtworkCategory;
use App\Models\BlogSetting;
use App\Models\PublicContentSetting;
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
        $editorialStylesheet = app()->environment('production')
            ? secure_asset('css/filament-editorial.css')
            : asset('css/filament-editorial.css');

        FilamentAsset::register([
            Css::make('analytics-dashboard', $analyticsStylesheet),
            Css::make('artist-editorial', $editorialStylesheet),
        ]);

        ArtworkCategory::observe(ArtworkCategorySiteSectionObserver::class);
        PublicContentSetting::observe(PublicContentSettingSiteSectionObserver::class);
        BlogSetting::observe(BlogSettingSiteSectionObserver::class);

        View::composer('layouts.app', function ($view): void {
            $view->with('navigationItems', app(PublicNavigationService::class)->items());
        });
    }
}
