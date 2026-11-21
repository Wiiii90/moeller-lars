{{-- Candidate actions never perform Gallery-level editing or ordering. --}}
<div class="admin-row-actions admin-row-actions--canonical admin-row-actions--stable {{ str_starts_with($heroSourceRule, 'gallery_') ? 'admin-row-actions--hero-ranked' : 'admin-row-actions--hero-source' }} admin-toolbar gallery-hero-source-table__row-actions gallery-hero-source-table__candidate-actions">
    @if (str_starts_with($heroSourceRule, 'gallery_'))
        <span class="gallery-hero-source-table__action-slot" aria-hidden="true"></span>
        <span class="gallery-hero-source-table__action-slot" aria-hidden="true"></span>
    @endif
    @if (str_starts_with($candidate['id'], 'artwork:'))
        <x-admin.row-action :action="\App\Filament\Support\AdminRowAction::Edit"
            label="Edit"
            wire:click="mountAction('editHeroArtwork', { gallery: {{ $groupId }}, artwork: {{ (int) substr($candidate['id'], 8) }} })" />
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
