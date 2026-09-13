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

    $overviewX = 18.0;
    $overviewY = 40.0;
    $overviewWidth = 284.0;
    $overviewHeight = 42.0;
    $detailX = 18.0;
    $detailY = 132.0;
    $detailWidth = 284.0;
    $detailHeight = 24.0;
    $usedWidth = $capacityPercent !== null ? ($overviewWidth * ($capacityPercent / 100)) : 0.0;
    $usedEndX = $overviewX + $usedWidth;
    $overviewCursor = $overviewX;
    $detailCursor = $detailX;
    $clipSuffix = $compact ? 'compact' : 'full';

    $formatPercent = static function (?float $value): string {
        if ($value === null) {
            return '—';
        }

        return number_format($value, $value < 0.1 ? 2 : ($value < 10 ? 1 : 0));
    };
@endphp

<div {{ $attributes->class(['admin-storage-capacity', 'is-compact' => $compact]) }}>
    <svg
        class="admin-storage-capacity__lens"
        viewBox="0 0 320 184"
        role="img"
        aria-label="@if ($configured && $measurementAvailable && $capacityPercent !== null) {{ $formatPercent($capacityPercent) }} percent used, {{ $remaining }} free, {{ $allowance }} total storage allowance @elseif ($measurementAvailable) {{ $authoritative }} authoritative storage measured with no configured allowance @else Storage measurement unavailable @endif"
        data-storage-capacity-percent="{{ $capacityPercent ?? '' }}"
    >
        <defs>
            <clipPath id="storage-capacity-overview-{{ $clipSuffix }}">
                <rect x="{{ $overviewX }}" y="{{ $overviewY }}" width="{{ $overviewWidth }}" height="{{ $overviewHeight }}" rx="8" />
            </clipPath>
            <clipPath id="storage-capacity-detail-{{ $clipSuffix }}">
                <rect x="{{ $detailX }}" y="{{ $detailY }}" width="{{ $detailWidth }}" height="{{ $detailHeight }}" rx="5" />
            </clipPath>
        </defs>

        @if ($configured && $measurementAvailable && $capacityPercent !== null)
            <text class="admin-storage-capacity__label admin-storage-capacity__label--used" x="{{ $overviewX }}" y="25">
                {{ $authoritative }} · {{ $formatPercent($capacityPercent) }}% used
            </text>
            <text class="admin-storage-capacity__label admin-storage-capacity__label--total" x="{{ $overviewX + $overviewWidth }}" y="25" text-anchor="end">
                {{ $allowance }} total
            </text>

            <rect
                class="admin-storage-capacity__overview-depth"
                x="{{ $overviewX + 4 }}"
                y="{{ $overviewY + 5 }}"
                width="{{ $overviewWidth }}"
                height="{{ $overviewHeight }}"
                rx="8"
            />
            <rect
                class="admin-storage-capacity__overview-free"
                x="{{ $overviewX }}"
                y="{{ $overviewY }}"
                width="{{ $overviewWidth }}"
                height="{{ $overviewHeight }}"
                rx="8"
            />

            <g clip-path="url(#storage-capacity-overview-{{ $clipSuffix }})">
                @if ($usedWidth > 0)
                    @if ($rows !== [] && $totalBreakdownBytes > 0)
                        @foreach ($rows as $row)
                            @php
                                $sliceBytes = max(0, (int) ($row['bytes'] ?? 0));
                                $shareOfUsed = $sliceBytes / $totalBreakdownBytes;
                                $sliceOverviewWidth = $usedWidth * $shareOfUsed;
                                $sliceDetailWidth = $detailWidth * $shareOfUsed;
                                $sliceKey = (string) ($row['key'] ?? 'referenced');
                                $sliceClass = preg_replace('/[^a-z0-9-]+/', '-', strtolower($sliceKey)) ?: 'referenced';
                                $capacityShare = $capacityPercent * $shareOfUsed;
                            @endphp
                            <rect
                                class="admin-storage-capacity__segment admin-storage-capacity__segment--{{ $sliceClass }}"
                                x="{{ number_format($overviewCursor, 4, '.', '') }}"
                                y="{{ $overviewY }}"
                                width="{{ number_format($sliceOverviewWidth, 4, '.', '') }}"
                                height="{{ $overviewHeight }}"
                                @if ($linked)
                                    x-bind:class="{
                                        'is-highlighted': selected === @js($sliceKey),
                                        'is-dimmed': selected !== null && selected !== @js($sliceKey),
                                    }"
                                @endif
                            >
                                <title>{{ $row['label'] ?? ucfirst($sliceKey) }} — {{ $row['display_bytes'] ?? '' }} · {{ $formatPercent($capacityShare) }}% of allowance</title>
                            </rect>
                            @php $overviewCursor += $sliceOverviewWidth; @endphp
                        @endforeach
                    @else
                        <rect
                            class="admin-storage-capacity__segment admin-storage-capacity__segment--used"
                            x="{{ $overviewX }}"
                            y="{{ $overviewY }}"
                            width="{{ number_format($usedWidth, 4, '.', '') }}"
                            height="{{ $overviewHeight }}"
                        />
                    @endif
                @endif
            </g>

            <rect
                class="admin-storage-capacity__overview-outline"
                x="{{ $overviewX }}"
                y="{{ $overviewY }}"
                width="{{ $overviewWidth }}"
                height="{{ $overviewHeight }}"
                rx="8"
            />

            @if ($remaining !== '—')
                <text
                    class="admin-storage-capacity__free-label"
                    x="{{ $overviewX + $overviewWidth - 10 }}"
                    y="{{ $overviewY + ($overviewHeight / 2) + 3 }}"
                    text-anchor="end"
                >{{ $remaining }} free</text>
            @endif

            @if ($usedWidth > 0)
                <line
                    class="admin-storage-capacity__zoom-line"
                    x1="{{ $overviewX }}"
                    y1="{{ $overviewY + $overviewHeight + 7 }}"
                    x2="{{ $detailX }}"
                    y2="{{ $detailY - 8 }}"
                />
                <line
                    class="admin-storage-capacity__zoom-line"
                    x1="{{ number_format($usedEndX, 4, '.', '') }}"
                    y1="{{ $overviewY + $overviewHeight + 7 }}"
                    x2="{{ $detailX + $detailWidth }}"
                    y2="{{ $detailY - 8 }}"
                />

                <text class="admin-storage-capacity__detail-label" x="{{ $detailX }}" y="{{ $detailY - 13 }}">
                    Used composition
                </text>

                <rect
                    class="admin-storage-capacity__detail-base"
                    x="{{ $detailX }}"
                    y="{{ $detailY }}"
                    width="{{ $detailWidth }}"
                    height="{{ $detailHeight }}"
                    rx="5"
                />

                <g clip-path="url(#storage-capacity-detail-{{ $clipSuffix }})">
                    @if ($rows !== [] && $totalBreakdownBytes > 0)
                        @foreach ($rows as $row)
                            @php
                                $sliceBytes = max(0, (int) ($row['bytes'] ?? 0));
                                $shareOfUsed = $sliceBytes / $totalBreakdownBytes;
                                $sliceDetailWidth = $detailWidth * $shareOfUsed;
                                $sliceKey = (string) ($row['key'] ?? 'referenced');
                                $sliceClass = preg_replace('/[^a-z0-9-]+/', '-', strtolower($sliceKey)) ?: 'referenced';
                            @endphp
                            <rect
                                class="admin-storage-capacity__segment admin-storage-capacity__segment--detail admin-storage-capacity__segment--{{ $sliceClass }}"
                                x="{{ number_format($detailCursor, 4, '.', '') }}"
                                y="{{ $detailY }}"
                                width="{{ number_format($sliceDetailWidth, 4, '.', '') }}"
                                height="{{ $detailHeight }}"
                                @if ($linked)
                                    x-bind:class="{
                                        'is-highlighted': selected === @js($sliceKey),
                                        'is-dimmed': selected !== null && selected !== @js($sliceKey),
                                    }"
                                @endif
                            >
                                <title>{{ $row['label'] ?? ucfirst($sliceKey) }} — {{ $row['display_bytes'] ?? '' }} · {{ $formatPercent($shareOfUsed * 100) }}% of used storage</title>
                            </rect>
                            @php $detailCursor += $sliceDetailWidth; @endphp
                        @endforeach
                    @else
                        <rect
                            class="admin-storage-capacity__segment admin-storage-capacity__segment--detail admin-storage-capacity__segment--used"
                            x="{{ $detailX }}"
                            y="{{ $detailY }}"
                            width="{{ $detailWidth }}"
                            height="{{ $detailHeight }}"
                        />
                    @endif
                </g>

                <rect
                    class="admin-storage-capacity__detail-outline"
                    x="{{ $detailX }}"
                    y="{{ $detailY }}"
                    width="{{ $detailWidth }}"
                    height="{{ $detailHeight }}"
                    rx="5"
                />
            @endif
        @elseif ($measurementAvailable)
            <rect class="admin-storage-capacity__overview-free" x="18" y="61" width="284" height="42" rx="8" />
            <rect class="admin-storage-capacity__overview-outline" x="18" y="61" width="284" height="42" rx="8" />
            <text class="admin-storage-capacity__label admin-storage-capacity__label--used" x="18" y="45">{{ $authoritative }} measured</text>
            <text class="admin-storage-capacity__free-label" x="160" y="86" text-anchor="middle">No allowance configured</text>
        @else
            <rect class="admin-storage-capacity__overview-free" x="18" y="61" width="284" height="42" rx="8" />
            <rect class="admin-storage-capacity__overview-outline" x="18" y="61" width="284" height="42" rx="8" />
            <text class="admin-storage-capacity__free-label" x="160" y="86" text-anchor="middle">No storage measurement</text>
        @endif
    </svg>
</div>
