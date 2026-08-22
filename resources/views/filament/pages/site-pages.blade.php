<x-filament-panels::page>
    <x-admin.workspace kicker="Site structure" title="Pages" class="artist-pages">
        <section class="artist-page-list" aria-label="Public site structure">
            @foreach ($sections as $section)
                @include('filament.pages.partials.site-section-row', ['section' => $section])

                @foreach ($section['children'] as $child)
                    @include('filament.pages.partials.site-section-row', ['section' => $child])
                @endforeach
            @endforeach
        </section>
    </x-admin.workspace>
</x-filament-panels::page>
