{{-- Shared native percentage editor for inline Livewire table/Stage controls.
     Owns input constraints and unit, not persistence or domain validation. --}}
@props([
    'value',
    'label',
])

<label class="admin-inline-percentage">
    <input
        type="number"
        min="0"
        max="100"
        step="0.01"
        inputmode="decimal"
        value="{{ $value }}"
        aria-label="{{ $label }}"
        {{ $attributes->class(['admin-inline-input', 'admin-inline-percentage__input']) }}
    >
    <span aria-hidden="true">%</span>
</label>
