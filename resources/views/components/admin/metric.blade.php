@props([
    'label',
    'value',
    'description' => null,
])

<div {{ $attributes->class(['admin-metric']) }}>
    <span class="admin-metric__label">{{ $label }}</span>
    <strong class="admin-metric__value">{{ $value }}</strong>
    @if ($description !== null)
        <div class="admin-metric__detail">{{ $description }}</div>
    @elseif (trim((string) $slot) !== '')
        <div class="admin-metric__detail">{{ $slot }}</div>
    @endif
</div>
