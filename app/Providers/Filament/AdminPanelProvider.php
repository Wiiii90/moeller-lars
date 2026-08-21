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
        $pagesItem = SitePages::getNavigationItems()[0]
            ->childItems($this->siteSectionNavigationItems())
            ->extraAttributes(['data-artist-tree-root' => 'true']);

        return $builder
            ->items([
                ...Dashboard::getNavigationItems(),
                NavigationItem::make('General')
                    ->icon(Heroicon::OutlinedGlobeAlt)
                    ->isActiveWhen(fn (): bool => original_request()->routeIs(PublicContentSettingResource::getNavigationItemActiveRoutePattern()))
                    ->sort(1)
                    ->url(fn (): string => PublicContentSettingResource::getNavigationUrl()),
                ...MediaAssetResource::getNavigationItems(),
            ])
            ->groups([
                NavigationGroup::make()
                    ->label('Website')
                    ->collapsible()
                    ->items([$pagesItem]),
                NavigationGroup::make()
                    ->label('Insights')
                    ->items([
                        ...Analytics::getNavigationItems(),
                        ...Activity::getNavigationItems(),
                        ...StorageCapacity::getNavigationItems(),
                    ]),
            ]);
    }

    /** @return array<NavigationItem> */
    private function siteSectionNavigationItems(): array
    {
        /** @var EloquentCollection<int, SiteSection> $sections */
        $sections = SiteSection::query()
            ->orderBy('position')
            ->orderBy('id')
            ->get();

        /** @var array<int, list<SiteSection>> $childrenByParent */
        $childrenByParent = [];
        foreach ($sections as $section) {
            $parentId = $section->getAttribute('parent_id');
            if (is_numeric($parentId)) {
                $childrenByParent[(int) $parentId][] = $section;
            }
        }

        $items = [];
        foreach ($sections as $section) {
            if ($section->getAttribute('parent_id') !== null) {
                continue;
            }

            $items[] = $this->siteSectionNavigationItem($section, $childrenByParent);
        }

        return $items;
    }

    /** @param array<int, list<SiteSection>> $childrenByParent */
    private function siteSectionNavigationItem(SiteSection $section, array $childrenByParent): NavigationItem
    {
        $label = trim((string) ($section->getAttribute('navigation_label') ?: $section->getAttribute('title')));
        $children = $childrenByParent[(int) $section->getKey()] ?? [];

        $item = NavigationItem::make($label)
            ->key('site-section-'.$section->getKey())
            ->url(SitePages::getUrl().'#site-section-'.$section->getKey())
            ->extraAttributes([
                'data-artist-site-section' => (string) $section->getKey(),
                'data-artist-site-section-type' => (string) $section->getAttribute('type'),
            ]);

        if ($children !== []) {
            $item
                ->icon(Heroicon::OutlinedFolder)
                ->childItems(array_map(
                    fn (SiteSection $child): NavigationItem => $this->siteSectionNavigationItem($child, $childrenByParent),
                    $children,
                ));
        }

        return $item;
    }
}
