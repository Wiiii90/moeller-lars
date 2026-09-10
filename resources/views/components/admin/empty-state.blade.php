@props([
    'kicker' => null,
    'title',
    'minimal' => false,
])

@php($hasBody = trim((string) $slot) !== '')

<div {{ $attributes->class(['admin-empty-state']) }}>
    @if (! $minimal && filled($kicker))
        <p class="admin-empty-state__kicker">{{ $kicker }}</p>
    @endif
    <h3 class="admin-empty-state__title">{{ $title }}</h3>
    @if (! $minimal && $hasBody)
        <div class="admin-empty-state__body">{{ $slot }}</div>
    @endif
    @isset($actions)
        <div class="admin-toolbar admin-empty-state__actions">{{ $actions }}</div>
    @endisset
</div>
