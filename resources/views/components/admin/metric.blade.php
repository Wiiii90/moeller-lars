@props([
    'label',
    'value',
    'description' => null,
])

<div {{ $attributes->class(['admin-metric']) }}>
    <span class="admin-metric__label">{{ is_string($label) ? __($label) : $label }}</span>
    <strong class="admin-metric__value">{{ $value }}</strong>
    @if ($description !== null)
        <div class="admin-metric__detail">{{ is_string($description) ? __($description) : $description }}</div>
    @elseif (trim((string) $slot) !== '')
        <div class="admin-metric__detail">{{ $slot }}</div>
    @endif
</div>
