@php
    $isOrganizer = $directoryKind === 'Organizer';
    $saveMethod = $isOrganizer ? 'saveExhibitionOrganizerField' : 'saveExhibitionVenueField';
    $deleteMethod = $isOrganizer ? 'deleteExhibitionOrganizerPreset' : 'deleteExhibitionVenuePreset';
    $addAction = $isOrganizer ? 'addExhibitionOrganizer' : 'addExhibitionVenue';
    $type = $isOrganizer ? 'organizer' : 'venue';
@endphp
<div>
    <x-admin.table class="admin-table--data" aria-label="{{ $isOrganizer ? 'Exhibition organizers' : 'Exhibition venues' }}">
        <table class="admin-table--six-grid journal-directory-table">
            <colgroup>
                <col class="journal-directory__name-col">
                <col class="journal-directory__details-col">
                <col class="journal-directory__actions-col">
            </colgroup>
            <thead>
                <tr>
                    <th scope="col">{{ $directoryKind }}</th>
                    <th scope="col">Details</th>
                    <th scope="col" class="admin-table__actions"><span class="admin-row-actions-heading">Actions</span></th>
                </tr>
            </thead>
            <tbody>
                @forelse ($records as $record)
                    <tr wire:key="exhibition-directory-{{ $type }}-{{ $record->getKey() }}">
                        <td class="journal-directory__name-cell">
                            <label class="admin-data-field journal-directory__field">
                                <span>Name</span>
                                <input type="text" value="{{ $record->name }}" maxlength="240" required
                                    wire:change="{{ $saveMethod }}({{ $record->getKey() }}, 'name', $event.target.value)"
                                    aria-label="{{ $directoryKind }} name">
                            </label>
                        </td>
                        <td class="journal-directory__details-cell">
                            <div class="journal-directory__detail-grid">
                                @foreach ([
                                    'website' => ['Website', 'url', '2048'],
                                    'street' => ['Street address', 'text', '500'],
                                    'postal_code' => ['Postal code', 'text', '16'],
                                    'city' => ['City', 'text', '160'],
                                    'country' => ['Country', 'text', '160'],
                                ] as $field => [$label, $inputType, $maxLength])
                                    <label class="admin-data-field journal-directory__field journal-directory__field--{{ $field }}">
                                        <span>{{ $label }}</span>
                                        <input type="{{ $inputType }}" value="{{ $record->{$field} }}" maxlength="{{ $maxLength }}"
                                            wire:change="{{ $saveMethod }}({{ $record->getKey() }}, '{{ $field }}', $event.target.value)"
                                            aria-label="{{ $directoryKind }} {{ strtolower($label) }}">
                                    </label>
                                @endforeach
                            </div>
                        </td>
                        <td class="admin-table__actions">
                            <x-admin.toolbar class="admin-row-actions admin-row-actions--canonical">
                                <x-admin.row-action :action="\App\Filament\Support\AdminRowAction::Delete"
                                    wire:click="{{ $deleteMethod }}({{ $record->getKey() }})" />
                            </x-admin.toolbar>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="3" class="admin-table__empty-cell"><x-admin.empty-state title="Empty" minimal /></td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </x-admin.table>
    <x-admin.add-row wire:click="mountAction('{{ $addAction }}')">Add {{ $type }}</x-admin.add-row>
</div>
