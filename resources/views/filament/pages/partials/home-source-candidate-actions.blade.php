{{-- Each candidate acts only on its own saved identity; Gallery rows cannot Remove. --}}
@if ($candidate)
    <div class="admin-row-actions admin-row-actions--canonical admin-row-actions--stable admin-toolbar home-source-table__row-actions home-source-table__candidate-actions">
        @if ($manualSource)
            <x-admin.row-action
                :action="($candidate['disabled'] ?? false) ? \App\Filament\Support\AdminRowAction::Publish : \App\Filament\Support\AdminRowAction::Unpublish"
                :label="($candidate['disabled'] ?? false) ? 'Enable' : 'Disable'"
                wire:click="setManualHeroCandidateEnabled('{{ $candidate['id'] }}', {{ ($candidate['disabled'] ?? false) ? 'true' : 'false' }})"
            />
            <x-admin.row-action
                :action="\App\Filament\Support\AdminRowAction::Delete"
                label="Remove"
                wire:click="removeHeroCandidate('{{ $candidate['id'] }}')"
            />
        @else
            <x-admin.row-action
                :action="($candidate['disabled'] ?? false) ? \App\Filament\Support\AdminRowAction::Publish : \App\Filament\Support\AdminRowAction::Unpublish"
                :label="($candidate['disabled'] ?? false) ? 'Enable' : 'Disable'"
                wire:click="setAutomaticHeroCandidateEnabled({{ (int) substr($candidate['id'], 8) }}, {{ ($candidate['disabled'] ?? false) ? 'true' : 'false' }})"
            />
            <span class="home-source-table__action-slot" aria-hidden="true"></span>
        @endif
    </div>
@endif
