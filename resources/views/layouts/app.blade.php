<!doctype html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="csrf-token" content="{{ csrf_token() }}">
        <title>@yield('title')</title>
        <meta name="description" content="{{ trim($__env->yieldContent('meta_description', 'Lars Möller — artist')) }}">
        <link rel="canonical" href="{{ trim($__env->yieldContent('canonical', app(\App\Domain\Content\CanonicalUrl::class)->current())) }}">
        @php($matomoTracking = app(\App\Domain\Analytics\MatomoConfiguration::class)->browserTracking())
        @if ($matomoTracking !== null)
            <meta
                data-matomo-tracking
                data-matomo-base-url="{{ $matomoTracking['base_url'] }}"
                data-matomo-site-id="{{ $matomoTracking['site_id'] }}"
            >
        @endif
        @vite(['resources/css/app.css', 'resources/css/public-content.css', 'resources/js/app.js'])
        <link rel="stylesheet" href="{{ asset('css/legacy-public.css') }}">
    </head>
    <body>
        <header class="site-header">
            <h1><a href="{{ route('home') }}" class="site-title">Lars Möller</a></h1>
            <div class="site-navigation" data-site-navigation>
                <button
                    class="site-navigation__control"
                    type="button"
                    data-direction="previous"
                    aria-label="Previous navigation items"
                    hidden
                >‹</button>
                <nav aria-label="Main navigation" data-site-navigation-scroll>
                    @foreach ($navigationItems as $navigationItem)
                        @php($submenuId = 'site-navigation-submenu-'.$navigationItem['tie_breaker'])
                        <div class="site-navigation__item @if ($navigationItem['active']) is-active @endif" data-navigation-item>
                            <div class="site-navigation__primary">
                                <a href="{{ $navigationItem['url'] }}" @if ($navigationItem['current']) aria-current="page" @endif>{{ $navigationItem['label'] }}</a>
                                @if ($navigationItem['children'] !== [])
                                    <button
                                        class="site-navigation__submenu-toggle"
                                        type="button"
                                        aria-label="Open {{ $navigationItem['label'] }} categories"
                                        aria-expanded="false"
                                        aria-controls="{{ $submenuId }}"
                                        data-navigation-submenu-toggle
                                    ><span aria-hidden="true">⌄</span></button>
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
                                        <a href="{{ $child['url'] }}" @if ($child['current']) aria-current="page" @endif>{{ $child['label'] }}</a>
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
                    aria-label="Next navigation items"
                    hidden
                >›</button>
            </div>
        </header>
        <main id="content" class="site-content">
            @yield('content')
        </main>
        <x-artwork-viewer />
    </body>
</html>
