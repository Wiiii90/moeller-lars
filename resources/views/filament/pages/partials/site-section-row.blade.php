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

    <div class="artist-page-row__placement">
        <div class="artist-section__state" aria-label="Publication and navigation status">
            <span class="{{ $section['state'] === 'published' ? 'is-published' : '' }}">{{ $section['state'] === 'published' ? 'Published' : 'Hidden' }}</span>
            @if ($section['type'] !== 'home')
                <span class="{{ $section['visible'] ? 'is-visible' : '' }}">{{ $section['visible'] ? 'In menu' : 'Off menu' }}</span>
            @endif
        </div>

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

    <div class="artist-page-row__count">
        <strong>{{ $section['count'] }}</strong>
        <span>{{ $section['count_label'] }}</span>
    </div>

    <div class="artist-page-row__actions">
        @if ($section['type'] !== 'home')
            <button class="artist-action" type="button" wire:click="toggleSectionState({{ $section['id'] }})">
                {{ $section['state'] === 'published' ? 'Hide' : 'Publish' }}
            </button>
            <button class="artist-action" type="button" wire:click="toggleSectionNavigation({{ $section['id'] }})">
                {{ $section['visible'] ? 'Remove menu' : 'Add menu' }}
            </button>
        @endif
        @if ($section['editor_url'] && $section['editor_url'] !== $workspaceUrl)
            <a class="artist-action" href="{{ $section['editor_url'] }}">Settings</a>
        @endif
        @if ($section['preview_url'])
            <a class="artist-action" href="{{ $section['preview_url'] }}" target="_blank" rel="noopener">Preview</a>
        @endif
        @if ($section['public_url'] && $section['state'] === 'published')
            <a class="artist-action" href="{{ $section['public_url'] }}" target="_blank" rel="noopener">Live</a>
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
