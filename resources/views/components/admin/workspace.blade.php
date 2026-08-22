@props([
    'kicker' => null,
    'title',
])

<div {{ $attributes->class(['admin-workspace']) }}>
    <header class="admin-workspace__header">
        <div class="admin-workspace__heading">
            @if (filled($kicker))
                <p class="admin-workspace__kicker">{{ $kicker }}</p>
            @endif
            <h1 class="admin-workspace__title">{{ $title }}</h1>
        </div>

        @isset($summary)
            <div class="admin-workspace__summary">
                {{ $summary }}
            </div>
        @endisset

        @isset($actions)
            <div class="admin-toolbar admin-workspace__actions">
                {{ $actions }}
            </div>
        @endisset
    </header>

    <div class="admin-workspace__body">
        {{ $slot }}
    </div>
</div>
