@props(['artwork', 'showDescription' => true])

@php
    $title = trim((string) $artwork->title);
    $titleMain = $title;
    $titleQualifier = null;

    if (preg_match('/^(.*?)(?:\s+)(\([^()]+\))$/u', $title, $matches) === 1) {
        $titleMain = trim($matches[1]);
        $titleQualifier = $matches[2];
    }
@endphp

<span class="artwork-label">
    <span class="artwork-label__heading">
        <span class="artwork-label__title">
            <span class="artwork-label__title-main">{{ $titleMain }}</span>@if ($titleQualifier !== null)<span class="artwork-label__title-qualifier">&nbsp;{{ $titleQualifier }}</span>@endif
        </span>
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
