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
    $isTreeRoot = $attributes->get('data-admin-tree-root') === 'true';
    $isSiteTreeNode = $attributes->get('data-admin-site-section') !== null;
    $isAdminTreeItem = $isTreeRoot || $isSiteTreeNode;
    $startsOpen = $active || $activeChildItems;
    $plainLabel = trim(strip_tags($slot->toHtml()));
    $displayIcon = ($active && $activeIcon) ? $activeIcon : $icon;
@endphp

<li
    @if ($hasChildItems)
        x-data="{ adminChildrenOpen: @js($isAdminTreeItem ? true : $startsOpen) }"
        @unless ($isAdminTreeItem)
            x-effect="if (@js($activeChildItems)) adminChildrenOpen = true"
        @endunless
    @endif
    {{
        $attributes->class([
            'fi-sidebar-item',
            'fi-active' => $active,
            'fi-sidebar-item-has-active-child-items' => $activeChildItems,
            'fi-sidebar-item-has-url' => filled($url),
            'admin-sidebar-tree__item' => $hasChildItems,
            'admin-sidebar-tree__root' => $isTreeRoot,
            'admin-sidebar-tree__node' => $isSiteTreeNode,
            'admin-sidebar-tree__node--first' => $isSiteTreeNode && $first,
            'admin-sidebar-tree__node--last' => $isSiteTreeNode && $last,
            'admin-sidebar-tree__node--branch' => $isSiteTreeNode && $hasChildItems,
        ])
    }}
>
    @if ($isAdminTreeItem)
        <div class="admin-sidebar-tree__node-row">
            @if (filled($url))
                <a
                    {{ \Filament\Support\generate_href_html($url, $shouldOpenUrlInNewTab) }}
                    @if ($active)
                        aria-current="page"
                    @endif
                    aria-label="{{ $plainLabel }}"
                    x-on:click="window.matchMedia(`(max-width: 1024px)`).matches && $store.sidebar.close()"
                    class="admin-sidebar-tree__icon-link"
                >
                    <span class="admin-sidebar-tree__node-icon" aria-hidden="true">
                        @if (filled($displayIcon))
                            {{ \Filament\Support\generate_icon_html($displayIcon, attributes: (new \Filament\Support\View\ComponentAttributeBag)->class(['fi-sidebar-item-icon']), size: \Filament\Support\Enums\IconSize::Large) }}
                        @endif
                    </span>
                </a>
            @else
                <span class="admin-sidebar-tree__icon-link admin-sidebar-tree__static" aria-hidden="true">
                    <span class="admin-sidebar-tree__node-icon">
                        @if (filled($displayIcon))
                            {{ \Filament\Support\generate_icon_html($displayIcon, attributes: (new \Filament\Support\View\ComponentAttributeBag)->class(['fi-sidebar-item-icon']), size: \Filament\Support\Enums\IconSize::Large) }}
                        @endif
                    </span>
                </span>
            @endif

            <span
                class="admin-sidebar-tree__disclosure-slot"
                @if ($sidebarCollapsible && (! $subNavigation))
                    x-show="$store.sidebar.isOpen"
                @endif
            >
                @if ($hasChildItems)
                    <button
                        type="button"
                        class="admin-sidebar-tree__disclosure"
                        x-on:click.stop="adminChildrenOpen = ! adminChildrenOpen"
                        x-bind:aria-expanded="adminChildrenOpen.toString()"
                        aria-label="Toggle {{ $plainLabel }} children"
                    >
                        <span class="admin-sidebar-tree__chevron" aria-hidden="true" x-bind:class="{ 'is-open': adminChildrenOpen }"></span>
                    </button>
                @else
                    <span class="admin-sidebar-tree__disclosure-placeholder" aria-hidden="true"></span>
                @endif
            </span>

            @if (filled($url))
                <a
                    {{ \Filament\Support\generate_href_html($url, $shouldOpenUrlInNewTab) }}
                    @if ($active)
                        aria-current="page"
                    @endif
                    x-on:click="window.matchMedia(`(max-width: 1024px)`).matches && $store.sidebar.close()"
                    @if ($sidebarCollapsible && (! $subNavigation))
                        x-show="$store.sidebar.isOpen"
                    @endif
                    class="fi-sidebar-item-btn admin-sidebar-tree__node-label-link"
                >
                    <span class="fi-sidebar-item-label">{{ $slot }}</span>

                    @if (filled($badge))
                        <span class="fi-sidebar-item-badge-ctn">
                            <x-filament::badge :color="$badgeColor" :tooltip="$badgeTooltip">{{ $badge }}</x-filament::badge>
                        </span>
                    @endif
                </a>
            @else
                <span
                    @if ($sidebarCollapsible && (! $subNavigation))
                        x-show="$store.sidebar.isOpen"
                    @endif
                    class="fi-sidebar-item-btn admin-sidebar-tree__node-label-static"
                >
                    <span class="fi-sidebar-item-label">{{ $slot }}</span>
                </span>
            @endif
        </div>
    @else
        <div @class(['admin-sidebar-tree__row' => $hasChildItems])>
            @if (filled($url))
                <a
                    {{ \Filament\Support\generate_href_html($url, $shouldOpenUrlInNewTab) }}
                    @if ($active)
                        aria-current="page"
                    @endif
                    x-on:click="window.matchMedia(`(max-width: 1024px)`).matches && $store.sidebar.close()"
                    @if ($sidebarCollapsible && (! $subNavigation))
                        x-bind:aria-label="$store.sidebar.isOpen ? null : @js($plainLabel)"
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
                >
                    @if (filled($icon) && ((! $subGrouped) || ($sidebarCollapsible && (! $subNavigation))))
                        {{
                            \Filament\Support\generate_icon_html($displayIcon, attributes: (new \Filament\Support\View\ComponentAttributeBag([
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
                    class="fi-sidebar-item-btn admin-sidebar-tree__label-button"
                    x-on:click="adminChildrenOpen = ! adminChildrenOpen"
                    x-bind:aria-expanded="adminChildrenOpen.toString()"
                >
                    @if (filled($icon))
                        {{ \Filament\Support\generate_icon_html($icon, attributes: (new \Filament\Support\View\ComponentAttributeBag)->class(['fi-sidebar-item-icon']), size: \Filament\Support\Enums\IconSize::Large) }}
                    @endif
                    <span class="fi-sidebar-item-label">{{ $slot }}</span>
                    <span class="admin-sidebar-tree__chevron admin-sidebar-tree__chevron--embedded" aria-hidden="true" x-bind:class="{ 'is-open': adminChildrenOpen }"></span>
                </button>
            @else
                <span class="fi-sidebar-item-btn admin-sidebar-tree__static">
                    @if (filled($icon))
                        {{ \Filament\Support\generate_icon_html($icon, attributes: (new \Filament\Support\View\ComponentAttributeBag)->class(['fi-sidebar-item-icon']), size: \Filament\Support\Enums\IconSize::Large) }}
                    @endif
                    <span class="fi-sidebar-item-label">{{ $slot }}</span>
                </span>
            @endif

            @if ($hasChildItems && filled($url))
                <button
                    type="button"
                    class="admin-sidebar-tree__toggle"
                    x-on:click.stop="adminChildrenOpen = ! adminChildrenOpen"
                    x-bind:aria-expanded="adminChildrenOpen.toString()"
                    aria-label="Toggle {{ $plainLabel }} children"
                    @if ($sidebarCollapsible && (! $subNavigation))
                        x-show="$store.sidebar.isOpen"
                    @endif
                ><span class="admin-sidebar-tree__chevron" aria-hidden="true" x-bind:class="{ 'is-open': adminChildrenOpen }"></span></button>
            @endif
        </div>
    @endif

    @if ($hasChildItems)
        <ul
            class="fi-sidebar-sub-group-items admin-sidebar-tree__children"
            @if ($isAdminTreeItem && $sidebarCollapsible && (! $subNavigation))
                x-show="adminChildrenOpen && $store.sidebar.isOpen"
            @else
                x-show="adminChildrenOpen"
            @endif
            x-cloak
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
