<x-filament-panels::page>
    <x-admin.workspace title="Pages">
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
            <x-admin.empty-state kicker="Empty structure" title="No site nodes exist" />
        @endif
    </x-admin.workspace>
</x-filament-panels::page>
