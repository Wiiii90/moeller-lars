@php
    $position = $pinned ? ($item['pin_position'] ?? null) : ($item['feed_position'] ?? null);
    $mutable = is_int($item['contact_id'] ?? null) || is_int($item['notification_id'] ?? null);
    $read = $mutable ? str_starts_with((string) $item['status'], 'Read') : null;
    $selected = in_array((string) $item['key'], $selectedFeedKeys, true);
@endphp

<tr
    @class([
        'admin-data-row',
        'admin-dashboard__feed-row',
        'is-selected' => $selected,
        'is-pinned' => $pinned,
        'is-unread' => $mutable && ! $read,
    ])
    wire:key="dashboard-feed-{{ $item['key'] }}"
    @if ($pinned) wire:sort:item="{{ $item['key'] }}" @endif
>
    <td class="admin-table__position">
        <span
            @class(['admin-position', 'admin-dashboard__priority-position' => $pinned])
            @if ($pinned) title="Pinned priority {{ $position }}" @endif
        >{{ $pinned ? 'P'.$position : $position }}</span>
    </td>
    <td class="admin-table__drag">
        @if ($pinned)
            <button
                class="admin-drag-handle"
                type="button"
                wire:sort:handle
                aria-label="Drag pinned entry to a new priority"
                title="Reorder pinned priority"
            >⋮⋮</button>
        @endif
    </td>
    <td class="admin-data-nowrap">{{ $item['type_label'] }}</td>
    <td class="admin-data-nowrap"><time datetime="{{ $item['date'] }}">{{ $item['date_display'] }}</time></td>
    <td class="admin-data-title">{{ $item['title'] }}</td>
    <td class="admin-data-sender">
        @if ($item['type'] === 'contact')
            <strong>{{ $item['sender_name'] }}</strong>
            <small>{{ $item['sender_email'] }}</small>
        @elseif ($item['type'] === 'notification')
            <strong>Admin</strong>
        @else
            <span>—</span>
        @endif
    </td>
    <td class="admin-table__actions">
        <x-admin.toolbar class="admin-row-actions admin-row-actions--canonical admin-dashboard__feed-actions">
            <button
                class="admin-action admin-action--with-icon"
                type="button"
                wire:click="openFeedEntry('{{ $item['key'] }}')"
                aria-label="Open {{ $item['type_label'] }} entry {{ $item['title'] }}"
                title="Open"
            >
                <x-filament::icon :icon="\App\Filament\Support\AdminIcon::OpenEntry->mini()" class="admin-action__icon" />
                <span class="admin-action__label">Open</span>
            </button>

            <button
                @class([
                    'admin-action',
                    'admin-action--with-icon',
                    'admin-dashboard__pin-action',
                    'is-active' => $pinned,
                ])
                type="button"
                wire:click="toggleFeedPin('{{ $item['key'] }}')"
                aria-label="{{ $pinned ? 'Unpin' : 'Pin' }} {{ $item['type_label'] }} entry {{ $item['title'] }}"
                title="{{ $pinned ? 'Unpin' : 'Pin' }}"
            >
                <x-filament::icon
                    :icon="($pinned ? \App\Filament\Support\AdminIcon::Pinned : \App\Filament\Support\AdminIcon::Pin)->mini()"
                    class="admin-action__icon"
                />
                <span class="admin-action__label">{{ $pinned ? 'Unpin' : 'Pin' }}</span>
            </button>

            @if ($mutable)
                <button
                    class="admin-action admin-action--with-icon"
                    type="button"
                    wire:click="{{ $read ? 'markFeedUnread' : 'markFeedRead' }}('{{ $item['key'] }}')"
                    aria-label="{{ $read ? 'Mark unread' : 'Mark read' }}"
                    title="{{ $read ? 'Mark unread' : 'Mark read' }}"
                >
                    <x-filament::icon
                        :icon="$read ? \App\Filament\Support\AdminIcon::MarkUnread->mini() : \App\Filament\Support\AdminIcon::MarkRead->mini()"
                        class="admin-action__icon"
                    />
                    <span class="admin-action__label">{{ $read ? 'Unread' : 'Read' }}</span>
                </button>

                @if ($deleteWithoutConfirmation)
                    <button
                        class="admin-action admin-action--with-icon is-danger"
                        type="button"
                        wire:click="deleteFeedEntry('{{ $item['key'] }}')"
                        title="Delete"
                    >
                @else
                    <button
                        class="admin-action admin-action--with-icon is-danger"
                        type="button"
                        wire:click="mountAction('deleteFeedEntry', { key: '{{ $item['key'] }}' })"
                        title="Delete"
                    >
                @endif
                    <x-filament::icon :icon="\App\Filament\Support\AdminIcon::Delete->mini()" class="admin-action__icon" />
                    <span class="admin-action__label">Delete</span>
                </button>
            @else
                <span class="admin-dashboard__action-placeholder" aria-hidden="true"></span>
                <span class="admin-dashboard__action-placeholder" aria-hidden="true"></span>
            @endif
        </x-admin.toolbar>
    </td>
    <td class="admin-table__selection admin-table__selection--trailing">
        <input
            type="checkbox"
            wire:model.live="selectedFeedKeys"
            value="{{ $item['key'] }}"
            aria-label="Select {{ $item['type_label'] }} entry {{ $item['title'] }}"
            @checked($selected)
        >
    </td>
</tr>
