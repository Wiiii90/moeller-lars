<!doctype html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="public-site-root">
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
            <link rel="icon" href="{{ route('media.variant', $faviconVariant) }}" type="{{ $faviconVariant->mime_type }}">
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
            'resources/js/app.js',
        ])
    </head>
    <body class="public-site">
        @if ($isPreview)
            <div
                role="status"
                aria-label="Artist preview mode"
                style="position:fixed;right:.75rem;bottom:.75rem;z-index:9999;padding:.45rem .65rem;border:1px solid currentColor;background:#fff;color:#3d3935;font:600 11px/1 Arial,sans-serif;letter-spacing:.12em;"
            >PREVIEW</div>
        @endif
        <header class="site-header">
            <h1>
                <a href="{{ $homeUrl }}" class="site-title" aria-label="Lars Möller — Home">
                    <span class="site-title__first">Lars</span><span class="site-title__last">Möller</span>
                </a>
            </h1>
            <div class="site-navigation" data-site-navigation>
                <button
                    class="site-navigation__control"
                    type="button"
                    data-direction="previous"
                    aria-label="Previous navigation item"
                    hidden
                >‹</button>
                <nav aria-label="Main navigation" data-site-navigation-scroll>
                    @foreach ($navigationItems as $navigationItem)
                        @php($submenuId = 'site-navigation-submenu-'.$navigationItem['tie_breaker'])
                        <div class="site-navigation__item @if ($navigationItem['active']) is-active @endif" data-navigation-item>
                            <div class="site-navigation__primary">
                                @if ($navigationItem['url'] !== null)
                                    <a
                                        href="{{ $navigationItem['url'] }}"
                                        @if ($navigationItem['current']) aria-current="page" @endif
                                        @if ($navigationItem['children'] !== [])
                                            aria-haspopup="true"
                                            aria-expanded="false"
                                            aria-controls="{{ $submenuId }}"
                                            data-navigation-parent-link
                                        @endif
                                    >{{ $navigationItem['label'] }}</a>
                                @else
                                    <a
                                        role="button"
                                        tabindex="0"
                                        @if ($navigationItem['children'] !== [])
                                            aria-haspopup="true"
                                            aria-expanded="false"
                                            aria-controls="{{ $submenuId }}"
                                            data-navigation-parent-link
                                        @endif
                                    >{{ $navigationItem['label'] }}</a>
                                @endif
                            </div>
                            @if ($navigationItem['children'] !== [])
                                <div
                                    id="{{ $submenuId }}"
                                    class="site-navigation__submenu"
                                    data-navigation-submenu
                                    hidden
                                >
                                    @foreach ($navigationItem['children'] as $child)
                                        @if ($child['url'] !== null)
                                            <a href="{{ $child['url'] }}"@if ($child['current']) aria-current="page"@endif>{{ $child['label'] }}</a>
                                        @else
                                            <span>{{ $child['label'] }}</span>
                                        @endif
                                    @endforeach
                                </div>
                            @endif
                        </div>
                    @endforeach
                </nav>
                <button
                    class="site-navigation__control"
                    type="button"
                    data-direction="next"
                    aria-label="Next navigation item"
                    hidden
                >›</button>
                <div class="site-navigation__submenu-region" data-navigation-submenu-region>
                    <div class="site-navigation__submenu-region-inner" data-navigation-submenu-region-inner></div>
                </div>
            </div>
        </header>
        <div class="site-scroll-region" data-site-scroll-region>
            <main id="content" class="site-content">
                @yield('content')
            </main>
        </div>
        <x-artwork-viewer />
    </body>
</html>
