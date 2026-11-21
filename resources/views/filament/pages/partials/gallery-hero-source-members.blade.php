{{-- Hero source members share the same Date/Actions axes, but regular Galleries cannot add or remove Artwork references. --}}
<x-admin.table class="admin-table--data admin-table--dialog-inset admin-table--static gallery-hero-source-members-table" aria-label="{{ ($regularSource ?? false) ? 'Hero Gallery candidates' : 'Dummy Gallery members' }}">
    <table class="admin-table--six-grid">
        <colgroup>
            <col class="gallery-hero-source-members-table__col-identity">
            <col class="gallery-hero-source-members-table__col-date">
            <col class="gallery-hero-source-members-table__col-actions">
        </colgroup>
        <thead>
            <tr>
                <th scope="col">{{ __('Artwork / Storage') }}</th>
                <th scope="col">{{ __('Date') }}</th>
                <th scope="col" class="admin-table__actions">
                    <span class="admin-row-actions-heading">{{ __('Actions') }}</span>
                </th>
            </tr>
        </thead>
        <tbody>
            @forelse ($members as $member)
                @php $isStorage = str_starts_with($member['id'], 'media:'); @endphp
                <tr wire:key="gallery-hero-dummy-member-{{ $creating ? 'new' : $galleryId }}-{{ $member['id'] }}">
                    <td class="admin-table__identity">
                        <div class="gallery-hero-source-table__candidate-inline">
                            <span class="gallery-hero-source-candidate__preview">
                                @if ($member['thumbnail_url'])
                                    <img src="{{ $member['thumbnail_url'] }}" alt="" loading="lazy" decoding="async" draggable="false">
                                @else
                                    <span>—</span>
                                @endif
                            </span>
                            @if ($isStorage && ! ($regularSource ?? false))
                                <input class="admin-inline-input" type="text" maxlength="255"
                                    value="{{ $member['title'] }}" aria-label="Storage candidate title"
                                    wire:change="{{ $creating ? 'updateDraftDummyMember' : 'updateDummyMember' }}({{ $creating ? '0' : $galleryId }}, '{{ $member['id'] }}', 'title', $event.target.value)">
                            @else
                                <strong title="{{ $member['title'] }}">{{ $member['title'] }}</strong>
                            @endif
                        </div>
                    </td>
                    <td class="gallery-hero-source-members-table__date">
                        <span class="admin-muted">{{ $member['date'] ?? '—' }}</span>
                    </td>
                    <td class="admin-table__actions">
                        <x-admin.toolbar class="admin-row-actions admin-row-actions--canonical admin-row-actions--stable admin-row-actions--hero-member">
                            @if ($regularSource ?? false)
                                <x-admin.row-action
                                    :action="($member['disabled'] ?? false) ? \App\Filament\Support\AdminRowAction::Publish : \App\Filament\Support\AdminRowAction::Unpublish"
                                    :label="($member['disabled'] ?? false) ? 'Enable' : 'Disable'"
                                    wire:click="setAutomaticHeroCandidateEnabled({{ (int) substr($member['id'], 8) }}, {{ ($member['disabled'] ?? false) ? 'true' : 'false' }})"
                                />
                                <span class="gallery-hero-source-table__action-slot" aria-hidden="true"></span>
                            @else
                                <x-admin.row-action
                                    :action="($member['disabled'] ?? false) ? \App\Filament\Support\AdminRowAction::Publish : \App\Filament\Support\AdminRowAction::Unpublish"
                                    :label="($member['disabled'] ?? false) ? 'Enable' : 'Disable'"
                                    wire:click="{{ $creating ? 'toggleDraftDummyMember' : 'toggleDummyMember' }}({{ $creating ? '0' : $galleryId }}, '{{ $member['id'] }}')"
                                />
                                <x-admin.row-action :action="\App\Filament\Support\AdminRowAction::Delete"
                                    label="Remove"
                                    wire:click="{{ $creating ? 'removeDraftDummyMember' : 'removeDummyMember' }}({{ $creating ? '0' : $galleryId }}, '{{ $member['id'] }}')"
                                />
                            @endif
                        </x-admin.toolbar>
                    </td>
                </tr>
            @empty
                <tr>
                    <td colspan="3" class="admin-table__empty-cell">
                        <x-admin.empty-state title="Empty" minimal />
                    </td>
                </tr>
            @endforelse
        </tbody>
    </table>
</x-admin.table>
