<x-filament-panels::page>
    @php
        $usageGroups = array_map(static function (array $group): array {
            $group['options'] = array_map(static function (array $option): array {
                if (($option['value'] ?? null) === 'site-identity') {
                    $option['label'] = 'Site icon';
                }

                return $option;
            }, $group['options'] ?? []);

            return $group;
        }, $usageGroups);
    @endphp

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
