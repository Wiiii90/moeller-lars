{{-- Gallery ownership, publication and candidate inclusion have distinct canonical mutations. --}}
@if ($candidate)
    <div class="admin-row-actions admin-row-actions--canonical admin-row-actions--stable admin-row-actions--move-state-delete admin-toolbar home-source-table__row-actions home-source-table__candidate-actions">
        @if ($showSourceActions ?? false)
            <x-admin.row-action :action="\App\Filament\Support\AdminRowAction::MoveUp"
                label="Move Gallery up"
                :disabled="! str_starts_with($heroSourceRule, 'gallery_') || $sourceSearch !== ''"
                wire:click="moveHeroSource({{ $groupId }}, 'up')" />
            <x-admin.row-action :action="\App\Filament\Support\AdminRowAction::MoveDown"
                label="Move Gallery down"
                :disabled="! str_starts_with($heroSourceRule, 'gallery_') || $sourceSearch !== ''"
                wire:click="moveHeroSource({{ $groupId }}, 'down')" />
            @if ($manualSource)
                <x-admin.row-action :action="\App\Filament\Support\AdminRowAction::Edit"
                    label="Edit Dummy Gallery"
                    wire:click="mountAction('editDummyGallery', { gallery: {{ $groupId }} })" />
            @else
                <span class="home-source-table__action-slot" aria-hidden="true"></span>
            @endif
        @else
            <span class="home-source-table__action-slot" aria-hidden="true"></span>
            <span class="home-source-table__action-slot" aria-hidden="true"></span>
            <span class="home-source-table__action-slot" aria-hidden="true"></span>
        @endif
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
