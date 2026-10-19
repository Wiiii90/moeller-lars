<!doctype html>
@php
    $publicStyleNonce = request()->attributes->get(\App\Http\Middleware\SecurityHeaders::STYLE_NONCE_ATTRIBUTE);
    // URL-keyed corner positions are available before the first render.
    $folioSeed = hexdec(substr(hash('sha256', '/'.ltrim(request()->path(), '/')), 0, 8));
    $folioPlacement = [
        'leftTop' => (16 + (($folioSeed >> 1) % 11)).'px',
        'leftBottom' => (46 + (($folioSeed >> 5) % 19)).'px',
        'rightTop' => (45 + (($folioSeed >> 9) % 22)).'px',
        'rightBottom' => (13 + (($folioSeed >> 13) % 12)).'px',
        'menuLeftBottom' => (37 + (($folioSeed >> 17) % 10)).'%',
        'menuRightBottom' => (6 + (($folioSeed >> 21) % 8)).'%',
    ];
@endphp
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="csrf-token" content="{{ csrf_token() }}">
        <title>@yield('title')</title>
        <meta name="description" content="{{ trim($__env->yieldContent('meta_description', 'Lars Möller — artist')) }}">
        <link rel="canonical" href="{{ trim($__env->yieldContent('canonical', app(\App\Domain\Content\CanonicalUrl::class)->current())) }}">
        @if ($isPreview)
            <meta name="robots" content="noindex,nofollow,noarchive">
        @endif
        @if ($faviconVariant !== null)
            <link rel="icon" href="{{ app(\App\Domain\Media\PublicMedia::class)->variantUrl($faviconVariant) }}" type="{{ $faviconVariant->mime_type }}">
        @endif
        @php($matomoTracking = $isPreview ? null : app(\App\Domain\Analytics\MatomoConfiguration::class)->browserTracking())
        @if ($matomoTracking !== null)
            <meta
                data-matomo-tracking
                data-matomo-base-url="{{ $matomoTracking['base_url'] }}"
                data-matomo-site-id="{{ $matomoTracking['site_id'] }}"
            >
        @endif
        {{-- All public CSS is bundled in the single app.js Vite entry. --}}
        @vite('resources/js/app.js')
        {{-- Navigation uses Livewire's established document-swap runtime, not a
             second router. Nonces keep the public policy strict. --}}
        @livewireStyles(['nonce' => $publicStyleNonce])
        <meta data-public-layout
            data-public-layout-frame-style="{{ $publicFrameStyle }}"
            data-public-layout-background="{{ $publicBackgroundCss ?? '' }}"
            data-public-layout-menu-background="{{ $publicMenuBackgroundCss ?? '' }}"
            data-public-layout-content-background="{{ $publicContentBackgroundCss ?? '' }}"
            data-public-layout-shell="{{ $publicLayoutCss['shell'] }}"
            data-public-layout-art="{{ $publicLayoutCss['art'] }}"
            data-public-layout-padding="{{ $publicLayoutCss['padding'] }}"
            data-public-layout-title-color="{{ $publicHeaderColor ?? '' }}"
            data-public-layout-title-size="{{ $publicHeaderSize }}"
            data-public-layout-title-min-size="{{ $publicHeaderMinSize }}"
            data-public-layout-divider-color="{{ $publicHeaderDividerColor ?? '' }}"
            data-folio-left-top="{{ $folioPlacement['leftTop'] }}"
            data-folio-left-bottom="{{ $folioPlacement['leftBottom'] }}"
            data-folio-right-top="{{ $folioPlacement['rightTop'] }}"
            data-folio-right-bottom="{{ $folioPlacement['rightBottom'] }}"
            data-folio-menu-left-bottom="{{ $folioPlacement['menuLeftBottom'] }}"
            data-folio-menu-right-bottom="{{ $folioPlacement['menuRightBottom'] }}">
        @if (is_string($publicStyleNonce) && $publicStyleNonce !== '')
            <style data-public-layout-style nonce="{{ $publicStyleNonce }}">:root {@if ($publicBackgroundCss !== null) --public-page: {{ $publicBackgroundCss }};@endif @if ($publicMenuBackgroundCss !== null) --public-menu-background: {{ $publicMenuBackgroundCss }};@endif @if ($publicContentBackgroundCss !== null) --public-content-background: {{ $publicContentBackgroundCss }};@endif @if ($publicHeaderColor !== null) --public-title-color: {{ $publicHeaderColor }};@endif @if ($publicHeaderDividerColor !== null) --public-separator-color: {{ $publicHeaderDividerColor }};@endif --public-title-size: {{ $publicHeaderSize }}; --public-title-min-size: {{ $publicHeaderMinSize }}; --public-shell-max: {{ $publicLayoutCss['shell'] }}; --public-art-max: {{ $publicLayoutCss['art'] }}; --public-content-padding: {{ $publicLayoutCss['padding'] }}; --public-folio-left-top: {{ $folioPlacement['leftTop'] }}; --public-folio-left-bottom: {{ $folioPlacement['leftBottom'] }}; --public-folio-right-top: {{ $folioPlacement['rightTop'] }}; --public-folio-right-bottom: {{ $folioPlacement['rightBottom'] }}; --public-menu-left-bottom: {{ $folioPlacement['menuLeftBottom'] }}; --public-menu-right-bottom: {{ $folioPlacement['menuRightBottom'] }}; }</style>
        @endif
        {{-- Dynamic component dimensions live in nonce-authorized CSS, never
             forbidden inline style attributes. --}}
        @stack('public-component-styles')
    </head>
    <body class="public-site public-site--frame-{{ $publicFrameStyle }}">
        @if ($isPreview)
            <div
                class="public-preview-badge"
                role="status"
                aria-label="Artist preview mode"
            >PREVIEW</div>
        @endif
        {{-- One paper/frame ownership decision for every Public template.
             Incompatible shapes (landscape, transparent or detached title)
             keep two independent sheets, never mismatched joined outlines. --}}
        @php
            $paperCompatible = ! ($publicHeaderPosition === 'below' && $publicHeaderVisible)
                && ! $publicPageTransparent
                && ! str_contains($__env->yieldContent('content_class'), 'landscape-artwork');
            $paperConnected = $paperCompatible && $publicHeaderDivider === 'fine';
        @endphp
        <div class="public-paper-frame @if ($paperCompatible) public-paper-frame--compatible @endif @if ($paperConnected) public-paper-frame--connected @endif">
            <div class="public-paper-decoration" aria-hidden="true"></div>
        <header class="site-header site-header--name-{{ $publicHeaderPosition }} site-header--caps-{{ $publicHeaderCaps }} site-header--divider-{{ $publicHeaderDivider }} site-header--title-align-{{ $publicHeaderAlignment }} site-header--mobile-title-align-{{ $publicHeaderMobileAlignment }} @unless ($publicHeaderVisible) site-header--title-hidden @endunless @if ($publicHeaderAutoScale) site-header--title-fluid @endif">
            @if ($publicHeaderVisible && ($publicHeaderPosition === 'below' || $__env->hasSection('hide_navigation')))
                <x-public.artist-name :home-url="$homeUrl" :title="$publicHeaderText" />
            @endif
            @unless ($__env->hasSection('hide_navigation'))
                @php($selectedNavigationParent = collect($navigationItems)->first(fn ($item) => $item['active'] && $item['children'] !== []))
                <div class="site-navigation" data-site-navigation data-hover-preview="{{ $publicNavigationHover ? 'true' : 'false' }}">
                    @if ($publicHeaderVisible && $publicHeaderPosition !== 'below')
                        <x-public.artist-name :home-url="$homeUrl" :title="$publicHeaderText" />
                    @endif
                    <div class="site-navigation__rail" data-navigation-main @if ($selectedNavigationParent) hidden @endif>
                        <button class="site-navigation__control" type="button" data-direction="previous" aria-label="Previous page" hidden>
                            <x-public-icon name="chevron-left" />
                        </button>
                        <nav aria-label="Main navigation" data-navigation-window-scroll>
                            @foreach ($navigationItems as $navigationItem)
                                @php($submenuId = 'site-navigation-submenu-'.$navigationItem['tie_breaker'])
                                @php($selectedChildParent = $navigationItem['active'] && $navigationItem['children'] !== [])
                                <div class="site-navigation__item @if ($navigationItem['active']) is-active @endif" data-navigation-item data-navigation-key="{{ $navigationItem['tie_breaker'] }}:{{ $navigationItem['label'] }}">
                                    <div class="site-navigation__primary">
                                        @if ($navigationItem['url'] !== null)
                                            <a href="{{ $navigationItem['url'] }}"
                                                @if ($navigationItem['children'] === []) wire:navigate @endif
                                                @if ($navigationItem['current']) aria-current="page" @endif
                                                @if ($navigationItem['children'] !== [])
                                                    aria-expanded="{{ $selectedChildParent ? 'true' : 'false' }}"
                                                    aria-controls="{{ $submenuId }}" data-navigation-parent-link
                                                @endif
                                            ><span class="site-navigation__label">{{ $navigationItem['label'] }}</span></a>
                                        @else
                                            @if ($navigationItem['children'] !== [])
                                                <button type="button" aria-expanded="{{ $selectedChildParent ? 'true' : 'false' }}"
                                                    aria-controls="{{ $submenuId }}" data-navigation-parent-link
                                                ><span class="site-navigation__label">{{ $navigationItem['label'] }}</span></button>
                                            @else
                                                <span class="site-navigation__text"><span class="site-navigation__label">{{ $navigationItem['label'] }}</span></span>
                                            @endif
                                        @endif
                                    </div>
                                </div>
                            @endforeach
                        </nav>
                        <button class="site-navigation__control" type="button" data-direction="next" aria-label="Next page" hidden>
                            <x-public-icon name="chevron-right" />
                        </button>
                    </div>
                    @foreach ($navigationItems as $navigationItem)
                        @if ($navigationItem['children'] !== [])
                            @php($submenuId = 'site-navigation-submenu-'.$navigationItem['tie_breaker'])
                            @php($selectedChildParent = $navigationItem['active'])
                            <div id="{{ $submenuId }}" class="site-navigation__submenu" data-navigation-submenu
                                @unless ($selectedChildParent) hidden @endunless>
                                <button class="site-navigation__control" type="button" data-direction="previous" aria-label="Previous subpage" hidden>
                                    <x-public-icon name="chevron-left" />
                                </button>
                                <div class="site-navigation__content" data-navigation-content>
                                    <button class="site-navigation__back" type="button" data-navigation-back aria-label="Return to main navigation from {{ $navigationItem['label'] }}" title="Return to main navigation">
                                        <x-public-icon name="corner-up-left" />
                                        <span class="site-navigation__back-label">{{ $navigationItem['label'] }}</span>
                                    </button>
                                    <span class="site-navigation__breadcrumb-separator" aria-hidden="true"></span>
                                    <nav aria-label="{{ $navigationItem['label'] }} subnavigation" data-navigation-window-scroll>
                                        @if ($navigationItem['url'] !== null)
                                            <div class="site-navigation__item @if ($navigationItem['current']) is-active @endif" data-navigation-item data-navigation-key="{{ $navigationItem['tie_breaker'] }}:parent">
                                                <div class="site-navigation__primary">
                                                    <a href="{{ $navigationItem['url'] }}" wire:navigate @if ($navigationItem['current']) aria-current="page" @endif>
                                                        <span class="site-navigation__label">{{ $navigationItem['label'] }}</span>
                                                    </a>
                                                </div>
                                            </div>
                                        @endif
                                        @foreach ($navigationItem['children'] as $child)
                                            <div class="site-navigation__item @if ($child['current']) is-active @endif" data-navigation-item data-navigation-key="{{ $loop->index }}:{{ $child['url'] ?? $child['label'] }}">
                                                <div class="site-navigation__primary">
                                                    @if ($child['url'] !== null)
                                                        <a href="{{ $child['url'] }}" wire:navigate @if ($child['current']) aria-current="page" @endif><span class="site-navigation__label">{{ $child['label'] }}</span></a>
                                                    @else
                                                        <span class="site-navigation__text"><span class="site-navigation__label">{{ $child['label'] }}</span></span>
                                                    @endif
                                                </div>
                                            </div>
                                        @endforeach
                                    </nav>
                                </div>
                                <button class="site-navigation__control" type="button" data-direction="next" aria-label="Next subpage" hidden>
                                    <x-public-icon name="chevron-right" />
                                </button>
                            </div>
                        @endif
                    @endforeach
                </div>
            @endunless
        </header>
        <main id="content" class="site-content @if ($publicPageTransparent) site-content--transparent @endif @yield('content_class')">
            {{-- One content owner; the inner flow keeps its configured reading width. --}}
            <div class="public-content-reveal">
                @yield('content')
            </div>
        </main>
        </div>
        <x-artwork-viewer />
        @livewireScripts(['nonce' => $publicStyleNonce, 'url' => route('public.livewire.csp')])
    </body>
</html>
