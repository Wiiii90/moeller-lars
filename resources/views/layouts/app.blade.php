<!doctype html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="csrf-token" content="{{ csrf_token() }}">
        <title>@yield('title', config('app.name'))</title>
        @if (file_exists(public_path('build/manifest.json')) || file_exists(public_path('hot')))
            @vite(['resources/css/app.css', 'resources/js/app.js'])
        @endif
    </head>
    <body>
        <header class="site-header">
            <h1><a href="{{ route('home') }}" class="site-title">Lars Möller</a></h1>
            <nav aria-label="Main navigation">
                <a href="{{ route('artworks.category', ['category' => 'paintings']) }}" @if (request()->is('paintings')) aria-current="page" @endif>Paintings</a>
                <a href="{{ route('artworks.category', ['category' => 'prints']) }}" @if (request()->is('prints')) aria-current="page" @endif>Prints</a>
                <a href="{{ route('artworks.category', ['category' => 'drawings']) }}" @if (request()->is('drawings')) aria-current="page" @endif>Drawings</a>
                <a href="/cv">CV &amp; Exhibitions</a>
            </nav>
        </header>
        <main id="content" class="site-content">
            @yield('content')
        </main>
        <x-artwork-viewer />
    </body>
</html>
