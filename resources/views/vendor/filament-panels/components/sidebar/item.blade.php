@props([
    'active' => false,
    'activeChildItems' => false,
    'activeIcon' => null,
    'badge' => null,
    'badgeColor' => null,
    'badgeTooltip' => null,
    'childItems' => [],
    'first' => false,
    'grouped' => false,
    'icon' => null,
    'last' => false,
    'shouldOpenUrlInNewTab' => false,
    'sidebarCollapsible' => true,
    'subGrouped' => false,
    'subNavigation' => false,
    'url',
])

@php
    $sidebarCollapsible = $sidebarCollapsible && filament()->isSidebarCollapsibleOnDesktop();
    $childItems = collect($childItems)->all();
    $hasChildItems = $childItems !== [];
    $alwaysOpen = $attributes->get('data-artist-tree-root') === 'true';
    $isArtistSiteSection = $attributes->has('data-artist-site-section');
    $usesNodeBranchControl = $isArtistSiteSection && $hasChildItems && filled($url);
    $startsOpen = $alwaysOpen || $active || $activeChildItems;
@endphp

<li
    @if ($hasChildItems)
        x-data="{ artistChildrenOpen: @js($startsOpen) }"
        x-effect="if (@js($alwaysOpen || $activeChildItems)) artistChildrenOpen = true"
    @endif
    {{
        $attributes->class([
            'fi-sidebar-item',
            'fi-active' => $active,
            'fi-sidebar-item-has-active-child-items' => $activeChildItems,
            'fi-sidebar-item-has-url' => filled($url),
            'artist-sidebar-tree__item' => $hasChildItems,
        ])
    }}
>
    <div @class(['artist-sidebar-tree__row' => $hasChildItems])>
        @if ($usesNodeBranchControl)
            <button
                type="button"
                class="artist-sidebar-tree__toggle"
                style="inset-inline-start: .2rem; inset-inline-end: auto;"
                x-on:click.stop="artistChildrenOpen = ! artistChildrenOpen"
                x-bind:aria-expanded="artistChildrenOpen.toString()"
                aria-label="Toggle {{ trim(strip_tags($slot->toHtml())) }} children"
                @if ($sidebarCollapsible && (! $subNavigation))
                    x-show="$store.sidebar.isOpen"
                @endif
            >
                {{
                    \Filament\Support\generate_icon_html(($active && $activeIcon) ? $activeIcon : $icon, attributes: (new \Filament\Support\View\ComponentAttributeBag)->class(['fi-sidebar-item-icon']), size: \Filament\Support\Enums\IconSize::Large)
                }}
            </button>
        @endif

        @if (filled($url))
            <a
                {{ \Filament\Support\generate_href_html($url, $shouldOpenUrlInNewTab) }}
                @if ($active)
                    aria-current="page"
                @endif
                x-on:click="window.matchMedia(`(max-width: 1024px)`).matches && $store.sidebar.close()"
                @if ($sidebarCollapsible && (! $subNavigation))
                    x-bind:aria-label="$store.sidebar.isOpen ? null : @js(trim(strip_tags($slot->toHtml())))"
                    x-data="{ tooltip: false }"
                    x-effect="
                        tooltip = $store.sidebar.isOpen
                            ? false
                            : {
                                  content: @js($slot->toHtml()),
                                  placement: document.dir === 'rtl' ? 'left' : 'right',
                                  theme: $store.theme,
                              }
                    "
                    x-tooltip.html="tooltip"
                @endif
                class="fi-sidebar-item-btn"
                @if ($usesNodeBranchControl && $sidebarCollapsible && (! $subNavigation))
                    x-bind:style="$store.sidebar.isOpen ? 'padding-inline-start: 2.25rem' : null"
                @elseif ($usesNodeBranchControl)
                    style="padding-inline-start: 2.25rem;"
                @endif
            >
                @if ($usesNodeBranchControl && $sidebarCollapsible && (! $subNavigation))
                    {{
                        \Filament\Support\generate_icon_html(($active && $activeIcon) ? $activeIcon : $icon, attributes: (new \Filament\Support\View\ComponentAttributeBag([
                            'x-show' => '! $store.sidebar.isOpen',
                        ]))->class(['fi-sidebar-item-icon']), size: \Filament\Support\Enums\IconSize::Large)
                    }}
                @elseif ((! $usesNodeBranchControl) && filled($icon) && ((! $subGrouped) || ($sidebarCollapsible && (! $subNavigation))))
                    {{
                        \Filament\Support\generate_icon_html(($active && $activeIcon) ? $activeIcon : $icon, attributes: (new \Filament\Support\View\ComponentAttributeBag([
                            'x-show' => ($subGrouped && $sidebarCollapsible) ? '! $store.sidebar.isOpen' : false,
                        ]))->class(['fi-sidebar-item-icon']), size: \Filament\Support\Enums\IconSize::Large)
                    }}
                @endif

                @if ((! $isArtistSiteSection) && ((blank($icon) && $grouped) || $subGrouped))
                    <div
                        @if (filled($icon) && $subGrouped && $sidebarCollapsible && (! $subNavigation))
                            x-show="$store.sidebar.isOpen"
                        @endif
                        class="fi-sidebar-item-grouped-border"
                    >
                        @if (! $first)
                            <div class="fi-sidebar-item-grouped-border-part-not-first"></div>
                        @endif
                        @if (! $last)
                            <div class="fi-sidebar-item-grouped-border-part-not-last"></div>
                        @endif
                        <div class="fi-sidebar-item-grouped-border-part"></div>
                    </div>
                @endif

                <span
                    @if ($sidebarCollapsible && (! $subNavigation))
                        x-show="$store.sidebar.isOpen"
                        x-transition:enter="fi-transition-enter"
                        x-transition:enter-start="fi-transition-enter-start"
                        x-transition:enter-end="fi-transition-enter-end"
                    @endif
                    class="fi-sidebar-item-label"
                >{{ $slot }}</span>

                @if (filled($badge))
                    <span
                        @if ($sidebarCollapsible && (! $subNavigation))
                            x-show="$store.sidebar.isOpen"
                        @endif
                        class="fi-sidebar-item-badge-ctn"
                    >
                        <x-filament::badge :color="$badgeColor" :tooltip="$badgeTooltip">{{ $badge }}</x-filament::badge>
                    </span>
                @endif
            </a>
        @elseif ($hasChildItems)
            <button
                type="button"
                class="fi-sidebar-item-btn artist-sidebar-tree__label-button"
                x-on:click="artistChildrenOpen = ! artistChildrenOpen"
                x-bind:aria-expanded="artistChildrenOpen.toString()"
            >
                @if (filled($icon))
                    {{ \Filament\Support\generate_icon_html($icon, attributes: (new \Filament\Support\View\ComponentAttributeBag)->class(['fi-sidebar-item-icon']), size: \Filament\Support\Enums\IconSize::Large) }}
                @endif
                <span class="fi-sidebar-item-label">{{ $slot }}</span>
                <span class="artist-sidebar-tree__chevron artist-sidebar-tree__chevron--embedded" aria-hidden="true" x-bind:class="{ 'is-open': artistChildrenOpen }"></span>
            </button>
        @else
            <span class="fi-sidebar-item-btn artist-sidebar-tree__static">
                @if (filled($icon))
                    {{ \Filament\Support\generate_icon_html($icon, attributes: (new \Filament\Support\View\ComponentAttributeBag)->class(['fi-sidebar-item-icon']), size: \Filament\Support\Enums\IconSize::Large) }}
                @endif
                <span class="fi-sidebar-item-label">{{ $slot }}</span>
            </span>
        @endif

        @if ($hasChildItems && (! $alwaysOpen) && filled($url) && (! $usesNodeBranchControl))
            <button
                type="button"
                class="artist-sidebar-tree__toggle"
                x-on:click.stop="artistChildrenOpen = ! artistChildrenOpen"
                x-bind:aria-expanded="artistChildrenOpen.toString()"
                aria-label="Toggle {{ trim(strip_tags($slot->toHtml())) }} children"
                @if ($sidebarCollapsible && (! $subNavigation))
                    x-show="$store.sidebar.isOpen"
                @endif
            ><span class="artist-sidebar-tree__chevron" aria-hidden="true" x-bind:class="{ 'is-open': artistChildrenOpen }"></span></button>
        @endif
    </div>

    @if ($hasChildItems)
        <ul
            class="fi-sidebar-sub-group-items artist-sidebar-tree__children"
            @unless ($alwaysOpen)
                x-show="artistChildrenOpen"
                x-cloak
            @endunless
        >
            @foreach ($childItems as $childItem)
                @php
                    $isChildItemChildItemsActive = $childItem->isChildItemsActive();
                    $isChildActive = (! $isChildItemChildItemsActive) && $childItem->isActive();
                    $childItemActiveIcon = $childItem->getActiveIcon();
                    $childItemBadge = $childItem->getBadge();
                    $childItemBadgeColor = $childItem->getBadgeColor($childItemBadge);
                    $childItemBadgeTooltip = $childItem->getBadgeTooltip($childItemBadge);
                    $childItemIcon = $childItem->getIcon();
                    $shouldChildItemOpenUrlInNewTab = $childItem->shouldOpenUrlInNewTab();
                    $childItemUrl = $childItem->getUrl();
                    $childItemExtraAttributes = $childItem->getExtraAttributeBag();
                @endphp

                <x-filament-panels::sidebar.item
                    :active="$isChildActive"
                    :active-child-items="$isChildItemChildItemsActive"
                    :active-icon="$childItemActiveIcon"
                    :badge="$childItemBadge"
                    :badge-color="$childItemBadgeColor"
                    :badge-tooltip="$childItemBadgeTooltip"
                    :child-items="$childItem->getChildItems()"
                    :first="$loop->first"
                    grouped
                    :icon="$childItemIcon"
                    :last="$loop->last"
                    :should-open-url-in-new-tab="$shouldChildItemOpenUrlInNewTab"
                    sub-grouped
                    :sub-navigation="$subNavigation"
                    :url="$childItemUrl"
                    :attributes="\Filament\Support\prepare_inherited_attributes($childItemExtraAttributes)"
                >{{ $childItem->getLabel() }}</x-filament-panels::sidebar.item>
            @endforeach
        </ul>
    @endif
</li>
