@props([
    'tone' => 'neutral',
])

<span {{ $attributes->class([
    'admin-status',
    'is-success' => $tone === 'success',
    'is-warning' => $tone === 'warning',
    'is-danger' => $tone === 'danger',
    'is-neutral' => $tone === 'neutral',
]) }}>
    {{ $slot }}
</span>
