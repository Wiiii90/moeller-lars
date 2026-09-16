@props([
    'points' => [],
    'animateArrival' => false,
    'ariaLabel' => 'World visitor map',
])

<div {{ $attributes->class(['analytics-world']) }} aria-label="{{ $ariaLabel }}">
    <div class="analytics-world__canvas">
        @if (view()->exists('filament.generated.analytics-world-map'))
            @include('filament.generated.analytics-world-map')
        @else
            <div class="analytics-map-build-warning" role="status">
                Map geometry unavailable in this build.
            </div>
        @endif

        @if ($points !== [])
            <svg
                class="analytics-world__marker-layer"
                viewBox="0 0 1200 600"
                preserveAspectRatio="xMidYMid meet"
                role="group"
                aria-label="Country visit markers"
            >
                @foreach ($points as $point)
                    @if ($animateArrival)
                        <circle
                            class="analytics-world__marker-pulse"
                            cx="{{ number_format($point['x'], 2, '.', '') }}"
                            cy="{{ number_format($point['y'], 2, '.', '') }}"
                            r="{{ number_format($point['radius'], 2, '.', '') }}"
                            style="--marker-delay: {{ min($loop->index, 8) * 45 }}ms;"
                            aria-hidden="true"
                        />
                    @endif

                    <circle
                        class="analytics-world__marker"
                        cx="{{ number_format($point['x'], 2, '.', '') }}"
                        cy="{{ number_format($point['y'], 2, '.', '') }}"
                        r="{{ number_format($point['radius'], 2, '.', '') }}"
                        tabindex="0"
                        role="button"
                        x-on:click="selectCountry(@js($point['label']))"
                        x-on:keydown.enter.prevent="selectCountry(@js($point['label']))"
                        x-on:keydown.space.prevent="selectCountry(@js($point['label']))"
                        x-bind:class="selectedCountry === @js($point['label']) ? 'is-selected' : ''"
                        x-bind:aria-pressed="(selectedCountry === @js($point['label'])).toString()"
                        aria-label="{{ $point['label'] }}: {{ number_format($point['visits']) }} visits"
                    >
                        <title>{{ $point['label'] }} · {{ number_format($point['visits']) }} visits</title>
                    </circle>
                @endforeach
            </svg>
        @endif
    </div>
</div>
