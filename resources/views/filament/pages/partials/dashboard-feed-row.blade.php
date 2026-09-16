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
            <x-admin.row-action
                :action="\App\Filament\Support\AdminRowAction::Open"
                wire:click="openFeedEntry('{{ $item['key'] }}')"
                aria-label="Open {{ $item['type_label'] }} entry {{ $item['title'] }}"
                title="Open"
            />

            <x-admin.row-action
                :action="$pinned ? \App\Filament\Support\AdminRowAction::Unpin : \App\Filament\Support\AdminRowAction::Pin"
                class="admin-dashboard__pin-action {{ $pinned ? 'is-active' : '' }}"
                wire:click="toggleFeedPin('{{ $item['key'] }}')"
                aria-label="{{ $pinned ? 'Unpin' : 'Pin' }} {{ $item['type_label'] }} entry {{ $item['title'] }}"
                title="{{ $pinned ? 'Unpin' : 'Pin' }}"
            />

            @if ($mutable)
                <x-admin.row-action
                    :action="$read ? \App\Filament\Support\AdminRowAction::MarkUnread : \App\Filament\Support\AdminRowAction::MarkRead"
                    wire:click="{{ $read ? 'markFeedUnread' : 'markFeedRead' }}('{{ $item['key'] }}')"
                    aria-label="{{ $read ? 'Mark unread' : 'Mark read' }}"
                    title="{{ $read ? 'Mark unread' : 'Mark read' }}"
                />

                @if ($deleteWithoutConfirmation)
                    <x-admin.row-action
                        :action="\App\Filament\Support\AdminRowAction::Delete"
                        wire:click="deleteFeedEntry('{{ $item['key'] }}')"
                        title="Delete"
                    />
                @else
                    <x-admin.row-action
                        :action="\App\Filament\Support\AdminRowAction::Delete"
                        wire:click="mountAction('deleteFeedEntry', { key: '{{ $item['key'] }}' })"
                        title="Delete"
                    />
                @endif
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
