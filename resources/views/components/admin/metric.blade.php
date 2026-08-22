@props([
    'label',
    'value',
])

<div {{ $attributes->class(['admin-metric']) }}>
    <span class="admin-metric__label">{{ $label }}</span>
    <strong class="admin-metric__value">{{ $value }}</strong>
    @if (trim((string) $slot) !== '')
        <div class="admin-metric__detail">{{ $slot }}</div>
    @endif
</div>
