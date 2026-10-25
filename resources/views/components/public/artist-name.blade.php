@props(['homeUrl', 'title'])

<h1 class="site-name-heading">
    <a href="{{ $homeUrl }}" class="site-title" aria-label="{{ $title }} — Home" wire:navigate>{{ $title }}</a>
</h1>
