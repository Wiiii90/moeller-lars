@php
    $rows = $generalPage->socialRows();
    $links = $get('social_links');
    $links = is_array($links) ? array_values($links) : [];
    $platformOptions = \App\Domain\Content\SocialLinks::options();
    $usedPlatforms = collect($links)
        ->pluck('platform')
        ->filter(fn (mixed $platform): bool => is_string($platform) && $platform !== '')
        ->values()
        ->all();
@endphp

<section class="general-social-section" aria-labelledby="general-social-heading">
    <h2 id="general-social-heading" class="admin-section__kicker general-social-section__heading">Social Media</h2>

    <x-admin.table class="admin-table--data admin-table--ranked general-social-table" aria-label="Social media profiles">
        <table class="admin-table--six-grid">
            <colgroup>
                <col class="admin-table__col-quarter-unit admin-table__col-position">
                <col class="admin-table__col-quarter-unit admin-table__col-drag">
                <col class="admin-table__col-one-half-units general-social-table__col-platform">
                <col class="admin-table__col-two-units general-social-table__col-url">
                <col class="admin-table__col-two-units general-social-table__col-actions">
            </colgroup>
            <thead>
                <tr>
                    <th scope="colgroup" colspan="2" class="admin-table__ordering-heading general-social-table__position-head">Position</th>
                    <th scope="col" class="general-social-table__platform-head">Platform</th>
                    <th scope="col" class="general-social-table__url-head">Profile URL</th>
                    <th scope="col" class="admin-table__actions"><span class="admin-row-actions-heading">Actions</span></th>
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
                        <td class="admin-table__position general-social-table__position-cell">
                            <span class="admin-position">{{ $index + 1 }}</span>
                        </td>
                        <td class="admin-table__drag general-social-table__drag-cell">
                            <button
                                class="admin-drag-handle"
                                type="button"
                                wire:sort:handle
                                title="Drag to reorder"
                                aria-label="Drag social link {{ $index + 1 }} to reorder"
                            >⋮⋮</button>
                        </td>
                        <td class="general-social-table__platform">
                            <select
                                class="admin-inline-select"
                                aria-label="Platform for social link {{ $index + 1 }}"
                                wire:change="updateSocialLink({{ $index }}, 'platform', $event.target.value)"
                            >
                                <option value="">Choose platform</option>
                                @foreach ($platformOptions as $value => $label)
                                    <option
                                        value="{{ $value }}"
                                        @selected($platform === $value)
                                        @disabled($platform !== $value && in_array($value, $usedPlatforms, true))
                                    >{{ $label }}</option>
                                @endforeach
                            </select>
                        </td>
                        <td class="general-social-table__url">
                            <input
                                class="admin-inline-input"
                                type="url"
                                value="{{ $url }}"
                                placeholder="https://"
                                aria-label="Profile URL for {{ $platformLabel }}"
                                wire:change="updateSocialLink({{ $index }}, 'url', $event.target.value)"
                                x-on:keydown.enter.prevent="$el.blur()"
                            >
                        </td>
                        <td class="admin-table__actions general-social-table__actions">
                            <x-admin.toolbar class="admin-row-actions admin-row-actions--canonical admin-row-actions--stable admin-row-actions--move-delete">
                                <x-admin.row-action
                                    :action="\App\Filament\Support\AdminRowAction::MoveUp"
                                    wire:click="moveSocialLink({{ $index }}, 'up')"
                                    :disabled="$index === 0"
                                    aria-label="Move social link {{ $index + 1 }} up"
                                />
                                <x-admin.row-action
                                    :action="\App\Filament\Support\AdminRowAction::MoveDown"
                                    wire:click="moveSocialLink({{ $index }}, 'down')"
                                    :disabled="$index === count($links) - 1"
                                    aria-label="Move social link {{ $index + 1 }} down"
                                />
                                <x-admin.row-action
                                    :action="\App\Filament\Support\AdminRowAction::Delete"
                                    wire:click="deleteSocialLink({{ $index }})"
                                    aria-label="Delete social link {{ $index + 1 }}"
                                />
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
