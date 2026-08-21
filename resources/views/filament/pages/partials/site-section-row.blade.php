@php
    $path = $section['public_url'] !== null ? (parse_url($section['public_url'], PHP_URL_PATH) ?: '/') : null;
    $workspaceUrl = $section['content_url'] ?: $section['editor_url'];
    $label = $section['navigation_label'] ?: $section['title'];
    $validParents = collect($parentCandidates)->filter(function (array $candidate) use ($section): bool {
        if ($candidate['id'] === $section['id'] || $section['type'] === 'navigation_group') {
            return false;
        }
        if ($section['type'] === 'gallery') {
            return in_array($candidate['type'], ['gallery', 'navigation_group'], true);
        }

        return $candidate['type'] === 'navigation_group';
    });
@endphp

<article id="site-section-{{ $section['id'] }}" class="artist-page-row" data-depth="{{ $section['depth'] }}" wire:key="site-section-{{ $section['id'] }}">
    <div class="artist-page-row__identity">
        <span class="artist-workspace__kicker">{{ $section['type_label'] }}</span>
        @if ($workspaceUrl)
            <a class="artist-page-row__title" href="{{ $workspaceUrl }}">{{ $label }}</a>
        @else
            <strong class="artist-page-row__title">{{ $label }}</strong>
        @endif
        <span class="artist-page-row__path">{{ $path ?? 'Navigation only' }}</span>
    </div>

    <div class="artist-page-row__placement" aria-label="Placement for {{ $label }}">
        @if ($section['type'] === 'home')
            <span class="artist-page-row__fixed-state">Published · in menu</span>
        @else
            <div class="artist-page-row__toggles">
                <button
                    class="artist-placement-toggle {{ $section['state'] === 'published' ? 'is-on' : '' }}"
                    type="button"
                    wire:click="toggleSectionState({{ $section['id'] }})"
                    aria-pressed="{{ $section['state'] === 'published' ? 'true' : 'false' }}"
                >{{ $section['state'] === 'published' ? 'Published' : 'Hidden' }}</button>
                <button
                    class="artist-placement-toggle {{ $section['visible'] ? 'is-on' : '' }}"
                    type="button"
                    wire:click="toggleSectionNavigation({{ $section['id'] }})"
                    aria-pressed="{{ $section['visible'] ? 'true' : 'false' }}"
                >{{ $section['visible'] ? 'In menu' : 'Off menu' }}</button>
            </div>
        @endif

        @if ($section['type'] !== 'home' && $section['type'] !== 'navigation_group')
            <label class="artist-page-row__parent">
                <span>Parent</span>
                <select
                    aria-label="Parent section for {{ $label }}"
                    wire:change="moveSectionParent({{ $section['id'] }}, $event.target.value)"
                    @disabled($section['has_children'])
                >
                    <option value="" @selected($section['parent_id'] === null)>Top level</option>
                    @foreach ($validParents as $parent)
                        <option value="{{ $parent['id'] }}" @selected($section['parent_id'] === $parent['id'])>{{ $parent['label'] }}</option>
                    @endforeach
                </select>
            </label>
        @endif
    </div>

    <div class="artist-page-row__actions">
        @if ($section['editor_url'] && $section['editor_url'] !== $workspaceUrl)
            <a class="artist-action" href="{{ $section['editor_url'] }}">Settings</a>
        @endif
        @if ($section['can_delete'])
            <button
                class="artist-action"
                type="button"
                wire:click="deleteSection({{ $section['id'] }})"
                wire:confirm="Delete this page or navigation node? Published pages, menu entries, parents with children and Journals with entries must be emptied or hidden first."
            >Delete</button>
        @endif
        <span class="artist-section__order" aria-label="Reorder {{ $label }}">
            <button class="artist-action" type="button" wire:click="moveSection({{ $section['id'] }}, 'up')" aria-label="Move {{ $label }} earlier" @disabled(! $section['can_move_up'])>↑</button>
            <button class="artist-action" type="button" wire:click="moveSection({{ $section['id'] }}, 'down')" aria-label="Move {{ $label }} later" @disabled(! $section['can_move_down'])>↓</button>
        </span>
    </div>
</article>
