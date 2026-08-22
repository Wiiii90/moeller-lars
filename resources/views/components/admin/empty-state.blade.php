@props([
    'kicker' => 'Empty',
    'title',
])

<div {{ $attributes->class(['admin-empty-state']) }}>
    <p class="admin-empty-state__kicker">{{ $kicker }}</p>
    <h3 class="admin-empty-state__title">{{ $title }}</h3>
    <div class="admin-empty-state__body">{{ $slot }}</div>
    @isset($actions)
        <div class="admin-toolbar admin-empty-state__actions">{{ $actions }}</div>
    @endisset
</div>
