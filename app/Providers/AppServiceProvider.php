<?php

namespace App\Providers;

use App\Domain\Content\PublicNavigationService;
use App\Domain\Media\PublicMedia;
use App\Models\MediaAsset;
use App\Models\MediaVariant;
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

        View::composer('layouts.app', function ($view): void {
            $settings = PublicContentSetting::query()
                ->with('faviconMediaAsset.variants')
                ->find(1);
            $faviconVariant = null;
            $faviconAsset = $settings?->getRelationValue('faviconMediaAsset');

            if ($faviconAsset instanceof MediaAsset && $faviconAsset->getAttribute('state') === 'available') {
                $variants = $faviconAsset->getRelationValue('variants');
                $faviconVariant = $variants->first(
                    fn (MediaVariant $variant): bool => $variant->getAttribute('variant_kind') === PublicMedia::THUMBNAIL_KIND
                        && $variant->getAttribute('transform_profile') === PublicMedia::PUBLIC_TRANSFORM_PROFILE
                        && $variant->getAttribute('state') === 'available',
                );
            }

            $view->with([
                'navigationItems' => app(PublicNavigationService::class)->items(),
                'faviconVariant' => $faviconVariant,
            ]);
        });
    }
}
