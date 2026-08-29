<div class="fi-sidebar-footer admin-sidebar-preview">
    <ul class="fi-sidebar-group-items">
        <x-filament-panels::sidebar.item
            :active="false"
            :icon="\Filament\Support\Icons\Heroicon::OutlinedEye"
            :should-open-url-in-new-tab="true"
            :url="route('preview.home')"
        >Preview</x-filament-panels::sidebar.item>
    </ul>
</div>
