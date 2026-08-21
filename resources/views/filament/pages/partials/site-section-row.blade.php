@php
    $path = $section['public_url'] !== null ? (parse_url($section['public_url'], PHP_URL_PATH) ?: '/') : null;
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

<article class="artist-section" data-depth="{{ $section['depth'] }}" wire:key="site-section-{{ $section['id'] }}">
    <div class="artist-section__identity">
        <span class="artist-section__type">{{ $section['type_label'] }}</span>
        <strong>{{ $section['navigation_label'] ?: $section['title'] }}</strong>
        @if ($path !== null)
            <span class="artist-section__path">{{ $path }}</span>
        @else
            <span class="artist-section__path">Navigation only · no public page</span>
        @endif
    </div>

    <div class="artist-section__state" aria-label="Publication status">
        <span class="{{ $section['state'] === 'published' ? 'is-published' : '' }}">{{ $section['state'] === 'published' ? 'Published' : 'Hidden / draft' }}</span>
        @if ($section['type'] !== 'home')
            <span class="{{ $section['visible'] ? 'is-visible' : '' }}">{{ $section['visible'] ? 'In navigation' : 'Not in navigation' }}</span>
        @endif
    </div>

    <div class="artist-section__count"><strong>{{ $section['count'] }}</strong><span>{{ $section['count_label'] }}</span></div>

    <div class="artist-section__actions">
        @if ($section['type'] !== 'home')
            <button class="artist-action" type="button" wire:click="toggleSectionState({{ $section['id'] }})">
                {{ $section['state'] === 'published' ? 'Hide' : 'Publish' }}
            </button>
            <button class="artist-action" type="button" wire:click="toggleSectionNavigation({{ $section['id'] }})">
                {{ $section['visible'] ? 'Remove menu' : 'Add menu' }}
            </button>
            <select
                class="artist-action"
                aria-label="Parent section for {{ $section['navigation_label'] ?: $section['title'] }}"
                wire:change="moveSectionParent({{ $section['id'] }}, $event.target.value)"
                @disabled($section['has_children'])
            >
                <option value="" @selected($section['parent_id'] === null)>Top level</option>
                @foreach ($validParents as $parent)
                    <option value="{{ $parent['id'] }}" @selected($section['parent_id'] === $parent['id'])>{{ $parent['label'] }}</option>
                @endforeach
            </select>
        @endif

        @if ($section['content_url'])
            <a class="artist-action is-primary" href="{{ $section['content_url'] }}">Content</a>
        @endif
        @if ($section['editor_url'])
            <a class="artist-action" href="{{ $section['editor_url'] }}">Settings</a>
        @endif
        @if ($section['preview_url'])
            <a class="artist-action" href="{{ $section['preview_url'] }}" target="_blank" rel="noopener">Preview</a>
        @endif
        @if ($section['public_url'] && $section['state'] === 'published')
            <a class="artist-action" href="{{ $section['public_url'] }}" target="_blank" rel="noopener">View live</a>
        @endif
        <span class="artist-section__order" aria-label="Reorder {{ $section['navigation_label'] ?: $section['title'] }}">
            <button class="artist-action" type="button" wire:click="moveSection({{ $section['id'] }}, 'up')" aria-label="Move {{ $section['navigation_label'] ?: $section['title'] }} earlier" @disabled(! $section['can_move_up'])>↑</button>
            <button class="artist-action" type="button" wire:click="moveSection({{ $section['id'] }}, 'down')" aria-label="Move {{ $section['navigation_label'] ?: $section['title'] }} later" @disabled(! $section['can_move_down'])>↓</button>
        </span>
    </div>
</article>
