<?php

namespace App\Providers\Filament;

use App\Domain\Publication\PublicationService;
use App\Filament\Auth\Login;
use App\Filament\Auth\RequestPasswordReset;
use App\Filament\Auth\ResetPassword;
use App\Filament\Pages\Activity;
use App\Filament\Pages\Analytics;
use App\Filament\Pages\Dashboard;
use App\Filament\Pages\General;
use App\Filament\Pages\SitePages;
use App\Filament\Resources\MediaAssets\MediaAssetResource;
use App\Filament\Support\AccountMenuAction;
use App\Filament\Support\AdminIcon;
use App\Filament\Support\Controls\AdminControl;
use App\Filament\Support\SiteNavigation;
use App\Http\Middleware\DeferMatomoReporting;
use BladeUI\Icons\Factory as BladeIconFactory;
use Filament\Actions\Action;
use Filament\Auth\MultiFactor\App\AppAuthentication;
use Filament\Http\Middleware\Authenticate;
use Filament\Http\Middleware\AuthenticateSession;
use Filament\Http\Middleware\DisableBladeIconComponents;
use Filament\Http\Middleware\DispatchServingFilamentEvent;
use Filament\Navigation\NavigationBuilder;
use Filament\Navigation\NavigationItem;
use Filament\Panel;
use Filament\PanelProvider;
use Filament\Support\Colors\Color;
use Filament\View\PanelsRenderHook;
use Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse;
use Illuminate\Cookie\Middleware\EncryptCookies;
use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;
use Illuminate\Routing\Middleware\SubstituteBindings;
use Illuminate\Session\Middleware\StartSession;
use Illuminate\Support\HtmlString;
use Illuminate\View\Middleware\ShareErrorsFromSession;

class AdminPanelProvider extends PanelProvider
{
    public function boot(): void
    {
        AdminControl::register();

        $this->callAfterResolving(BladeIconFactory::class, static function (BladeIconFactory $factory): void {
            $factory->add('admin', [
                'path' => resource_path('svg/admin'),
                'prefix' => 'admin',
            ]);
        });
    }

    public function panel(Panel $panel): Panel
    {
        $appAuthentication = AppAuthentication::make()
            ->recoverable()
            ->brandName('Lars Möller Administration');

        return $panel
            ->default()
            ->id('admin')
            ->path('admin')
            ->login(Login::class)
            ->passwordReset(RequestPasswordReset::class, ResetPassword::class)
            ->multiFactorAuthentication([
                $appAuthentication,
            ], isRequired: false)
            ->userMenuItems([
                'profile' => fn (Action $action): Action => AccountMenuAction::configure($action, $appAuthentication),
            ])
            ->authGuard('web')
            ->authPasswordBroker('users')
            ->revealablePasswords(false)
            ->brandName('Admin Area')
            ->brandLogo(new HtmlString('Lars Möller'))
            ->favicon($this->adminFaviconUrl())
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
                PanelsRenderHook::AUTH_LOGIN_FORM_AFTER,
                fn (): string => view('filament.partials.admin-auth-back')->render(),
            )
            ->renderHook(
                PanelsRenderHook::AUTH_PASSWORD_RESET_REQUEST_FORM_AFTER,
                fn (): string => view('filament.partials.admin-auth-back')->render(),
            )
            ->renderHook(
                PanelsRenderHook::AUTH_PASSWORD_RESET_RESET_FORM_AFTER,
                fn (): string => view('filament.partials.admin-auth-back')->render(),
            )
            ->renderHook(
                PanelsRenderHook::BODY_END,
                fn (): string => view('filament.partials.admin-viz')->render(),
            )
            ->renderHook(
                PanelsRenderHook::BODY_END,
                fn (): string => view('filament.partials.publication-state-bridge-hook')->render(),
            )
            ->discoverResources(in: app_path('Filament/Resources'), for: 'App\\Filament\\Resources')
            ->discoverPages(in: app_path('Filament/Pages'), for: 'App\\Filament\\Pages')
            ->pages([])
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

    private function adminFaviconUrl(): string
    {
        $faviconPath = public_path('admin-favicon.svg');
        $faviconHash = is_file($faviconPath) ? hash_file('sha256', $faviconPath) : false;
        $version = $faviconHash !== false ? substr($faviconHash, 0, 12) : 'missing';

        return asset('admin-favicon.svg').'?v='.$version;
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
        $storageItem = MediaAssetResource::getNavigationItems()[0]->group(null);
        $previewItem = NavigationItem::make('Preview')
            ->group(null)
            ->icon(AdminIcon::Preview)
            ->url(route('preview.home'))
            ->openUrlInNewTab();
        $hasPendingChanges = app(PublicationService::class)->hasPendingChanges();
        $commitItem = NavigationItem::make('Commit')
            ->group(null)
            ->icon(AdminIcon::Commit)
            ->url('#')
            ->extraAttributes([
                'aria-disabled' => $hasPendingChanges ? 'false' : 'true',
                'data-publication-commit' => $hasPendingChanges ? 'enabled' : 'disabled',
                'style' => $hasPendingChanges ? '' : 'opacity: .5;',
                'x-data' => '{ publicationPending: '.($hasPendingChanges ? 'true' : 'false').' }',
                'x-on:publication-state-changed.window' => 'publicationPending = Boolean($event.detail.pending)',
                'x-bind:aria-disabled' => '(! publicationPending).toString()',
                'x-bind:data-publication-commit' => "publicationPending ? 'enabled' : 'disabled'",
                'x-bind:style' => "publicationPending ? '' : 'opacity: .5;'",
                'x-on:click.prevent' => "if (publicationPending) { \$dispatch('publication-commit') }",
            ]);
        $publicItem = NavigationItem::make('View Site')
            ->group(null)
            ->icon(AdminIcon::OpenPublic)
            ->url(route('home'))
            ->openUrlInNewTab();

        return $builder
            ->items([
                ...Dashboard::getNavigationItems(),
                $generalItem,
                $pagesItem,
                $storageItem,
                $activityItem,
                $analyticsItem,
                $previewItem,
                $commitItem,
                $publicItem,
            ]);
    }
}
