<x-filament-panels::page>
    <x-admin.workspace title="General">
        @include('filament.schemas.components.general-status-metrics')
        {{ $this->form }}
    </x-admin.workspace>
</x-filament-panels::page>
