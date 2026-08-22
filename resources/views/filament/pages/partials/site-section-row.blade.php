@php
    $path = $section['public_url'] !== null ? (parse_url($section['public_url'], PHP_URL_PATH) ?: '/') : null;
    $workspaceUrl = $section['workspace_url'] ?: $section['editor_url'];
    $label = $section['navigation_label'] ?: $section['title'];
    $validParents = collect($parentCandidates)->filter(
        fn (array $candidate): bool => in_array($candidate['id'], $section['valid_parent_ids'], true),
    );
@endphp

<article class="admin-site-node" data-depth="{{ $section['depth'] }}" wire:key="site-section-{{ $section['id'] }}">
    <div class="admin-site-node__identity">
        <span class="admin-list__eyebrow">{{ $section['type_label'] }}</span>
        @if ($workspaceUrl)
            <a class="admin-site-node__title" href="{{ $workspaceUrl }}">{{ $label }}</a>
        @else
            <strong class="admin-site-node__title">{{ $label }}</strong>
        @endif
        <span class="admin-site-node__path">{{ $path ?? 'Navigation only' }}</span>
    </div>

    <div class="admin-site-node__placement" aria-label="Placement for {{ $label }}">
        @if ($section['fixed_placement'])
            <span class="admin-site-node__fixed-state">Published · in menu</span>
        @else
            <x-admin.toolbar>
                <button
                    class="admin-action {{ $section['state'] === 'published' ? 'is-primary' : '' }}"
                    type="button"
                    wire:click="toggleSectionState({{ $section['id'] }})"
                    aria-pressed="{{ $section['state'] === 'published' ? 'true' : 'false' }}"
                >{{ $section['state'] === 'published' ? 'Published' : 'Hidden' }}</button>
                <button
                    class="admin-action {{ $section['visible'] ? 'is-primary' : '' }}"
                    type="button"
                    wire:click="toggleSectionNavigation({{ $section['id'] }})"
                    aria-pressed="{{ $section['visible'] ? 'true' : 'false' }}"
                >{{ $section['visible'] ? 'In menu' : 'Off menu' }}</button>
            </x-admin.toolbar>
        @endif

        @if ($section['can_choose_parent'])
            <label class="admin-site-node__parent">
                <span>Parent</span>
                <select
                    aria-label="Parent section for {{ $label }}"
                    wire:change="moveSectionParent({{ $section['id'] }}, $event.target.value)"
                >
                    <option value="" @selected($section['parent_id'] === null)>Top level</option>
                    @foreach ($validParents as $parent)
                        <option value="{{ $parent['id'] }}" @selected($section['parent_id'] === $parent['id'])>{{ $parent['label'] }}</option>
                    @endforeach
                </select>
            </label>
        @endif
    </div>

    <x-admin.toolbar class="admin-site-node__actions">
        @if ($section['editor_url'] && $section['editor_url'] !== $workspaceUrl)
            <a class="admin-action" href="{{ $section['editor_url'] }}">Settings</a>
        @endif
        @if ($section['can_delete'])
            <button
                class="admin-action"
                type="button"
                wire:click="deleteSection({{ $section['id'] }})"
                wire:confirm="Delete this page or navigation node? Published pages, menu entries, parents with children and Journals with entries must be emptied or hidden first."
            >Delete</button>
        @endif
        <span class="admin-toolbar" aria-label="Reorder {{ $label }}">
            <button class="admin-action" type="button" wire:click="moveSection({{ $section['id'] }}, 'up')" aria-label="Move {{ $label }} earlier" @disabled(! $section['can_move_up'])>↑</button>
            <button class="admin-action" type="button" wire:click="moveSection({{ $section['id'] }}, 'down')" aria-label="Move {{ $label }} later" @disabled(! $section['can_move_down'])>↓</button>
        </span>
    </x-admin.toolbar>
</article>
