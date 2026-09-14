@php
    $rows = $generalPage->socialRows();
    $links = $get('social_links');
    $links = is_array($links) ? array_values($links) : [];
@endphp

<section class="general-social-section" aria-labelledby="general-social-heading">
    <h2 id="general-social-heading" class="admin-section__kicker general-social-section__heading">Social Media</h2>

    <x-admin.table class="admin-table--data admin-table--ranked general-social-table" aria-label="Social media profiles">
        <table class="admin-table--six-grid">
            <colgroup>
                <col class="admin-table__col-quarter-unit">
                <col class="admin-table__col-quarter-unit">
                <col class="admin-table__col-one-half-units">
                <col class="admin-table__col-two-units">
                <col class="admin-table__col-two-units">
            </colgroup>
            <thead>
                <tr>
                    <th scope="colgroup" colspan="2" class="admin-table__ordering-heading">Position</th>
                    <th scope="col">Platform</th>
                    <th scope="col">Profile URL</th>
                    <th scope="col" class="admin-table__actions">Actions</th>
                </tr>
            </thead>
            <tbody wire:sort="sortSocialLink">
                @forelse ($rows as $row)
                    @php
                        $index = (int) $row['index'];
                        $link = $row['link'];
                        $platform = (string) ($link['platform'] ?? '');
                        $url = (string) ($link['url'] ?? '');
                        $platformLabel = $platform !== ''
                            ? \App\Domain\Content\SocialLinks::label($platform)
                            : 'Choose platform';
                    @endphp
                    <tr
                        wire:key="general-social-link-{{ $index }}"
                        wire:sort:item="{{ $index }}"
                    >
                        <td class="admin-table__position">
                            <span class="admin-position">{{ $index + 1 }}</span>
                        </td>
                        <td class="admin-table__drag">
                            <button
                                class="admin-drag-handle"
                                type="button"
                                wire:sort:handle
                                title="Drag to reorder"
                                aria-label="Drag social link {{ $index + 1 }} to reorder"
                            >⋮⋮</button>
                        </td>
                        <td class="general-social-table__platform">
                            {{ $platformLabel }}
                        </td>
                        <td class="general-social-table__url" title="{{ $url }}">
                            {{ $url }}
                        </td>
                        <td class="admin-table__actions">
                            <x-admin.toolbar class="admin-row-actions admin-row-actions--canonical admin-row-actions--four">
                                <button
                                    class="admin-action admin-action--with-icon admin-order-action admin-order-action--labeled"
                                    type="button"
                                    wire:click="moveSocialLink({{ $index }}, 'up')"
                                    @disabled($index === 0)
                                    aria-label="Move social link {{ $index + 1 }} up"
                                >
                                    <x-filament::icon :icon="\App\Filament\Support\AdminIcon::MoveUp->mini()" class="admin-action__icon" />
                                    <span class="admin-action__label">Move up</span>
                                </button>
                                <button
                                    class="admin-action admin-action--with-icon admin-order-action admin-order-action--labeled"
                                    type="button"
                                    wire:click="moveSocialLink({{ $index }}, 'down')"
                                    @disabled($index === count($links) - 1)
                                    aria-label="Move social link {{ $index + 1 }} down"
                                >
                                    <x-filament::icon :icon="\App\Filament\Support\AdminIcon::MoveDown->mini()" class="admin-action__icon" />
                                    <span class="admin-action__label">Move down</span>
                                </button>
                                <button
                                    class="admin-action admin-action--with-icon"
                                    type="button"
                                    wire:click="mountAction('editSocialLink', { index: {{ $index }} })"
                                    aria-label="Edit social link {{ $index + 1 }}"
                                >
                                    <x-filament::icon :icon="\App\Filament\Support\AdminIcon::Edit->mini()" class="admin-action__icon" />
                                    <span class="admin-action__label">Edit</span>
                                </button>
                                <button
                                    class="admin-action admin-action--with-icon is-danger"
                                    type="button"
                                    wire:click="deleteSocialLink({{ $index }})"
                                    aria-label="Delete social link {{ $index + 1 }}"
                                >
                                    <x-filament::icon :icon="\App\Filament\Support\AdminIcon::Delete->mini()" class="admin-action__icon" />
                                    <span class="admin-action__label">Delete</span>
                                </button>
                            </x-admin.toolbar>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td class="admin-table__empty-cell" colspan="5">
                            <x-admin.empty-state title="No social media profiles configured" minimal />
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </x-admin.table>

    <x-admin.add-row wire:click="mountAction('addSocialLink')">Add social link</x-admin.add-row>
</section>
