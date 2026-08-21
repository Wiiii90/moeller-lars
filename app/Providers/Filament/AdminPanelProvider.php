<?php

namespace App\Providers\Filament;

use App\Filament\Pages\Activity;
use App\Filament\Pages\Analytics;
use App\Filament\Pages\Dashboard;
use App\Filament\Pages\SitePages;
use App\Filament\Pages\StorageCapacity;
use App\Filament\Resources\MediaAssets\MediaAssetResource;
use App\Filament\Resources\PublicContentSettings\PublicContentSettingResource;
use App\Filament\Widgets\ArtistDashboard;
use App\Filament\Widgets\ContactHealth;
use App\Http\Middleware\DeferMatomoReporting;
use App\Models\SiteSection;
use Filament\Http\Middleware\Authenticate;
use Filament\Http\Middleware\AuthenticateSession;
use Filament\Http\Middleware\DisableBladeIconComponents;
use Filament\Http\Middleware\DispatchServingFilamentEvent;
use Filament\Navigation\NavigationBuilder;
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
use Illuminate\Support\Facades\Schema;
use Illuminate\View\Middleware\ShareErrorsFromSession;
use function Filament\Support\original_request;

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
                ContactHealth::class,
            ])
            ->navigationGroups([
                NavigationGroup::make()->label('Website'),
                NavigationGroup::make()->label('Content'),
                NavigationGroup::make()->label('Insights'),
                NavigationGroup::make()->label('Settings'),
            ])
            ->navigation(fn (NavigationBuilder $builder): NavigationBuilder => $builder->items($this->navigationItems()))
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

    /** @return array<NavigationItem> */
    private function navigationItems(): array
    {
        return [
            ...Dashboard::getNavigationItems(),
            ...SitePages::getNavigationItems(),
            ...$this->siteSectionNavigationItems(),
            ...MediaAssetResource::getNavigationItems(),
            ...StorageCapacity::getNavigationItems(),
            ...Analytics::getNavigationItems(),
            ...Activity::getNavigationItems(),
            NavigationItem::make('General')
                ->key(PublicContentSettingResource::class)
                ->group('Settings')
                ->icon(Heroicon::OutlinedCog6Tooth)
                ->isActiveWhen(fn (): bool => original_request()->routeIs(PublicContentSettingResource::getNavigationItemActiveRoutePattern()))
                ->sort(10)
                ->url(fn (): string => PublicContentSettingResource::getNavigationUrl()),
        ];
    }

    /** @return array<NavigationItem> */
    private function siteSectionNavigationItems(): array
    {
        if (! Schema::hasTable('site_sections')) {
            return [];
        }

        $sections = SiteSection::query()
            ->whereNull('parent_id')
            ->with(['children' => static function ($relation): void {
                $relation->orderBy('position')->orderBy('id');
            }])
            ->orderBy('position')
            ->orderBy('id')
            ->get();

        $items = [];
        $sort = 10;

        foreach ($sections as $section) {
            $items[] = $this->siteSectionNavigationItem($section, depth: 0, sort: $sort++);

            foreach ($section->children as $child) {
                $items[] = $this->siteSectionNavigationItem($child, depth: 1, sort: $sort++);
            }
        }

        return $items;
    }

    private function siteSectionNavigationItem(SiteSection $section, int $depth, int $sort): NavigationItem
    {
        $label = trim((string) ($section->getAttribute('navigation_label') ?: $section->getAttribute('title')));
        if ($depth > 0) {
            $label = '↳ '.$label;
        }

        return NavigationItem::make($label)
            ->key('site-section:'.$section->getKey())
            ->group('Website')
            ->parentItem('Pages')
            ->sort($sort)
            ->url(SitePages::getUrl().'#site-section-'.$section->getKey());
    }
}
