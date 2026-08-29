@php
    $rows = $generalPage->socialVisibleRows();
    $links = $get('social_links');
    $links = is_array($links) ? array_values($links) : [];
    $platformOptions = \App\Domain\Content\SocialLinks::options();
    $visibilityOptions = \App\Filament\Support\AdminBooleanControl::options('Visible', 'Hidden');
    $selectedIndexes = collect($generalPage->selectedSocialLinkIndexes)
        ->filter(static fn (mixed $index): bool => is_numeric($index))
        ->map(static fn (mixed $index): int => (int) $index)
        ->values()
        ->all();
    $visibleIndexes = collect($rows)->pluck('index')->map(static fn (mixed $index): int => (int) $index)->all();
    $dragEnabled = $generalPage->canDragSortSocialLinks();
@endphp

<x-admin.controls class="general-social-controls" aria-label="Social links controls">
    <x-slot:search>
        <label class="admin-field admin-control-bar__search">
            <span class="admin-field__label">Search</span>
            <input
                type="search"
                wire:model.live.debounce.300ms="socialSearch"
                placeholder="Platform or Profile URL"
                autocomplete="off"
            >
        </label>
    </x-slot:search>

    <x-slot:filters>
        <label class="admin-field">
            <span class="admin-field__label">Visibility</span>
            <select wire:model.live="socialVisibility">
                <option value="any">Any</option>
                <option value="visible">Visible</option>
                <option value="hidden">Hidden</option>
            </select>
        </label>
    </x-slot:filters>

    <x-slot:reset>
        <div class="admin-control-group">
            <span class="admin-control-group__label">Filter</span>
            <div class="admin-control-group__actions">
                <button class="admin-action" type="button" wire:click="resetSocialFilters">Reset</button>
            </div>
        </div>
    </x-slot:reset>

    <x-slot:actions>
        <div class="admin-control-group">
            <span class="admin-control-group__label">Social links</span>
            <div class="admin-control-group__actions">
                <button class="admin-action" type="button" wire:click="addSocialLink">Add social link</button>
            </div>
        </div>
    </x-slot:actions>

    <x-slot:selection>
        <div class="admin-control-group admin-selection" x-data="{ open: false }" x-on:keydown.escape.window="open = false">
            <span class="admin-control-group__label">Selection</span>
            <div class="admin-selection__anchor">
                <button
                    class="admin-action admin-selection__trigger"
                    type="button"
                    x-on:click="open = ! open"
                    x-bind:aria-expanded="open.toString()"
                    aria-haspopup="menu"
                    @disabled($selectedIndexes === [])
                >
                    Selected social links <span class="admin-selection__count">{{ count($selectedIndexes) }}</span>
                </button>
                <div class="admin-selection__menu" role="menu" x-show="open" x-cloak x-on:click.outside="open = false">
                    <button class="admin-action is-danger" type="button" role="menuitem" wire:click="deleteSelectedSocialLinks" x-on:click="open = false">Bulk Delete</button>
                </div>
            </div>
        </div>
    </x-slot:selection>
</x-admin.controls>

<x-admin.table class="admin-table--data" aria-label="Social links">
    <table>
        <thead>
            <tr>
                <th scope="col" class="admin-table__selection">
                    <input
                        type="checkbox"
                        x-data="{}"
                        wire:click.prevent="toggleVisibleSocialSelection"
                        x-effect="const visibleIndexes = @js($visibleIndexes); const selectedIndexes = $wire.selectedSocialLinkIndexes.map(Number); const count = visibleIndexes.filter((index) => selectedIndexes.includes(index)).length; $el.checked = visibleIndexes.length > 0 && count === visibleIndexes.length; $el.indeterminate = count > 0 && count < visibleIndexes.length; $el.setAttribute('aria-checked', $el.indeterminate ? 'mixed' : ($el.checked ? 'true' : 'false'));"
                        @disabled($visibleIndexes === [])
                        aria-label="Toggle selection for visible social links"
                    >
                </th>
                <th scope="col" class="admin-table__drag">Drag</th>
                <th scope="col" class="admin-table__position">Position</th>
                <th scope="col">Platform</th>
                <th scope="col">Profile URL</th>
                <th scope="col">Visibility</th>
                <th scope="col" class="admin-table__actions">Actions</th>
            </tr>
        </thead>
        <tbody @if ($dragEnabled) wire:sort="sortSocialLink" @endif>
            @forelse ($rows as $row)
                @php
                    $index = (int) $row['index'];
                    $link = $row['link'];
                    $platform = (string) ($link['platform'] ?? '');
                    $url = (string) ($link['url'] ?? '');
                @endphp
                <tr
                    class="{{ in_array($index, $selectedIndexes, true) ? 'is-selected' : '' }}"
                    wire:key="general-social-link-{{ $index }}-{{ $platform }}"
                    @if ($dragEnabled) wire:sort:item="{{ $index }}" @endif
                >
                    <td class="admin-table__selection">
                        <input
                            type="checkbox"
                            wire:click="toggleSocialSelection({{ $index }})"
                            @checked(in_array($index, $selectedIndexes, true))
                            aria-label="Toggle selection for social link {{ $index + 1 }}"
                        >
                    </td>
                    <td class="admin-table__drag">
                        <button
                            class="admin-action admin-order-action"
                            type="button"
                            @if ($dragEnabled) wire:sort:handle title="Drag to reorder" @else disabled title="Drag reorder is available only with no search and Visibility set to Any" @endif
                            aria-label="Drag social link {{ $index + 1 }} to reorder"
                        >⠿</button>
                    </td>
                    <td class="admin-table__position"><span class="admin-position">{{ $index + 1 }}</span></td>
                    <td>
                        <select
                            class="admin-form-control"
                            aria-label="Platform for social link {{ $index + 1 }}"
                            wire:change="updateSocialLink({{ $index }}, 'platform', $event.target.value)"
                        >
                            <option value="">Choose platform</option>
                            @foreach ($platformOptions as $value => $label)
                                <option value="{{ $value }}" @selected($platform === $value)>{{ $label }}</option>
                            @endforeach
                        </select>
                        @error("data.social_links.$index.platform")<p class="admin-form-error">{{ $message }}</p>@enderror
                    </td>
                    <td>
                        <input
                            class="admin-form-control"
                            type="url"
                            value="{{ $url }}"
                            maxlength="2048"
                            placeholder="https://…"
                            aria-label="Profile URL for social link {{ $index + 1 }}"
                            x-on:keydown.enter.prevent="$event.target.blur()"
                            wire:blur="updateSocialLink({{ $index }}, 'url', $event.target.value)"
                        >
                        @error("data.social_links.$index.url")<p class="admin-form-error">{{ $message }}</p>@enderror
                    </td>
                    <td>
                        <select
                            class="admin-form-control admin-boolean-control"
                            aria-label="Visibility for social link {{ $index + 1 }}"
                            wire:change="updateSocialLink({{ $index }}, 'visible', $event.target.value)"
                        >
                            @foreach ($visibilityOptions as $value => $label)
                                <option value="{{ $value }}" @selected((bool) ($link['visible'] ?? true) === ($value === '1'))>{{ $label }}</option>
                            @endforeach
                        </select>
                        @error("data.social_links.$index.visible")<p class="admin-form-error">{{ $message }}</p>@enderror
                    </td>
                    <td class="admin-table__actions">
                        <x-admin.toolbar>
                            <button class="admin-action admin-order-action" type="button" wire:click="moveSocialLink({{ $index }}, 'up')" @disabled($index === 0) aria-label="Move social link {{ $index + 1 }} up">↑</button>
                            <button class="admin-action admin-order-action" type="button" wire:click="moveSocialLink({{ $index }}, 'down')" @disabled($index === count($links) - 1) aria-label="Move social link {{ $index + 1 }} down">↓</button>
                            <button class="admin-action is-danger" type="button" wire:click="deleteSocialLink({{ $index }})">Delete</button>
                        </x-admin.toolbar>
                    </td>
                </tr>
            @empty
                <tr>
                    <td colspan="7">No social links configured.</td>
                </tr>
            @endforelse
        </tbody>
    </table>
</x-admin.table>

<div class="admin-control-bar">
    <div class="admin-control-group">
        <div class="admin-control-group__actions">
            <button class="admin-action" type="button" wire:click="addSocialLink">Add social link</button>
        </div>
    </div>
</div>
