{{-- Dummy membership uses the same Admin table contract as other editors. --}}
<x-admin.table class="admin-table--data admin-table--dialog-inset home-dummy-gallery-table" aria-label="Dummy Gallery members">
    <table class="admin-table--six-grid">
        <colgroup>
            <col class="home-dummy-gallery-table__col-artwork">
            <col class="home-dummy-gallery-table__col-storage">
            <col style="width: 4.2rem">
            <col style="width: 3.5rem">
            <col style="width: 3.5rem">
            <col class="home-dummy-gallery-table__col-actions">
        </colgroup>
        <thead>
            <tr>
                <th scope="col">{{ __('Artwork') }}</th>
                <th scope="col">{{ __('Storage') }}</th>
                <th scope="col">{{ __('Year') }}</th>
                <th scope="col">{{ __('Month') }}</th>
                <th scope="col">{{ __('Day') }}</th>
                <th scope="col" class="admin-table__actions">
                    <span class="admin-row-actions-heading">{{ __('Actions') }}</span>
                </th>
            </tr>
        </thead>
        <tbody>
            @forelse ($members as $member)
                @php $isStorage = str_starts_with($member['id'], 'media:'); @endphp
                <tr wire:key="home-dummy-member-{{ $creating ? 'new' : $galleryId }}-{{ $member['id'] }}">
                    <td class="admin-table__identity">
                        @unless ($isStorage)
                            <div class="home-source-table__candidate-inline">
                                <span class="home-source-candidate__preview">
                                    @if ($member['thumbnail_url'])
                                        <img src="{{ $member['thumbnail_url'] }}" alt="" loading="lazy" decoding="async" draggable="false">
                                    @else
                                        <span>—</span>
                                    @endif
                                </span>
                                <strong title="{{ $member['title'] }}">{{ $member['title'] }}</strong>
                            </div>
                        @else
                            <span class="admin-muted">—</span>
                        @endunless
                    </td>
                    <td class="admin-table__identity">
                        @if ($isStorage)
                            <div class="home-source-table__candidate-inline">
                                <span class="home-source-candidate__preview">
                                    @if ($member['thumbnail_url'])
                                        <img src="{{ $member['thumbnail_url'] }}" alt="" loading="lazy" decoding="async" draggable="false">
                                    @else
                                        <span>—</span>
                                    @endif
                                </span>
                                <input class="admin-inline-input" type="text" maxlength="255"
                                    value="{{ $member['title'] }}" aria-label="Storage candidate title"
                                    wire:change="{{ $creating ? 'updateDraftDummyMember' : 'updateDummyMember' }}({{ $creating ? '0' : $galleryId }}, '{{ $member['id'] }}', 'title', $event.target.value)">
                            </div>
                        @else
                            <span class="admin-muted">—</span>
                        @endif
                    </td>
                    @foreach (['year', 'month', 'day'] as $part)
                        <td>
                            @if ($isStorage)
                                <input class="admin-inline-input" type="number" min="1"
                                    max="{{ $part === 'year' ? 9999 : ($part === 'month' ? 12 : 31) }}"
                                    value="{{ $member[$part] ?? '' }}"
                                    aria-label="{{ ucfirst($part) }} of {{ $member['title'] }}"
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
                                label="Remove"
                                wire:click="{{ $creating ? 'removeDraftDummyMember' : 'removeDummyMember' }}({{ $creating ? '0' : $galleryId }}, '{{ $member['id'] }}')"
                            />
                        </x-admin.toolbar>
                    </td>
                </tr>
            @empty
                <tr>
                    <td colspan="6" class="admin-table__empty-cell">
                        <x-admin.empty-state title="No Dummy Gallery members" minimal />
                    </td>
                </tr>
            @endforelse
        </tbody>
    </table>
</x-admin.table>
