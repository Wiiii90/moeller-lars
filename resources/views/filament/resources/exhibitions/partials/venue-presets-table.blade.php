<div>
    <x-admin.table class="admin-table--data" aria-label="Exhibition venue presets">
        <table class="admin-table--six-grid">
            <colgroup>
                <col>
                <col>
                <col>
                <col class="admin-table__col-actions-move-delete">
            </colgroup>
            <thead>
                <tr>
                    <th scope="col">Venue</th>
                    <th scope="col">Street address</th>
                    <th scope="col">Place</th>
                    <th scope="col" class="admin-table__actions"><span class="admin-row-actions-heading">Actions</span></th>
                </tr>
            </thead>
            <tbody>
                @forelse ($venues as $venue)
                    <tr wire:key="exhibition-venue-{{ $venue->getKey() }}">
                        <td>{{ $venue->name }}</td>
                        <td>{{ $venue->street ?: '—' }}</td>
                        <td>{{ collect([$venue->postal_code, $venue->city, $venue->country])->filter()->implode(' ') ?: '—' }}</td>
                        <td class="admin-table__actions">
                            <x-admin.toolbar class="admin-row-actions admin-row-actions--canonical admin-row-actions--stable">
                                <x-admin.row-action :action="\App\Filament\Support\AdminRowAction::Edit"
                                    wire:click="mountAction('editExhibitionVenuePreset', { venue: {{ $venue->getKey() }} })" />
                                <x-admin.row-action :action="\App\Filament\Support\AdminRowAction::Delete"
                                    wire:click="deleteExhibitionVenuePreset({{ $venue->getKey() }})" />
                            </x-admin.toolbar>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="4" class="admin-table__empty-cell"><x-admin.empty-state title="Empty" minimal /></td></tr>
                @endforelse
            </tbody>
        </table>
    </x-admin.table>
    <x-admin.add-row wire:click="mountAction('addExhibitionVenuePreset', { organizer: {{ $organizerId }} })">Add venue</x-admin.add-row>
</div>
