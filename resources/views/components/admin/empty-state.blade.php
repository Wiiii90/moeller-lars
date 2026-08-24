@props([
    'kicker' => 'Empty',
    'title',
    'minimal' => false,
])

@php($isMinimal = $minimal || $title === 'No matching files')

<div {{ $attributes->class(['admin-empty-state']) }}>
    @if (! $isMinimal && filled($kicker))
        <p class="admin-empty-state__kicker">{{ $kicker }}</p>
    @endif
    <h3 class="admin-empty-state__title">{{ $title }}</h3>
    @if (! $isMinimal)
        <div class="admin-empty-state__body">{{ $slot }}</div>
    @endif
    @isset($actions)
        <div class="admin-toolbar admin-empty-state__actions">{{ $actions }}</div>
    @endisset
</div>
