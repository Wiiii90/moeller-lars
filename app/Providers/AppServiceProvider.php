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
        $adminAsset = static fn (string $path): string => app()->environment('production')
            ? secure_asset($path)
            : asset($path);

        FilamentAsset::register([
            Css::make('admin-system', $adminAsset('css/admin.css')),
            Css::make('admin-layouts', $adminAsset('css/admin-layouts.css')),
            Css::make('admin-forms', $adminAsset('css/admin-forms.css')),
            Css::make('admin-analytics', $adminAsset('css/admin-analytics.css')),
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
