@php
    $rows = $generalPage->socialRows();
    $links = $get('social_links');
    $links = is_array($links) ? array_values($links) : [];
    $platformOptions = \App\Domain\Content\SocialLinks::options();
@endphp

<section class="general-social-section" aria-labelledby="general-social-heading">
    <h2 id="general-social-heading" class="admin-section__kicker general-social-section__heading">Social Media</h2>

    <x-admin.table class="admin-table--data general-social-table" aria-label="Social media profiles">
        <table>
            <colgroup>
                <col class="general-social-table__col-order">
                <col class="general-social-table__col-platform">
                <col class="general-social-table__col-url">
                <col class="general-social-table__col-actions">
            </colgroup>
            <thead>
                <tr>
                    <th scope="col">Order</th>
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
                        x-data="{
                            editPlatform: @js($platform === ''),
                            editUrl: @js($url === ''),
                        }"
                    >
                        <td class="general-social-table__order">
                            <div class="general-social-table__order-cell">
                                <button
                                    class="admin-drag-handle"
                                    type="button"
                                    wire:sort:handle
                                    title="Drag to reorder"
                                    aria-label="Drag social link {{ $index + 1 }} to reorder"
                                >⋮⋮</button>
                                <span class="admin-position">{{ $index + 1 }}</span>
                            </div>
                        </td>
                        <td class="general-social-table__platform">
                            <button
                                class="general-social-table__editable"
                                type="button"
                                x-show="! editPlatform"
                                x-on:click="editPlatform = true; $nextTick(() => $refs.platform?.focus())"
                                title="Edit platform"
                            >{{ $platformLabel }}</button>
                            <select
                                x-ref="platform"
                                x-show="editPlatform"
                                x-cloak
                                class="admin-form-control general-social-table__editor"
                                aria-label="Platform for social link {{ $index + 1 }}"
                                wire:change="updateSocialLink({{ $index }}, 'platform', $event.target.value)"
                                x-on:change="editPlatform = false"
                                x-on:keydown.escape.prevent="editPlatform = false"
                            >
                                <option value="">Choose platform</option>
                                @foreach ($platformOptions as $value => $label)
                                    <option value="{{ $value }}" @selected($platform === $value)>{{ $label }}</option>
                                @endforeach
                            </select>
                            @error("data.social_links.$index.platform")<p class="admin-form-error">{{ $message }}</p>@enderror
                        </td>
                        <td class="general-social-table__url">
                            <button
                                class="general-social-table__editable general-social-table__editable--url"
                                type="button"
                                x-show="! editUrl"
                                x-on:click="editUrl = true; $nextTick(() => $refs.url?.focus())"
                                title="Edit profile URL"
                            >{{ $url !== '' ? $url : 'Add profile URL' }}</button>
                            <input
                                x-ref="url"
                                x-show="editUrl"
                                x-cloak
                                class="admin-form-control general-social-table__editor"
                                type="url"
                                value="{{ $url }}"
                                maxlength="2048"
                                placeholder="https://…"
                                aria-label="Profile URL for social link {{ $index + 1 }}"
                                x-on:keydown.enter.prevent="$event.target.blur()"
                                x-on:keydown.escape.prevent="editUrl = false"
                                x-on:blur="editUrl = false"
                                wire:blur="updateSocialLink({{ $index }}, 'url', $event.target.value)"
                            >
                            @error("data.social_links.$index.url")<p class="admin-form-error">{{ $message }}</p>@enderror
                        </td>
                        <td class="admin-table__actions general-social-table__actions">
                            <x-admin.toolbar>
                                <button class="admin-action admin-order-action" type="button" wire:click="moveSocialLink({{ $index }}, 'up')" @disabled($index === 0) aria-label="Move social link {{ $index + 1 }} up">↑</button>
                                <button class="admin-action admin-order-action" type="button" wire:click="moveSocialLink({{ $index }}, 'down')" @disabled($index === count($links) - 1) aria-label="Move social link {{ $index + 1 }} down">↓</button>
                                <button
                                    class="admin-action general-social-table__edit-action"
                                    type="button"
                                    wire:click="mountAction('editSocialLink', { index: {{ $index }} })"
                                >Edit</button>
                                <button class="admin-action is-danger" type="button" wire:click="deleteSocialLink({{ $index }})">Delete</button>
                            </x-admin.toolbar>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td class="admin-table__empty-cell" colspan="4">
                            <x-admin.empty-state title="No social media profiles configured" minimal />
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </x-admin.table>

    <x-admin.add-row wire:click="addSocialLink">Add social link</x-admin.add-row>
</section>
