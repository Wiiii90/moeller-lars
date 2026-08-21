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
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;
use Illuminate\Routing\Middleware\SubstituteBindings;
use Illuminate\Session\Middleware\StartSession;
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
            ->navigation(fn (NavigationBuilder $builder): NavigationBuilder => $builder->groups($this->navigationGroups()))
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

    /** @return array<NavigationGroup> */
    private function navigationGroups(): array
    {
        return [
            NavigationGroup::make()
                ->label('Website')
                ->items([
                    ...Dashboard::getNavigationItems(),
                    ...SitePages::getNavigationItems(),
                    ...$this->siteSectionNavigationItems(),
                ]),
            NavigationGroup::make()
                ->label('Content')
                ->items([
                    ...MediaAssetResource::getNavigationItems(),
                ]),
            NavigationGroup::make()
                ->label('Insights')
                ->items([
                    ...Analytics::getNavigationItems(),
                    ...Activity::getNavigationItems(),
                    ...StorageCapacity::getNavigationItems(),
                ]),
            NavigationGroup::make()
                ->label('Settings')
                ->items([
                    NavigationItem::make('General')
                        ->icon(Heroicon::OutlinedCog6Tooth)
                        ->isActiveWhen(fn (): bool => original_request()->routeIs(PublicContentSettingResource::getNavigationItemActiveRoutePattern()))
                        ->sort(10)
                        ->url(fn (): string => PublicContentSettingResource::getNavigationUrl()),
                ]),
        ];
    }

    /** @return array<NavigationItem> */
    private function siteSectionNavigationItems(): array
    {
        /** @var EloquentCollection<int, SiteSection> $sections */
        $sections = SiteSection::query()
            ->orderBy('position')
            ->orderBy('id')
            ->get();

        $items = [];
        $sort = 10;

        foreach ($sections as $section) {
            if ($section->getAttribute('parent_id') !== null) {
                continue;
            }

            $items[] = $this->siteSectionNavigationItem($section, depth: 0, sort: $sort++);

            foreach ($sections as $child) {
                if ((int) $child->getAttribute('parent_id') !== (int) $section->getKey()) {
                    continue;
                }

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
            ->parentItem('Pages')
            ->sort($sort)
            ->url(SitePages::getUrl().'#site-section-'.$section->getKey());
    }
}
