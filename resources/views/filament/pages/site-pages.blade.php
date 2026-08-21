<x-filament-panels::page>
    @php
        $allSections = collect($sections)->flatMap(fn (array $section) => [$section, ...$section['children']]);
        $published = $allSections->where('state', 'published')->count();
        $visible = $allSections->where('visible', true)->count();
        $galleries = $allSections->where('type', 'gallery')->count();
    @endphp

    <div class="artist-workspace">
        <header class="artist-workspace__head">
            <div>
                <p class="artist-workspace__kicker">Site structure</p>
                <h2>Public pages</h2>
                <p>The canonical page and navigation tree. Typed pages keep their dedicated editors; navigation groups organize submenu entries without creating a fake public page.</p>
            </div>

            <div class="artist-workspace__summary" aria-label="Site structure summary">
                <div><strong>{{ $allSections->count() }}</strong><span>Sections</span></div>
                <div><strong>{{ $galleries }}</strong><span>Galleries</span></div>
                <div><strong>{{ $published }}</strong><span>Published</span></div>
                <div><strong>{{ $visible }}</strong><span>In menu</span></div>
            </div>
        </header>

        <section aria-label="Public site sections">
            <div class="artist-section-list">
                @foreach ($sections as $section)
                    @include('filament.pages.partials.site-section-row', ['section' => $section])

                    @if ($section['children'] !== [])
                        <details open wire:key="site-section-children-{{ $section['id'] }}">
                            <summary class="artist-workspace__kicker">{{ count($section['children']) }} submenu {{ count($section['children']) === 1 ? 'section' : 'sections' }}</summary>
                            <div class="artist-section-list">
                                @foreach ($section['children'] as $child)
                                    @include('filament.pages.partials.site-section-row', ['section' => $child])
                                @endforeach
                            </div>
                        </details>
                    @endif
                @endforeach
            </div>
        </section>

        <footer class="artist-workspace__footnote">
            <span>Save editorial work first, then use Preview to inspect hidden sections, draft content and the draft navigation before publishing.</span>
            <span>Navigation groups are capability-driven nodes with children and no public route. Media, Analytics and Storage remain global tools.</span>
        </footer>
    </div>
</x-filament-panels::page>
