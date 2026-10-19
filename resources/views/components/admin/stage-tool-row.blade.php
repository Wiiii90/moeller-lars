@props(['label' => 'Stage tools'])

<div {{ $attributes->class(['admin-stage-tool-row']) }} role="toolbar" aria-label="{{ $label }}">
    {{ $slot }}
</div>
