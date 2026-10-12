<!doctype html>
@php($publicStyleNonce = request()->attributes->get(\App\Http\Middleware\SecurityHeaders::STYLE_NONCE_ATTRIBUTE))
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
        @vite([
            'resources/css/app.css',
            'resources/css/public-content.css',
            'resources/css/public-presentation.css',
            'resources/css/custom-pages.css',
            'resources/js/app.js',
        ])
        @if (is_string($publicStyleNonce) && $publicStyleNonce !== '')
            <style nonce="{{ $publicStyleNonce }}">:root {@if ($publicBackgroundCss !== null) --public-page: {{ $publicBackgroundCss }};@endif --public-shell-max: {{ $publicLayoutCss['shell'] }}; --public-art-max: {{ $publicLayoutCss['art'] }}; --public-content-padding: {{ $publicLayoutCss['padding'] }}; }</style>
        @endif
    </head>
    <body class="public-site">
        @if ($isPreview)
            <div
                class="public-preview-badge"
                role="status"
                aria-label="Artist preview mode"
            >PREVIEW</div>
        @endif
        <header class="site-header">
            <h1>
                <a href="{{ $homeUrl }}" class="site-title" aria-label="Lars Möller — Home">
                    <span class="site-title__first">Lars</span><span class="site-title__last">Möller</span>
                </a>
            </h1>
            @unless ($__env->hasSection('hide_navigation'))
                @php($selectedNavigationParent = collect($navigationItems)->first(fn ($item) => $item['active'] && $item['children'] !== []))
                <div class="site-navigation" data-site-navigation>
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
                                    <button class="site-navigation__back" type="button" data-navigation-back aria-label="Return to main navigation">MENU</button>
                                    <span class="site-navigation__breadcrumb-separator" aria-hidden="true">/</span>
                                    <nav aria-label="{{ $navigationItem['label'] }} subnavigation" data-navigation-window-scroll>
                                        @if ($navigationItem['url'] !== null)
                                            <div class="site-navigation__item @if ($navigationItem['current']) is-active @endif" data-navigation-item data-navigation-key="{{ $navigationItem['tie_breaker'] }}:parent">
                                                <div class="site-navigation__primary">
                                                    <a href="{{ $navigationItem['url'] }}" @if ($navigationItem['current']) aria-current="page" @endif>
                                                        <span class="site-navigation__label">{{ $navigationItem['label'] }}</span>
                                                    </a>
                                                </div>
                                            </div>
                                        @endif
                                        @foreach ($navigationItem['children'] as $child)
                                            <div class="site-navigation__item @if ($child['current']) is-active @endif" data-navigation-item data-navigation-key="{{ $loop->index }}:{{ $child['url'] ?? $child['label'] }}">
                                                <div class="site-navigation__primary">
                                                    @if ($child['url'] !== null)
                                                        <a href="{{ $child['url'] }}" @if ($child['current']) aria-current="page" @endif><span class="site-navigation__label">{{ $child['label'] }}</span></a>
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
        <main id="content" class="site-content">
            @yield('content')
        </main>
        <x-artwork-viewer />
    </body>
</html>
