@props([
    'activity' => [],
    'peakCount' => 0,
    'peakHour' => null,
    'selectedHour' => null,
    'hourUrls' => [],
    'captionLabel' => null,
    'ariaContext' => 'activity distribution',
])

<div
    {{ $attributes->class(['activity-clock']) }}
    x-data="{
        now: new Date(),
        timer: null,
        timeFormatter: new Intl.DateTimeFormat(undefined, {
            hour: '2-digit',
            minute: '2-digit',
            second: '2-digit',
        }),
        init() {
            this.now = new Date()
            if (this.timer !== null) window.clearInterval(this.timer)
            this.timer = window.setInterval(() => { this.now = new Date() }, 1000)
        },
        destroy() {
            if (this.timer !== null) {
                window.clearInterval(this.timer)
                this.timer = null
            }
        },
        hourAngle() {
            return ((this.now.getHours() % 12) + (this.now.getMinutes() / 60) + (this.now.getSeconds() / 3600)) * 30
        },
        minuteAngle() {
            return (this.now.getMinutes() + (this.now.getSeconds() / 60)) * 6
        },
        secondAngle() {
            return this.now.getSeconds() * 6
        },
        timeLabel() {
            return this.timeFormatter.format(this.now)
        },
    }"
>
    <div class="activity-clock__figure">
        <svg
            class="activity-clock__dial"
            viewBox="0 0 320 320"
            role="img"
            x-bind:aria-label="`Live local time ${timeLabel()}; {{ $ariaContext }}`"
        >
            <circle class="activity-clock__activity-track" cx="160" cy="160" r="134" />
            @foreach ($activity as $bucket)
                @php
                    $activityRatio = $peakCount > 0 ? sqrt($bucket['count'] / $peakCount) : 0;
                    $activityOpacity = $bucket['count'] > 0 ? 0.28 + (0.72 * $activityRatio) : 0.12;
                    $hourUrl = $hourUrls[$bucket['hour']] ?? null;
                    $tickClass = 'activity-clock__activity-tick'.($bucket['hour'] === $peakHour ? ' is-peak' : '').($bucket['hour'] === $selectedHour ? ' is-selected' : '');
                @endphp

                @if (is_string($hourUrl) && $hourUrl !== '')
                    <a
                        href="{{ $hourUrl }}"
                        aria-label="Filter {{ str_pad((string) $bucket['hour'], 2, '0', STR_PAD_LEFT) }}:00, {{ number_format($bucket['count']) }} changes"
                    >
                        <line
                            class="{{ $tickClass }}"
                            x1="160"
                            y1="33"
                            x2="160"
                            y2="19"
                            transform="rotate({{ $bucket['hour'] * 15 }} 160 160)"
                            opacity="{{ number_format($activityOpacity, 3, '.', '') }}"
                        >
                            <title>{{ str_pad((string) $bucket['hour'], 2, '0', STR_PAD_LEFT) }}:00 · {{ number_format($bucket['count']) }} changes</title>
                        </line>
                    </a>
                @else
                    <line
                        class="{{ $tickClass }}"
                        x1="160"
                        y1="33"
                        x2="160"
                        y2="19"
                        transform="rotate({{ $bucket['hour'] * 15 }} 160 160)"
                        opacity="{{ number_format($activityOpacity, 3, '.', '') }}"
                    >
                        <title>{{ str_pad((string) $bucket['hour'], 2, '0', STR_PAD_LEFT) }}:00 · {{ number_format($bucket['count']) }} changes</title>
                    </line>
                @endif
            @endforeach

            <circle class="activity-clock__face" cx="160" cy="160" r="112" />

            @for ($minute = 0; $minute < 60; $minute++)
                <line
                    class="activity-clock__tick {{ $minute % 5 === 0 ? 'is-hour' : '' }}"
                    x1="160"
                    y1="{{ $minute % 5 === 0 ? 59 : 54 }}"
                    x2="160"
                    y2="48"
                    transform="rotate({{ $minute * 6 }} 160 160)"
                />
            @endfor

            @foreach (range(1, 12) as $hour)
                <g transform="rotate({{ $hour * 30 }} 160 160)">
                    <text
                        class="activity-clock__number"
                        x="160"
                        y="74"
                        transform="rotate({{ $hour * -30 }} 160 74)"
                    >{{ $hour }}</text>
                </g>
            @endforeach

            <line
                class="activity-clock__hand activity-clock__hand--hour"
                x1="160"
                y1="170"
                x2="160"
                y2="98"
                x-bind:transform="`rotate(${hourAngle()} 160 160)`"
            />
            <line
                class="activity-clock__hand activity-clock__hand--minute"
                x1="160"
                y1="174"
                x2="160"
                y2="82"
                x-bind:transform="`rotate(${minuteAngle()} 160 160)`"
            />
            <line
                class="activity-clock__hand activity-clock__hand--second"
                x1="160"
                y1="178"
                x2="160"
                y2="74"
                x-bind:transform="`rotate(${secondAngle()} 160 160)`"
            />
            <circle class="activity-clock__pin" cx="160" cy="160" r="4.5" />
        </svg>
    </div>

    @if ($captionLabel !== null)
        <div class="activity-clock__caption" aria-label="{{ $captionLabel }} and live local time">
            <strong>{{ $captionLabel }}</strong>
            <span>Live local time · <time x-text="timeLabel()">—</time></span>
        </div>
    @endif
</div>
