<x-filament-panels::page>
    <x-admin.workspace kicker="Site structure" title="Pages">
        @if ($sections !== [])
            <x-admin.list class="admin-site-tree" aria-label="Public site structure">
                @foreach ($sections as $section)
                    @include('filament.pages.partials.site-section-row', ['section' => $section])

                    @foreach ($section['children'] as $child)
                        @include('filament.pages.partials.site-section-row', ['section' => $child])
                    @endforeach
                @endforeach
            </x-admin.list>
        @else
            <x-admin.empty-state kicker="Empty structure" title="No site nodes exist">
                <p>Add a Gallery, Journal, Custom Page or Navigation Node from the page action.</p>
            </x-admin.empty-state>
        @endif
    </x-admin.workspace>
</x-filament-panels::page>
