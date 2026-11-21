{{-- Source-level controls stay present on the first expanded candidate row. --}}
@if ($candidate)
    <div class="admin-row-actions admin-row-actions--canonical admin-row-actions--stable {{ str_starts_with($heroSourceRule, 'gallery_') ? 'admin-row-actions--move-state-delete' : 'admin-row-actions--edit-state-delete' }} admin-toolbar gallery-hero-source-table__row-actions gallery-hero-source-table__candidate-actions">
        @if (str_starts_with($heroSourceRule, 'gallery_'))
            @if ($showSourceActions ?? false)
                <x-admin.row-action :action="\App\Filament\Support\AdminRowAction::MoveUp"
                    :disabled="$sourceSearch !== ''" wire:click="moveHeroSource({{ $groupId }}, 'up')" />
                <x-admin.row-action :action="\App\Filament\Support\AdminRowAction::MoveDown"
                    :disabled="$sourceSearch !== ''" wire:click="moveHeroSource({{ $groupId }}, 'down')" />
            @else
                <span class="gallery-hero-source-table__action-slot" aria-hidden="true"></span>
                <span class="gallery-hero-source-table__action-slot" aria-hidden="true"></span>
            @endif
        @endif
        @if ($showSourceActions ?? false)
            @if ($manualSource)
                <x-admin.row-action :action="\App\Filament\Support\AdminRowAction::Edit"
                    label="Edit"
                    wire:click="mountAction('editDummyGallery', { gallery: {{ $groupId }} })" />
            @elseif ($gallery['workspace_url'])
                <x-admin.row-action :action="\App\Filament\Support\AdminRowAction::Edit"
                    label="Edit Gallery"
                    :href="$gallery['workspace_url']"
                    wire:navigate />
            @else
                <span class="gallery-hero-source-table__action-slot" aria-hidden="true"></span>
            @endif
        @elseif (str_starts_with($candidate['id'], 'artwork:'))
            <x-admin.row-action :action="\App\Filament\Support\AdminRowAction::Edit"
                label="Edit"
                wire:click="mountAction('editHeroArtwork', { artwork: {{ (int) substr($candidate['id'], 8) }} })" />
        @else
            <span class="gallery-hero-source-table__action-slot" aria-hidden="true"></span>
        @endif
        @if ($manualSource)
            <x-admin.row-action
                :action="($candidate['disabled'] ?? false) ? \App\Filament\Support\AdminRowAction::Publish : \App\Filament\Support\AdminRowAction::Unpublish"
                :label="($candidate['disabled'] ?? false) ? 'Enable' : 'Disable'"
                wire:click="toggleDummyMember({{ $groupId }}, '{{ $candidate['id'] }}')" />
            <x-admin.row-action :action="\App\Filament\Support\AdminRowAction::Delete"
                label="Remove"
                wire:click="removeDummyMember({{ $groupId }}, '{{ $candidate['id'] }}')" />
        @else
            <x-admin.row-action
                :action="($candidate['disabled'] ?? false) ? \App\Filament\Support\AdminRowAction::Publish : \App\Filament\Support\AdminRowAction::Unpublish"
                :label="($candidate['disabled'] ?? false) ? 'Enable' : 'Disable'"
                wire:click="setAutomaticHeroCandidateEnabled({{ (int) substr($candidate['id'], 8) }}, {{ ($candidate['disabled'] ?? false) ? 'true' : 'false' }})" />
            <span class="gallery-hero-source-table__action-slot" aria-hidden="true"></span>
        @endif
    </div>
@endif
