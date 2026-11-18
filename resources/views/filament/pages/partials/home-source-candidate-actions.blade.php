{{-- Gallery ownership, publication and candidate inclusion have distinct canonical mutations. --}}
@if ($candidate)
    <div class="admin-row-actions admin-row-actions--canonical admin-row-actions--stable admin-row-actions--move-state-delete admin-toolbar home-source-table__row-actions home-source-table__candidate-actions">
        <span class="home-source-table__action-slot" aria-hidden="true"></span>
        <span class="home-source-table__action-slot" aria-hidden="true"></span>
        <span class="home-source-table__action-slot" aria-hidden="true"></span>
        @if ($manualSource)
            <x-admin.row-action
                :action="($candidate['disabled'] ?? false) ? \App\Filament\Support\AdminRowAction::Publish : \App\Filament\Support\AdminRowAction::Unpublish"
                :label="($candidate['disabled'] ?? false) ? 'Enable' : 'Disable'"
                wire:click="toggleDummyMember({{ $groupId }}, '{{ $candidate['id'] }}')" />
            <x-admin.row-action :action="\App\Filament\Support\AdminRowAction::Delete"
                label="Remove from Dummy Gallery"
                wire:click="removeDummyMember({{ $groupId }}, '{{ $candidate['id'] }}')" />
        @else
            <x-admin.row-action
                :action="($candidate['disabled'] ?? false) ? \App\Filament\Support\AdminRowAction::Publish : \App\Filament\Support\AdminRowAction::Unpublish"
                :label="($candidate['disabled'] ?? false) ? 'Enable' : 'Disable'"
                wire:click="setAutomaticHeroCandidateEnabled({{ (int) substr($candidate['id'], 8) }}, {{ ($candidate['disabled'] ?? false) ? 'true' : 'false' }})" />
            <span class="home-source-table__action-slot" aria-hidden="true"></span>
        @endif
    </div>
@endif
