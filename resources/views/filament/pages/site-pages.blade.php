<x-filament-panels::page>
    <div class="artist-workspace artist-pages">
        <header class="artist-workspace__head">
            <div>
                <p class="artist-workspace__kicker">Site structure</p>
                <h2>Pages</h2>
            </div>
        </header>

        <section class="artist-page-list artist-page-tree" aria-label="Public site structure">
            @foreach ($sections as $section)
                <div class="artist-page-tree__branch" data-has-children="{{ $section['has_children'] ? 'true' : 'false' }}">
                    @include('filament.pages.partials.site-section-row', ['section' => $section])

                    @if ($section['children'] !== [])
                        <div class="artist-page-tree__children" aria-label="Children of {{ $section['navigation_label'] ?: $section['title'] }}">
                            @foreach ($section['children'] as $child)
                                @include('filament.pages.partials.site-section-row', ['section' => $child])
                            @endforeach
                        </div>
                    @endif
                </div>
            @endforeach
        </section>
    </div>
</x-filament-panels::page>
