{{-- A real shared Admin table; a Dummy Gallery owns membership, not a manual ranking. --}}
<x-admin.table class="admin-table--data admin-table--dialog-inset home-dummy-gallery-table" aria-label="Dummy Gallery members">
    <table class="admin-table--six-grid">
        <colgroup>
            <col>
            <col style="width: 4.2rem">
            <col style="width: 3.5rem">
            <col style="width: 3.5rem">
            <col class="home-dummy-gallery-table__col-actions">
        </colgroup>
        <thead>
            <tr>
                <th scope="col">{{ __('Artwork / Storage') }}</th>
                <th scope="col">{{ __('Year') }}</th>
                <th scope="col">{{ __('Month') }}</th>
                <th scope="col">{{ __('Day') }}</th>
                <th scope="col" class="admin-table__actions">{{ __('Actions') }}</th>
            </tr>
        </thead>
        <tbody>
            @forelse ($members as $member)
                @php $isStorage = str_starts_with($member['id'], 'media:'); @endphp
                <tr wire:key="home-dummy-member-{{ $creating ? 'new' : $galleryId }}-{{ $member['id'] }}">
                    <td class="admin-table__identity">
                        <div class="home-source-table__candidate-inline">
                            <span class="home-source-candidate__preview">
                                @if ($member['thumbnail_url'])
                                    <img src="{{ $member['thumbnail_url'] }}" alt="" loading="lazy" decoding="async" draggable="false">
                                @else
                                    <span>—</span>
                                @endif
                            </span>
                            @if ($isStorage)
                                <input class="admin-inline-input" type="text" maxlength="255"
                                    value="{{ $member['title'] }}" aria-label="Candidate title"
                                    wire:change="{{ $creating ? 'updateDraftDummyMember' : 'updateDummyMember' }}({{ $creating ? '0' : $galleryId }}, '{{ $member['id'] }}', 'title', $event.target.value)">
                            @else
                                <strong title="{{ $member['title'] }}">{{ $member['title'] }}</strong>
                            @endif
                        </div>
                    </td>
                    @foreach (['year', 'month', 'day'] as $part)
                        <td>
                            @if ($isStorage)
                                <input class="admin-inline-input" type="number" min="1"
                                    max="{{ $part === 'year' ? 9999 : ($part === 'month' ? 12 : 31) }}"
                                    value="{{ $member[$part] ?? '' }}" aria-label="{{ ucfirst($part) }} of {{ $member['title'] }}"
                                    wire:change="{{ $creating ? 'updateDraftDummyMember' : 'updateDummyMember' }}({{ $creating ? '0' : $galleryId }}, '{{ $member['id'] }}', '{{ $part }}', $event.target.value)">
                            @else
                                <span class="admin-muted">{{ $member[$part] ?? '—' }}</span>
                            @endif
                        </td>
                    @endforeach
                    <td class="admin-table__actions">
                        <x-admin.toolbar class="admin-row-actions admin-row-actions--canonical admin-row-actions--stable admin-row-actions--two-static">
                            <x-admin.row-action
                                :action="($member['disabled'] ?? false) ? \App\Filament\Support\AdminRowAction::Publish : \App\Filament\Support\AdminRowAction::Unpublish"
                                :label="($member['disabled'] ?? false) ? 'Enable' : 'Disable'"
                                wire:click="{{ $creating ? 'toggleDraftDummyMember' : 'toggleDummyMember' }}({{ $creating ? '0' : $galleryId }}, '{{ $member['id'] }}')"
                            />
                            <x-admin.row-action :action="\App\Filament\Support\AdminRowAction::Delete"
                                label="Remove from Dummy Gallery"
                                wire:click="{{ $creating ? 'removeDraftDummyMember' : 'removeDummyMember' }}({{ $creating ? '0' : $galleryId }}, '{{ $member['id'] }}')"
                            />
                        </x-admin.toolbar>
                    </td>
                </tr>
            @empty
                <tr>
                    <td colspan="5" class="admin-table__empty-cell">
                        <x-admin.empty-state title="No Dummy Gallery members" minimal />
                    </td>
                </tr>
            @endforelse
        </tbody>
    </table>
</x-admin.table>
