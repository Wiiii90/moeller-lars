<?php

namespace App\Providers\Filament;

use App\Filament\Pages\Activity;
use App\Filament\Pages\Analytics;
use App\Filament\Pages\Dashboard;
use App\Filament\Pages\SitePages;
use App\Filament\Pages\StorageCapacity;
use App\Filament\Resources\Artworks\ArtworkResource;
use App\Filament\Resources\BlogPosts\BlogPostResource;
use App\Filament\Resources\CustomPageSettings\CustomPageSettingResource;
use App\Filament\Resources\Exhibitions\ExhibitionResource;
use App\Filament\Resources\MediaAssets\MediaAssetResource;
use App\Filament\Resources\PublicContentSettings\PublicContentSettingResource;
use App\Filament\Widgets\ArtistDashboard;
use App\Filament\Widgets\ContactHealth;
use App\Http\Middleware\DeferMatomoReporting;
use App\Models\CustomPageSetting;
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
            ->with('customPageSetting')
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

            $items[] = $this->siteSectionNavigationItem($section, $childrenByParent, 0);
        }

        return $items;
    }

    /** @param array<int, list<SiteSection>> $childrenByParent */
    private function siteSectionNavigationItem(SiteSection $section, array $childrenByParent, int $depth): NavigationItem
    {
        $label = trim((string) ($section->getAttribute('navigation_label') ?: $section->getAttribute('title')));
        $children = $childrenByParent[(int) $section->getKey()] ?? [];
        $url = $this->siteSectionWorkspaceUrl($section);

        $item = NavigationItem::make($label)
            ->key('site-section-'.$section->getKey())
            ->icon($this->siteSectionNavigationIcon($section))
            ->url($url)
            ->isActiveWhen(fn (): bool => $url !== null && $this->navigationUrlIsActive($url))
            ->extraAttributes([
                'data-artist-site-section' => (string) $section->getKey(),
                'data-artist-site-section-depth' => (string) $depth,
                'data-artist-site-section-type' => (string) $section->getAttribute('type'),
                'data-artist-tree-branch' => $children === [] ? 'false' : 'true',
            ]);

        if ($children !== []) {
            $item->childItems(array_map(
                fn (SiteSection $child): NavigationItem => $this->siteSectionNavigationItem($child, $childrenByParent, $depth + 1),
                $children,
            ));
        }

        return $item;
    }

    private function siteSectionNavigationIcon(SiteSection $section): Heroicon
    {
        return match ((string) $section->getAttribute('type')) {
            SiteSection::TYPE_HOME => Heroicon::OutlinedHome,
            SiteSection::TYPE_GALLERY => Heroicon::OutlinedPhoto,
            SiteSection::TYPE_JOURNAL => Heroicon::OutlinedPencilSquare,
            SiteSection::TYPE_CUSTOM => Heroicon::OutlinedDocumentText,
            SiteSection::TYPE_NAVIGATION_GROUP => Heroicon::OutlinedFolder,
            default => Heroicon::OutlinedRectangleStack,
        };
    }

    private function siteSectionWorkspaceUrl(SiteSection $section): ?string
    {
        $fallback = SitePages::getUrl().'#site-section-'.$section->getKey();
        $type = (string) $section->getAttribute('type');

        if ($type === SiteSection::TYPE_NAVIGATION_GROUP) {
            return null;
        }

        if ($type === SiteSection::TYPE_HOME) {
            return ArtworkResource::getUrl('index');
        }

        if ($type === SiteSection::TYPE_GALLERY) {
            $galleryId = $section->getAttribute('artwork_category_id');

            return is_numeric($galleryId)
                ? ArtworkResource::getUrl('gallery', ['gallery' => (int) $galleryId])
                : $fallback;
        }

        if ($type === SiteSection::TYPE_CUSTOM) {
            $settings = $section->getRelation('customPageSetting');

            return $settings instanceof CustomPageSetting
                ? CustomPageSettingResource::getUrl('edit', ['record' => $settings])
                : $fallback;
        }

        if ($type === SiteSection::TYPE_JOURNAL) {
            return $section->getAttribute('template') === SiteSection::JOURNAL_TEMPLATE_EXHIBITIONS
                ? ExhibitionResource::getUrl('index', ['section' => $section->getKey()])
                : BlogPostResource::getUrl('index', ['section' => $section->getKey()]);
        }

        return $fallback;
    }

    private function navigationUrlIsActive(string $url): bool
    {
        $target = parse_url($url);
        $targetPath = $target['path'] ?? null;
        if (! is_string($targetPath) || $targetPath === '') {
            return false;
        }

        $request = original_request();
        $requestPath = '/'.ltrim($request->path(), '/');
        if (rtrim($requestPath, '/') !== rtrim($targetPath, '/')) {
            return false;
        }

        $query = [];
        if (isset($target['query'])) {
            parse_str($target['query'], $query);
        }

        foreach ($query as $key => $value) {
            if ((string) $request->query((string) $key) !== (string) $value) {
                return false;
            }
        }

        return true;
    }
}
