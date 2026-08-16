<!doctype html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="csrf-token" content="{{ csrf_token() }}">
        <title>@yield('title')</title>
        @vite(['resources/css/app.css', 'resources/js/app.js'])
    </head>
    <body>
        <header class="site-header">
            <h1><a href="{{ route('home') }}" class="site-title">Lars Möller</a></h1>
            <nav aria-label="Main navigation">
                @foreach ($navigationItems as $navigationItem)
                    <a href="{{ $navigationItem['url'] }}" @if ($navigationItem['current']) aria-current="page" @endif>{{ $navigationItem['label'] }}</a>
                @endforeach
            </nav>
        </header>
        <main id="content" class="site-content">
            @yield('content')
        </main>
        <x-artwork-viewer />
    </body>
</html>
