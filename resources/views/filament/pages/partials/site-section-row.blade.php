@php
    $label = $section['navigation_label'] ?: $section['title'];
    $selected = in_array((int) $section['id'], array_map('intval', $selectedSectionIds), true);
@endphp

<div class="pages-row" role="row" data-depth="{{ $section['depth'] }}">
    <div role="cell" data-cell="selection">
        <input
            type="checkbox"
            aria-label="Select {{ $label }}"
            value="{{ $section['id'] }}"
            wire:model.live="selectedSectionIds"
            @checked($selected)
        >
    </div>

    <div role="cell" data-cell="drag">
        @if ($reorderEnabled)
            <button class="pages-drag-handle" type="button" wire:sort:handle aria-label="Drag {{ $label }} to a new position">⋮⋮</button>
        @else
            <span class="pages-drag-placeholder" aria-hidden="true">—</span>
        @endif
    </div>

    <div role="cell" data-cell="position">
        <span class="pages-position-box" title="Stored position {{ $section['position'] }}">{{ $section['position_label'] }}</span>
    </div>

    <div role="cell" data-cell="page-type">
        @if ($section['can_convert'])
            <select
                class="pages-type-select"
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

    <div class="pages-page-cell" role="cell" data-cell="page">
        <strong>{{ $label }}</strong>
        @if ($section['parent_label'] !== null)
            <small>Under {{ $section['parent_label'] }}</small>
        @endif
    </div>

    <div role="cell" data-cell="template">
        @if ($section['type'] === \App\Domain\Content\SiteNodeType::Journal->value)
            <select
                class="pages-template-select"
                aria-label="Journal template for {{ $label }}"
                wire:change="changeJournalTemplate({{ $section['id'] }}, $event.target.value)"
            >
                @foreach ($journalTemplateOptions as $value => $templateLabel)
                    <option value="{{ $value }}" @selected($section['template'] === $value)>{{ $templateLabel }}</option>
                @endforeach
            </select>
        @else
            <span class="pages-muted">—</span>
        @endif
    </div>

    <div role="cell" data-cell="status">
        {{ $section['state'] === 'published' ? 'Published' : 'Unpublished' }}
    </div>

    <div role="cell" data-cell="navigation">
        {{ $section['visible'] ? 'In menu' : 'Off menu' }}
    </div>

    <div class="pages-actions" role="cell" data-cell="actions" aria-label="Actions for {{ $label }}">
        <div class="pages-actions__slot">
            @if ($section['workspace_url'])
                <a class="pages-action-link" href="{{ $section['workspace_url'] }}">Edit</a>
            @endif
        </div>

        <div class="pages-actions__slot">
            @if ($section['can_change_publication'])
                <button class="pages-action-button" type="button" wire:click="toggleSectionState({{ $section['id'] }})">
                    {{ $section['state'] === 'published' ? 'Unpublish' : 'Publish' }}
                </button>
            @endif
        </div>

        <div class="pages-actions__slot">
            <button
                class="pages-action-button"
                type="button"
                wire:click="toggleSectionNavigation({{ $section['id'] }})"
                @disabled(! $section['can_toggle_navigation'])
                title="{{ $section['can_toggle_navigation'] ? 'Toggle navigation visibility' : 'Add a navigation label before showing this page in the menu' }}"
            >{{ $section['visible'] ? 'Remove' : 'Add' }}</button>
        </div>

        <div class="pages-actions__slot">
            <button
                class="pages-action-button"
                type="button"
                wire:click="moveSection({{ $section['id'] }}, 'up')"
                aria-label="Move {{ $label }} earlier"
                @disabled(! $reorderEnabled || ! $section['can_move_up'])
            >↑</button>
        </div>

        <div class="pages-actions__slot">
            <button
                class="pages-action-button"
                type="button"
                wire:click="moveSection({{ $section['id'] }}, 'down')"
                aria-label="Move {{ $label }} later"
                @disabled(! $reorderEnabled || ! $section['can_move_down'])
            >↓</button>
        </div>

        <div class="pages-actions__slot">
            @if ($section['can_delete'])
                <button
                    class="pages-action-button"
                    type="button"
                    wire:click="deleteSection({{ $section['id'] }})"
                    wire:confirm="Delete this page? Page-specific content, child pages, publication and navigation safety rules still apply."
                >Delete</button>
            @endif
        </div>
    </div>
</div>
