<x-filament-panels::page>
    <div class="artist-workspace artist-pages">
        <header class="artist-workspace__head">
            <div>
                <p class="artist-workspace__kicker">Site structure</p>
                <h2>Pages</h2>
            </div>
        </header>

        <section class="artist-page-list" aria-label="Public site structure">
            @foreach ($sections as $section)
                @include('filament.pages.partials.site-section-row', ['section' => $section])

                @foreach ($section['children'] as $child)
                    @include('filament.pages.partials.site-section-row', ['section' => $child])
                @endforeach
            @endforeach
        </section>
    </div>
</x-filament-panels::page>
