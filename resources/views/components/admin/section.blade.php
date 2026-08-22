@props([
    'kicker' => null,
    'title' => null,
])

<section {{ $attributes->class(['admin-section']) }}>
    @if ($kicker !== null || $title !== null || isset($actions))
        <header class="admin-section__header">
            <div>
                @if ($kicker !== null)
                    <p class="admin-section__kicker">{{ $kicker }}</p>
                @endif
                @if ($title !== null)
                    <h3 class="admin-section__title">{{ $title }}</h3>
                @endif
            </div>
            @isset($actions)
                <div class="admin-toolbar admin-section__actions">{{ $actions }}</div>
            @endisset
        </header>
    @endif

    <div class="admin-section__body">{{ $slot }}</div>
</section>
