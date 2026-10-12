@props(['artwork', 'showDescription' => true])

<span class="artwork-label">
    <span class="artwork-label__row artwork-label__row--primary @if ($artwork->work_year === null) artwork-label__row--single @endif">
        <span class="artwork-label__field artwork-label__field--title">
            <span class="artwork-label__key">Title</span>
            <span class="artwork-label__value artwork-label__value--title">{{ trim((string) $artwork->title) }}</span>
        </span>
        @if ($artwork->work_year !== null)
            <span class="artwork-label__field artwork-label__field--year">
                <span class="artwork-label__key">Year</span>
                <span class="artwork-label__value artwork-label__value--year">{{ $artwork->work_year }}</span>
            </span>
        @endif
    </span>

    @if ($artwork->medium || $artwork->dimensions)
        <span class="artwork-label__row artwork-label__row--technical @unless ($artwork->medium && $artwork->dimensions) artwork-label__row--single @endunless">
            @if ($artwork->medium)
                <span class="artwork-label__field artwork-label__field--medium">
                    <span class="artwork-label__key">Medium</span>
                    <span class="artwork-label__value">{{ $artwork->medium }}</span>
                </span>
            @endif
            @if ($artwork->dimensions)
                <span class="artwork-label__field artwork-label__field--dimensions">
                    <span class="artwork-label__key">Size</span>
                    <span class="artwork-label__value artwork-label__value--dimensions">{{ $artwork->dimensions }}</span>
                </span>
            @endif
        </span>
    @endif

    @if ($showDescription && $artwork->description)
        <span class="artwork-label__row artwork-label__row--note">
            <span class="artwork-label__field artwork-label__field--note">
                <span class="artwork-label__key">Note</span>
                <span class="artwork-label__value">{{ $artwork->description }}</span>
            </span>
        </span>
    @endif
</span>
