{{-- Shared ranked Admin table; compact single-line identity keeps every row at the same height. --}}
<x-admin.table class="admin-table--data admin-table--ranked home-hero-group-table" aria-label="Manual Sourced Artworks">
    <table class="admin-table--six-grid">
        <colgroup>
            <col class="admin-table__col-position">
            <col class="admin-table__col-drag">
            <col>
            <col class="admin-table__col-actions-move-state-remove">
        </colgroup>
        <thead>
            <tr>
                <th scope="colgroup" colspan="2" class="admin-table__ordering-heading">{{ __('Position') }}</th>
                <th scope="col">{{ __('Candidate') }}</th>
                <th scope="col" class="admin-table__actions"><span class="admin-row-actions-heading">{{ __('Actions') }}</span></th>
            </tr>
        </thead>
        <tbody wire:sort="sortHeroArtwork">
            @forelse ($rows as $row)
                <tr wire:key="home-hero-group-member-{{ $row['id'] }}" wire:sort:item="{{ $row['id'] }}">
                    <td class="admin-table__position">
                        <span class="admin-position">{{ $loop->iteration }}</span>
                    </td>
                    <td class="admin-table__drag">
                        <button class="admin-drag-handle" type="button" wire:sort:handle
                            title="{{ __('Drag to reorder') }}"
                            aria-label="{{ __('Drag') }} {{ $row['title'] }} {{ __('to reorder') }}">⋮⋮</button>
                    </td>
                    <td class="admin-table__identity">
                        <div class="home-source-table__candidate-inline">
                            <span class="home-source-candidate__preview">
                                @if ($row['thumbnail_url'])
                                    <img src="{{ $row['thumbnail_url'] }}" alt="" loading="lazy" decoding="async" draggable="false">
                                @else
                                    <span>—</span>
                                @endif
                            </span>
                            <strong title="{{ implode(', ', array_filter([$row['title'], $row['gallery'], $row['date']])) }}">{{ $row['title'] }}</strong>
                        </div>
                    </td>
                    <td class="admin-table__actions">
                        <x-admin.toolbar class="admin-row-actions admin-row-actions--canonical admin-row-actions--stable admin-row-actions--move-state-remove">
                            <x-admin.row-action :action="\App\Filament\Support\AdminRowAction::MoveUp"
                                :disabled="$loop->first"
                                wire:click="moveHeroArtwork('{{ $row['id'] }}', 'up')" />
                            <x-admin.row-action :action="\App\Filament\Support\AdminRowAction::MoveDown"
                                :disabled="$loop->last"
                                wire:click="moveHeroArtwork('{{ $row['id'] }}', 'down')" />
                            <x-admin.row-action
                                :action="$row['disabled'] ? \App\Filament\Support\AdminRowAction::Publish : \App\Filament\Support\AdminRowAction::Unpublish"
                                :label="$row['disabled'] ? 'Enable' : 'Disable'"
                                wire:click="setManualHeroCandidateEnabled('{{ $row['id'] }}', {{ $row['disabled'] ? 'true' : 'false' }})" />
                            <x-admin.row-action :action="\App\Filament\Support\AdminRowAction::Delete"
                                label="Remove"
                                wire:click="removeHeroCandidate('{{ $row['id'] }}')" />
                        </x-admin.toolbar>
                    </td>
                </tr>
            @empty
                <tr>
                    <td colspan="4" class="admin-table__empty-cell">
                        <x-admin.empty-state title="No manual candidates" minimal />
                    </td>
                </tr>
            @endforelse
        </tbody>
    </table>
</x-admin.table>
