@props(['artwork', 'showDescription' => true])

<span class="artwork-label">
    <span class="artwork-label__field artwork-label__field--title">
        <span class="artwork-label__prompt">Title</span>
        <span class="artwork-label__writing"><span class="artwork-label__entry">{{ trim((string) $artwork->title) }}</span></span>
    </span>

    @if ($artwork->medium)
        <span class="artwork-label__field artwork-label__field--medium">
            <span class="artwork-label__prompt">Medium</span>
            <span class="artwork-label__writing"><span class="artwork-label__entry">{{ $artwork->medium }}</span></span>
        </span>
    @endif

    @if ($artwork->work_year !== null || $artwork->dimensions)
        <span class="artwork-label__tail @unless ($artwork->work_year !== null && $artwork->dimensions) artwork-label__tail--single @endunless">
            @if ($artwork->work_year !== null)
                <span class="artwork-label__field artwork-label__field--year">
                    <span class="artwork-label__prompt">Year</span>
                    <span class="artwork-label__writing"><span class="artwork-label__entry artwork-label__entry--numeric">{{ $artwork->work_year }}</span></span>
                </span>
            @endif
            @if ($artwork->dimensions)
                <span class="artwork-label__field artwork-label__field--size">
                    <span class="artwork-label__prompt">Size</span>
                    <span class="artwork-label__writing"><span class="artwork-label__entry artwork-label__entry--numeric">{{ $artwork->dimensions }}</span></span>
                </span>
            @endif
        </span>
    @endif

    @if ($showDescription && $artwork->description)
        <span class="artwork-label__field artwork-label__field--note">
            <span class="artwork-label__prompt">Note</span>
            <span class="artwork-label__writing"><span class="artwork-label__entry">{{ $artwork->description }}</span></span>
        </span>
    @endif
</span>
