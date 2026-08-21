<?php

namespace App\Providers;

use App\Domain\Content\PublicSiteContext;
use Filament\Support\Assets\Css;
use Filament\Support\Facades\FilamentAsset;
use Filament\Support\Facades\FilamentView;
use Filament\View\PanelsRenderHook;
use Illuminate\Contracts\View\View as ViewContract;
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
        $unifiedStylesheet = app()->environment('production')
            ? secure_asset('css/filament-unified.css')
            : asset('css/filament-unified.css');

        FilamentAsset::register([
            Css::make('analytics-dashboard', $analyticsStylesheet),
            Css::make('artist-editorial', $editorialStylesheet),
            Css::make('artist-unified', $unifiedStylesheet),
        ]);

        FilamentView::registerRenderHook(
            PanelsRenderHook::USER_MENU_BEFORE,
            fn (): ViewContract => view('filament.partials.topbar-sign-out'),
        );

        View::composer('layouts.app', function ($view): void {
            $view->with(app(PublicSiteContext::class)->layoutData());
        });
    }
}
