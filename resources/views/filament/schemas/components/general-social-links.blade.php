@php
    $links = $get('social_links');
    $links = is_array($links) ? array_values($links) : [];
    $platformOptions = \App\Domain\Content\SocialLinks::options();
    $visibilityOptions = \App\Filament\Support\AdminBooleanControl::options('Visible', 'Hidden');
@endphp

<x-admin.table class="admin-table--data" aria-label="Social links">
    <table>
        <thead>
            <tr>
                <th scope="col">Platform</th>
                <th scope="col">Profile URL</th>
                <th scope="col">Visibility</th>
                <th scope="col">Actions</th>
            </tr>
        </thead>
        <tbody>
            @forelse ($links as $index => $link)
                <tr wire:key="general-social-link-{{ $index }}">
                    <td>
                        <select
                            class="admin-form-control"
                            aria-label="Platform for social link {{ $index + 1 }}"
                            wire:change="updateSocialLink({{ $index }}, 'platform', $event.target.value)"
                        >
                            <option value="">Choose platform</option>
                            @foreach ($platformOptions as $value => $label)
                                <option value="{{ $value }}" @selected(($link['platform'] ?? '') === $value)>{{ $label }}</option>
                            @endforeach
                        </select>
                        @error("data.social_links.$index.platform")<p class="admin-form-error">{{ $message }}</p>@enderror
                    </td>
                    <td>
                        <input
                            class="admin-form-control"
                            type="url"
                            value="{{ $link['url'] ?? '' }}"
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
                    <td>
                        <div class="admin-toolbar">
                            <button class="admin-action" type="button" wire:click="moveSocialLink({{ $index }}, 'up')" @disabled($index === 0)>Up</button>
                            <button class="admin-action" type="button" wire:click="moveSocialLink({{ $index }}, 'down')" @disabled($index === count($links) - 1)>Down</button>
                            <button class="admin-action is-danger" type="button" wire:click="deleteSocialLink({{ $index }})">Delete</button>
                        </div>
                    </td>
                </tr>
            @empty
                <tr>
                    <td colspan="4">No social links configured.</td>
                </tr>
            @endforelse
        </tbody>
    </table>
</x-admin.table>

<x-admin.add-row wire:click="addSocialLink">Add social link</x-admin.add-row>
