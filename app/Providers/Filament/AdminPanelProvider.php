<?php

namespace App\Providers\Filament;

use App\Filament\Resources\ContactContentSettings\ContactContentSettingResource;
use App\Filament\Resources\PublicContentSettings\PublicContentSettingResource;
use App\Filament\Widgets\ArtistDashboard;
use App\Http\Middleware\DeferMatomoReporting;
use Filament\Http\Middleware\Authenticate;
use Filament\Http\Middleware\AuthenticateSession;
use Filament\Http\Middleware\DisableBladeIconComponents;
use Filament\Http\Middleware\DispatchServingFilamentEvent;
use Filament\Navigation\NavigationGroup;
use Filament\Navigation\NavigationItem;
use Filament\Panel;
use Filament\PanelProvider;
use Filament\Support\Colors\Color;
use Filament\Support\Icons\Heroicon;
use Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse;
use Illuminate\Cookie\Middleware\EncryptCookies;
use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;
use Illuminate\Routing\Middleware\SubstituteBindings;
use Illuminate\Session\Middleware\StartSession;
use Illuminate\View\Middleware\ShareErrorsFromSession;

class AdminPanelProvider extends PanelProvider
{
    public function panel(Panel $panel): Panel
    {
        return $panel
            ->default()
            ->id('admin')
            ->path('admin')
            ->login()
            ->authGuard('web')
            ->authPasswordBroker('users')
            ->revealablePasswords(false)
            ->brandName('Lars Möller')
            ->homeUrl(fn (): string => route('home'))
            ->colors([
                'primary' => Color::Amber,
            ])
            ->discoverResources(in: app_path('Filament/Resources'), for: 'App\\Filament\\Resources')
            ->discoverPages(in: app_path('Filament/Pages'), for: 'App\\Filament\\Pages')
            ->pages([])
            ->widgets([
                ArtistDashboard::class,
            ])
            ->navigationGroups([
                NavigationGroup::make()->label('Website'),
                NavigationGroup::make()->label('Content'),
                NavigationGroup::make()->label('Insights'),
                NavigationGroup::make()->label('Settings'),
            ])
            ->navigationItems([
                NavigationItem::make('General')
                    ->group('Settings')
                    ->icon(Heroicon::OutlinedCog6Tooth)
                    ->sort(10)
                    ->url(fn (): string => PublicContentSettingResource::getNavigationUrl()),
                NavigationItem::make('Contact')
                    ->group('Settings')
                    ->icon(Heroicon::OutlinedEnvelope)
                    ->sort(20)
                    ->url(fn (): string => ContactContentSettingResource::getSettingsUrl()),
            ])
            ->middleware([
                EncryptCookies::class,
                AddQueuedCookiesToResponse::class,
                StartSession::class,
                AuthenticateSession::class,
                ShareErrorsFromSession::class,
                PreventRequestForgery::class,
                SubstituteBindings::class,
                DisableBladeIconComponents::class,
                DispatchServingFilamentEvent::class,
            ])
            ->authMiddleware([
                Authenticate::class,
                DeferMatomoReporting::class,
            ], isPersistent: true);
    }
}
