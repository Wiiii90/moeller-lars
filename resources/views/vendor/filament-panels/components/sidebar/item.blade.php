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
    $isArtistTreeRoot = $attributes->get('data-artist-tree-root') === 'true';
    $startsOpen = $isArtistTreeRoot || $active || $activeChildItems;
@endphp

<li
    @if ($hasChildItems)
        x-data="{ artistChildrenOpen: @js($startsOpen) }"
    @endif
    {{
        $attributes->class([
            'fi-sidebar-item',
            'fi-active' => $active,
            'fi-sidebar-item-has-active-child-items' => $activeChildItems,
            'fi-sidebar-item-has-url' => filled($url),
        ])->style($hasChildItems && (! $isArtistTreeRoot) ? 'position: relative;' : null)
    }}
>
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
        @if ($hasChildItems && (! $isArtistTreeRoot))
            style="padding-inline-end: 2.5rem;"
        @endif
    >
        @if (filled($icon) && ((! $subGrouped) || ($sidebarCollapsible && (! $subNavigation))))
            {{
                \Filament\Support\generate_icon_html(($active && $activeIcon) ? $activeIcon : $icon, attributes: (new \Filament\Support\View\ComponentAttributeBag([
                    'x-show' => ($subGrouped && $sidebarCollapsible) ? '! $store.sidebar.isOpen' : false,
                ]))->class(['fi-sidebar-item-icon']), size: \Filament\Support\Enums\IconSize::Large)
            }}
        @endif

        @if ((blank($icon) && $grouped) || $subGrouped)
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
        >
            {{ $slot }}
        </span>

        @if (filled($badge))
            <span
                @if ($sidebarCollapsible && (! $subNavigation))
                    x-show="$store.sidebar.isOpen"
                    x-transition:enter="fi-transition-enter"
                    x-transition:enter-start="fi-transition-enter-start"
                    x-transition:enter-end="fi-transition-enter-end"
                @endif
                class="fi-sidebar-item-badge-ctn"
            >
                <x-filament::badge :color="$badgeColor" :tooltip="$badgeTooltip">
                    {{ $badge }}
                </x-filament::badge>
            </span>
        @endif
    </a>

    @if ($hasChildItems && (! $isArtistTreeRoot))
        <button
            type="button"
            x-show="$store.sidebar.isOpen"
            x-on:click.stop.prevent="artistChildrenOpen = ! artistChildrenOpen"
            x-bind:aria-expanded="artistChildrenOpen.toString()"
            aria-label="Toggle {{ trim(strip_tags($slot->toHtml())) }} children"
            style="position:absolute;inset-inline-end:.45rem;top:.42rem;z-index:2;display:grid;width:1.65rem;height:1.65rem;padding:0;place-items:center;border:0;border-radius:.25rem;background:transparent;color:currentColor;cursor:pointer;"
        >
            <span
                aria-hidden="true"
                style="display:inline-block;font-size:.8rem;line-height:1;transition:transform 150ms ease;"
                x-bind:style="artistChildrenOpen ? 'transform: rotate(90deg)' : 'transform: rotate(0deg)'"
            >›</span>
        </button>
    @endif

    @if ($hasChildItems)
        <ul
            @if (! $isArtistTreeRoot)
                x-show="artistChildrenOpen"
                x-cloak
            @endif
            class="fi-sidebar-sub-group-items"
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
                    $childItemExtraAttributes = $childItem->getExtraAttributeBag()->merge(['data-artist-tree-branch' => 'true']);
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
                >
                    {{ $childItem->getLabel() }}
                </x-filament-panels::sidebar.item>
            @endforeach
        </ul>
    @endif
</li>
