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
        @include('filament.resources.media-assets.partials.storage-overview')
        @include('filament.resources.media-assets.partials.site-storage-summary')
        @include('filament.resources.media-assets.partials.storage-library')
    </x-admin.workspace>
</x-filament-panels::page>