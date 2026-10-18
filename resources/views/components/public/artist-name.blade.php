@props(['homeUrl'])

<h1 class="site-name-heading">
    <a href="{{ $homeUrl }}" class="site-title" aria-label="Lars Möller — Home" wire:navigate>
        <span class="site-title__first">Lars</span><span class="site-title__last">Möller</span>
    </a>
</h1>
