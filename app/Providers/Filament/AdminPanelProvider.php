<?php

namespace App\Providers\Filament;

use App\Domain\Publication\PublicationService;
use App\Filament\Pages\Activity;
use App\Filament\Pages\Analytics;
use App\Filament\Pages\Dashboard;
use App\Filament\Pages\General;
use App\Filament\Pages\SitePages;
use App\Filament\Pages\StorageCapacity;
use App\Filament\Resources\MediaAssets\MediaAssetResource;
use App\Filament\Support\SiteNavigation;
use App\Filament\Widgets\ContactHealth;
use App\Http\Middleware\DeferMatomoReporting;
use Filament\Http\Middleware\Authenticate;
use Filament\Http\Middleware\AuthenticateSession;
use Filament\Http\Middleware\DisableBladeIconComponents;
use Filament\Http\Middleware\DispatchServingFilamentEvent;
use Filament\Navigation\NavigationBuilder;
use Filament\Navigation\NavigationItem;
use Filament\Panel;
use Filament\PanelProvider;
use Filament\Support\Colors\Color;
use Filament\Support\Icons\Heroicon;
use Filament\View\PanelsRenderHook;
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
            ->breadcrumbs(false)
            ->globalSearch(false)
            ->colors([
                'primary' => Color::Amber,
            ])
            ->renderHook(
                PanelsRenderHook::STYLES_AFTER,
                fn (): string => view('filament.partials.admin-theme')->render(),
            )
            ->renderHook(
                PanelsRenderHook::BODY_END,
                fn (): string => view('filament.partials.publication-commit-dialog')->render(),
            )
            ->discoverResources(in: app_path('Filament/Resources'), for: 'App\\Filament\\Resources')
            ->discoverPages(in: app_path('Filament/Pages'), for: 'App\\Filament\\Pages')
            ->pages([])
            ->widgets([
                ContactHealth::class,
            ])
            ->navigation(fn (NavigationBuilder $builder): NavigationBuilder => $this->navigation($builder))
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

    private function navigation(NavigationBuilder $builder): NavigationBuilder
    {
        $generalItem = General::getNavigationItems()[0]->group(null);
        $pagesItem = SitePages::getNavigationItems()[0]
            ->group(null)
            ->childItems(app(SiteNavigation::class)->items())
            ->extraAttributes(['data-admin-tree-root' => 'true']);
        $analyticsItem = Analytics::getNavigationItems()[0]->group(null);
        $activityItem = Activity::getNavigationItems()[0]->group(null);
        $storageItem = StorageCapacity::getNavigationItems()[0]->group(null);
        $previewItem = NavigationItem::make('Preview')
            ->group(null)
            ->icon(Heroicon::OutlinedEye)
            ->url(route('preview.home'))
            ->openUrlInNewTab();
        $commitItem = NavigationItem::make('Commit')
            ->group(null)
            ->icon(Heroicon::OutlinedCheckCircle)
            ->url('#')
            ->extraAttributes(function (): array {
                if (! app(PublicationService::class)->hasPendingChanges()) {
                    return [
                        'aria-disabled' => 'true',
                        'data-publication-commit' => 'disabled',
                        'style' => 'opacity: .5;',
                        'x-on:click.prevent' => 'void 0',
                    ];
                }

                return [
                    'data-publication-commit' => 'enabled',
                    'x-on:click.prevent' => "\$dispatch('publication-commit-open')",
                ];
            });

        return $builder
            ->items([
                ...Dashboard::getNavigationItems(),
                $generalItem,
                ...MediaAssetResource::getNavigationItems(),
                $pagesItem,
                $analyticsItem,
                $activityItem,
                $storageItem,
                $previewItem,
                $commitItem,
            ]);
    }
}
