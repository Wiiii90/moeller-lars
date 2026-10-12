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
                <div class="site-navigation" data-site-navigation>
                    <div class="site-navigation__rail" data-navigation-main>
                        <button class="site-navigation__control" type="button" data-direction="previous" aria-label="Previous page" hidden>
                            <x-public-icon name="chevron-left" />
                        </button>
                        <nav aria-label="Main navigation" data-navigation-window-scroll>
                            @foreach ($navigationItems as $navigationItem)
                                @php($submenuId = 'site-navigation-submenu-'.$navigationItem['tie_breaker'])
                                <div class="site-navigation__item @if ($navigationItem['active']) is-active @endif" data-navigation-item>
                                    <div class="site-navigation__primary">
                                        @if ($navigationItem['url'] !== null)
                                            <a href="{{ $navigationItem['url'] }}"
                                                @if ($navigationItem['current']) aria-current="page" @endif
                                                @if ($navigationItem['children'] !== [])
                                                    aria-haspopup="true" aria-expanded="false"
                                                    aria-controls="{{ $submenuId }}" data-navigation-parent-link
                                                @endif
                                            >{{ $navigationItem['label'] }}</a>
                                        @else
                                            @if ($navigationItem['children'] !== [])
                                                <button type="button" aria-haspopup="true" aria-expanded="false"
                                                    aria-controls="{{ $submenuId }}" data-navigation-parent-link
                                                >{{ $navigationItem['label'] }}</button>
                                            @else
                                                <span class="site-navigation__text">{{ $navigationItem['label'] }}</span>
                                            @endif
                                        @endif
                                    </div>
                                    @if ($navigationItem['children'] !== [])
                                        <div id="{{ $submenuId }}" class="site-navigation__submenu" data-navigation-submenu hidden>
                                            <button class="site-navigation__control" type="button" data-direction="previous" aria-label="Previous subpage" hidden>
                                                <x-public-icon name="chevron-left" />
                                            </button>
                                            <nav aria-label="{{ $navigationItem['label'] }} subnavigation" data-navigation-window-scroll>
                                                @foreach ($navigationItem['children'] as $child)
                                                    <div class="site-navigation__item @if ($child['current']) is-active @endif" data-navigation-item>
                                                        <div class="site-navigation__primary">
                                                            @if ($child['url'] !== null)
                                                                <a href="{{ $child['url'] }}" @if ($child['current']) aria-current="page" @endif>{{ $child['label'] }}</a>
                                                            @else
                                                                <span class="site-navigation__text">{{ $child['label'] }}</span>
                                                            @endif
                                                        </div>
                                                    </div>
                                                @endforeach
                                            </nav>
                                            <button class="site-navigation__control" type="button" data-direction="next" aria-label="Next subpage" hidden>
                                                <x-public-icon name="chevron-right" />
                                            </button>
                                        </div>
                                    @endif
                                </div>
                            @endforeach
                        </nav>
                        <button class="site-navigation__control" type="button" data-direction="next" aria-label="Next page" hidden>
                            <x-public-icon name="chevron-right" />
                        </button>
                    </div>
                    <div class="site-navigation__submenu-region" data-navigation-submenu-region>
                        <div class="site-navigation__submenu-region-inner" data-navigation-submenu-region-inner></div>
                    </div>
                </div>
            @endunless
        </header>
        <div class="site-scroll-region" data-site-scroll-region>
            <main id="content" class="site-content">
                @yield('content')
            </main>
        </div>
        <x-artwork-viewer />
    </body>
</html>
