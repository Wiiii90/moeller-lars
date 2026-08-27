<x-filament-panels::page>
    <x-admin.workspace title="General" class="general-workspace">
        @include('filament.schemas.components.general-status-metrics')

        <div class="general-workspace__sheet" aria-label="General settings">
            {{ $this->form }}
        </div>
    </x-admin.workspace>
</x-filament-panels::page>
