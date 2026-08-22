@props([
    'kicker',
    'title',
])

<div {{ $attributes->class(['artist-workspace']) }}>
    <header class="artist-workspace__head">
        <div>
            <p class="artist-workspace__kicker">{{ $kicker }}</p>
            <h2>{{ $title }}</h2>
        </div>

        @isset($summary)
            <div class="artist-workspace__summary">
                {{ $summary }}
            </div>
        @endisset

        @isset($actions)
            {{ $actions }}
        @endisset
    </header>

    {{ $slot }}
</div>
