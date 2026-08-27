@php
    $label = $section['navigation_label'] ?: $section['title'];
    $selected = in_array((int) $section['id'], array_map('intval', $selectedSectionIds), true);
    $isChild = (int) $section['depth'] === 1;
@endphp

<div class="admin-hierarchy__row {{ $isChild ? 'is-child' : '' }}" role="row" data-depth="{{ $section['depth'] }}">
    <label class="admin-hierarchy__selection" role="cell" data-cell="selection">
        <input
            type="checkbox"
            aria-label="Select {{ $label }}"
            value="{{ $section['id'] }}"
            wire:model.live="selectedSectionIds"
            @checked($selected)
        >
    </label>

    <div role="cell" data-cell="drag">
        <button
            class="admin-drag-handle"
            type="button"
            @if ($reorderEnabled) wire:sort:handle @else disabled @endif
            aria-label="Drag {{ $label }} to a new position"
        >⋮⋮</button>
    </div>

    <div role="cell" data-cell="position">
        <span class="admin-position" title="Stored position {{ $section['position'] }}">{{ $section['position_label'] }}</span>
    </div>

    <div role="cell" data-cell="page-type">
        @if ($section['can_convert'])
            <select
                class="admin-inline-select"
                aria-label="Page type for {{ $label }}"
                wire:change="convertSectionType({{ $section['id'] }}, $event.target.value)"
            >
                @foreach ($editableTypeOptions as $value => $typeLabel)
                    <option value="{{ $value }}" @selected($section['type'] === $value)>{{ $typeLabel }}</option>
                @endforeach
            </select>
        @else
            <span>{{ $section['type_label'] }}</span>
        @endif
    </div>

    <div class="admin-hierarchy__content" role="cell" data-cell="page">
        <strong>{{ $label }}</strong>
        @if ($section['parent_label'] !== null)
            <small>Under {{ $section['parent_label'] }}</small>
        @elseif ($section['filter_context'] ?? false)
            <small class="admin-hierarchy__context-note">Parent context for matching child</small>
        @elseif (is_string($section['slug']) && $section['slug'] !== '')
            <small>/{{ $section['slug'] }}</small>
        @endif
    </div>

    <div role="cell" data-cell="template">
        @if ($section['type'] === \App\Domain\Content\SiteNodeType::Journal->value)
            <select
                class="admin-inline-select"
                aria-label="Journal template for {{ $label }}"
                wire:change="changeJournalTemplate({{ $section['id'] }}, $event.target.value)"
            >
                @foreach ($journalTemplateOptions as $value => $templateLabel)
                    <option value="{{ $value }}" @selected($section['template'] === $value)>{{ $templateLabel }}</option>
                @endforeach
            </select>
        @else
            <span aria-hidden="true">—</span>
            <span class="sr-only">No template</span>
        @endif
    </div>

    <div role="cell" data-cell="status">
        <span class="admin-status {{ $section['state'] === 'published' ? 'is-published' : 'is-unpublished' }}">
            {{ $section['state'] === 'published' ? 'Published' : 'Unpublished' }}
        </span>
    </div>

    <div class="admin-row-actions admin-toolbar" role="cell" data-cell="actions" aria-label="Actions for {{ $label }}">
        @if ($section['workspace_url'])
            <a class="admin-action" href="{{ $section['workspace_url'] }}">Edit</a>
        @endif

        @if ($section['can_change_publication'])
            <button class="admin-action admin-action--state" type="button" wire:click="toggleSectionState({{ $section['id'] }})">
                {{ $section['state'] === 'published' ? 'Unpublish' : 'Publish' }}
            </button>
        @endif

        <button
            class="admin-action"
            type="button"
            wire:click="moveSection({{ $section['id'] }}, 'up')"
            aria-label="Move {{ $label }} earlier"
            @disabled(! $reorderEnabled || ! $section['can_move_up'])
        >↑</button>

        <button
            class="admin-action"
            type="button"
            wire:click="moveSection({{ $section['id'] }}, 'down')"
            aria-label="Move {{ $label }} later"
            @disabled(! $reorderEnabled || ! $section['can_move_down'])
        >↓</button>

        @if ($section['can_delete'])
            <button
                class="admin-action is-danger"
                type="button"
                wire:click="deleteSection({{ $section['id'] }})"
                wire:confirm="Delete this page? Page-specific content, child pages, publication and navigation safety rules still apply."
            >Delete</button>
        @endif
    </div>
</div>
