@props(['artwork', 'showDescription' => true])

<span class="artwork-label">
    <span class="artwork-label__heading">
        <span class="artwork-label__title">{{ trim((string) $artwork->title) }}</span>
        @if ($artwork->work_year !== null)
            <span class="artwork-label__year">{{ $artwork->work_year }}</span>
        @endif
    </span>

    @if ($artwork->medium || $artwork->dimensions)
        <span class="artwork-label__facts">
            @if ($artwork->medium)
                <span>{{ $artwork->medium }}</span>
            @endif
            @if ($artwork->dimensions)
                <span class="artwork-label__dimensions">{{ $artwork->dimensions }}</span>
            @endif
        </span>
    @endif

    @if ($showDescription && $artwork->description)
        <span class="artwork-label__note">{{ $artwork->description }}</span>
    @endif
</span>
