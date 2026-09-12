<x-filament-panels::page>
    <x-admin.workspace title="Storage" class="media-workspace admin-storage">
        <x-slot:status>
            <x-admin.status :tone="$capacity['status_tone'] ?? 'neutral'">
                {{ $capacity['status_label'] ?? 'Storage unavailable' }}
            </x-admin.status>
        </x-slot:status>

        @include('filament.resources.media-assets.partials.storage-overview')
        @include('filament.resources.media-assets.partials.storage-library')
    </x-admin.workspace>
</x-filament-panels::page>
