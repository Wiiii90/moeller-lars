@props([
    'type' => 'button',
])

<button type="{{ $type }}" {{ $attributes->class(['admin-add-row']) }}>
    <span class="admin-add-row__mark" aria-hidden="true">+</span>
    <span>{{ $slot }}</span>
</button>
