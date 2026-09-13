@props([
    'capacity',
    'breakdown' => [],
    'interactive' => false,
    'compact' => false,
])

@php
    $capacityPercent = ($capacity['percent'] ?? null) !== null
        ? min(100, max(0, (float) $capacity['percent']))
        : null;
    $measurementAvailable = (bool) ($capacity['measurement_available'] ?? false);
    $configured = (bool) ($capacity['configured'] ?? false);
    $allowance = (string) ($capacity['allowance'] ?? '—');
    $authoritative = (string) ($capacity['authoritative'] ?? '—');
    $remaining = (string) ($capacity['remaining'] ?? '—');
    $rows = array_values(array_filter(
        is_array($breakdown) ? $breakdown : [],
        static fn (mixed $row): bool => is_array($row) && (int) ($row['bytes'] ?? 0) > 0,
    ));
    $totalBreakdownBytes = array_sum(array_map(
        static fn (array $row): int => (int) ($row['bytes'] ?? 0),
        $rows,
    ));
    $usedAngle = $capacityPercent !== null ? ($capacityPercent / 100) * 360 : 0.0;
    $sliceAngleCursor = 0.0;

    $polarPoint = static function (float $angle, float $radius): array {
        $radians = deg2rad($angle - 90);

        return [
            60 + (cos($radians) * $radius),
            60 + (sin($radians) * $radius),
        ];
    };

    $piePath = static function (float $startAngle, float $endAngle) use ($polarPoint): string {
        $radius = 44.0;
        $endAngle = min($endAngle, $startAngle + 359.999);
        [$startX, $startY] = $polarPoint($startAngle, $radius);
        [$endX, $endY] = $polarPoint($endAngle, $radius);
        $largeArc = ($endAngle - $startAngle) > 180 ? 1 : 0;

        return sprintf(
            'M 60 60 L %.3f %.3f A %.1f %.1f 0 %d 1 %.3f %.3f Z',
            $startX,
            $startY,
            $radius,
            $radius,
            $largeArc,
            $endX,
            $endY,
        );
    };

    $ariaLabel = match (true) {
        $configured && $measurementAvailable && $capacityPercent !== null => number_format($capacityPercent, 1).' percent used of '.$allowance.' storage allowance',
        $measurementAvailable => $authoritative.' authoritative storage measured; no allowance configured',
        default => 'Storage measurement unavailable',
    };
@endphp

<div {{ $attributes->class(['admin-storage-capacity', 'is-compact' => $compact]) }}>
    <svg
        class="admin-storage-capacity__pie"
        viewBox="0 0 120 120"
        role="img"
        aria-label="{{ $ariaLabel }}"
        data-storage-capacity-percent="{{ $capacityPercent ?? '' }}"
    >
        <circle class="admin-storage-capacity__remaining" cx="60" cy="60" r="44" />

        @if ($configured && $measurementAvailable && $capacityPercent !== null && $usedAngle > 0)
            @if ($rows !== [] && $totalBreakdownBytes > 0)
                @foreach ($rows as $row)
                    @php
                        $sliceBytes = max(0, (int) ($row['bytes'] ?? 0));
                        $sliceAngle = ($sliceBytes / $totalBreakdownBytes) * $usedAngle;
                        $sliceStart = $sliceAngleCursor;
                        $sliceEnd = $sliceAngleCursor + $sliceAngle;
                        $sliceMidpoint = $sliceStart + ($sliceAngle / 2);
                        $sliceRadians = deg2rad($sliceMidpoint - 90);
                        $sliceX = round(cos($sliceRadians) * 5.5, 2);
                        $sliceY = round(sin($sliceRadians) * 5.5, 2);
                        $sliceKey = (string) ($row['key'] ?? 'referenced');
                        $sliceClass = preg_replace('/[^a-z0-9-]+/', '-', strtolower($sliceKey)) ?: 'referenced';
                        $sliceCapacityPercent = $capacityPercent * ($sliceBytes / $totalBreakdownBytes);
                        $slicePath = $piePath($sliceStart, $sliceEnd);
                    @endphp
                    <path
                        class="admin-storage-capacity__segment admin-storage-capacity__segment--{{ $sliceClass }}"
                        d="{{ $slicePath }}"
                        style="--storage-slice-x: {{ $sliceX }}px; --storage-slice-y: {{ $sliceY }}px"
                        @if ($interactive)
                            role="button"
                            tabindex="0"
                            x-bind:aria-pressed="(selected === @js($sliceKey)).toString()"
                            x-bind:class="{
                                'is-selected': selected === @js($sliceKey),
                                'is-muted': selected !== null && selected !== @js($sliceKey),
                            }"
                            x-on:click="select(@js($sliceKey))"
                            x-on:keydown.enter.prevent="select(@js($sliceKey))"
                            x-on:keydown.space.prevent="select(@js($sliceKey))"
                        @endif
                        aria-label="{{ $row['label'] ?? ucfirst($sliceKey) }}: {{ $row['display_bytes'] ?? '' }}, {{ number_format($sliceCapacityPercent, $sliceCapacityPercent < 0.1 ? 2 : 1) }} percent of storage allowance"
                    >
                        <title>{{ $row['label'] ?? ucfirst($sliceKey) }} — {{ $row['display_bytes'] ?? '' }} · {{ number_format($sliceCapacityPercent, $sliceCapacityPercent < 0.1 ? 2 : 1) }}% of allowance</title>
                    </path>
                    @php $sliceAngleCursor += $sliceAngle; @endphp
                @endforeach
            @else
                <path
                    class="admin-storage-capacity__segment admin-storage-capacity__segment--used"
                    d="{{ $piePath(0, $usedAngle) }}"
                >
                    <title>{{ $authoritative }} used · {{ number_format($capacityPercent, 1) }}% of allowance</title>
                </path>
            @endif
        @endif

        <circle class="admin-storage-capacity__outline" cx="60" cy="60" r="44" />

        @foreach ([0, 90, 180, 270] as $tickAngle)
            @php
                [$tickInnerX, $tickInnerY] = $polarPoint($tickAngle, 47.5);
                [$tickOuterX, $tickOuterY] = $polarPoint($tickAngle, 51.5);
            @endphp
            <line
                class="admin-storage-capacity__tick"
                x1="{{ number_format($tickInnerX, 3, '.', '') }}"
                y1="{{ number_format($tickInnerY, 3, '.', '') }}"
                x2="{{ number_format($tickOuterX, 3, '.', '') }}"
                y2="{{ number_format($tickOuterY, 3, '.', '') }}"
                aria-hidden="true"
            />
        @endforeach
    </svg>

    <div class="admin-storage-capacity__readout">
        @if ($configured && $measurementAvailable && $capacityPercent !== null)
            <strong>{{ $authoritative }}</strong>
            <span>of {{ $allowance }} used</span>
            <small>{{ $remaining }} free</small>
        @elseif ($measurementAvailable)
            <strong>{{ $authoritative }}</strong>
            <span>authoritative</span>
            <small>No allowance configured</small>
        @else
            <strong>—</strong>
            <span>Measurement unavailable</span>
        @endif
    </div>
</div>
