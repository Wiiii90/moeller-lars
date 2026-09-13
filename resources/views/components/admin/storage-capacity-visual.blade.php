@props([
    'capacity',
    'breakdown' => [],
    'linked' => false,
    'compact' => false,
])

@php
    $capacityPercent = ($capacity['percent'] ?? null) !== null
        ? min(100, max(0, (float) $capacity['percent']))
        : null;
    $measurementAvailable = (bool) ($capacity['measurement_available'] ?? false);
    $configured = (bool) ($capacity['configured'] ?? false);
    $rows = array_values(array_filter(
        is_array($breakdown) ? $breakdown : [],
        static fn (mixed $row): bool => is_array($row) && (int) ($row['bytes'] ?? 0) > 0,
    ));
    $vizConfig = [
        'kind' => 'storage-capacity',
        'configured' => $configured,
        'measurement_available' => $measurementAvailable,
        'percent' => $capacityPercent,
        'allowance' => (string) ($capacity['allowance'] ?? '—'),
        'authoritative' => (string) ($capacity['authoritative'] ?? '—'),
        'remaining' => (string) ($capacity['remaining'] ?? '—'),
        'breakdown' => array_map(
            static fn (array $row): array => [
                'key' => (string) ($row['key'] ?? 'referenced'),
                'label' => (string) ($row['label'] ?? 'Referenced'),
                'bytes' => (int) ($row['bytes'] ?? 0),
                'display_bytes' => (string) ($row['display_bytes'] ?? '—'),
                'files' => (int) ($row['files'] ?? 0),
                'percent' => (float) ($row['percent'] ?? 0),
            ],
            $rows,
        ),
    ];
@endphp

<div
    {{ $attributes->class(['admin-storage-capacity', 'is-compact' => $compact]) }}
    data-admin-viz="storage-capacity"
    data-admin-viz-linked="{{ $linked ? 'true' : 'false' }}"
    role="img"
    aria-label="@if ($configured && $measurementAvailable && $capacityPercent !== null) {{ number_format($capacityPercent, 1) }} percent of {{ $capacity['allowance'] ?? 'the storage allowance' }} is used @elseif ($measurementAvailable) Authoritative storage measured without a configured allowance @else Storage measurement unavailable @endif"
>
    <div class="admin-storage-capacity__surface" data-admin-viz-surface wire:ignore></div>
    <script type="application/json" data-admin-viz-config>@json($vizConfig)</script>
</div>
