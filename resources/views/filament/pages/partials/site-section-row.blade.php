@php
    $label = $section['navigation_label'] ?: $section['title'];
    $selected = in_array((int) $section['id'], array_map('intval', $selectedSectionIds), true);
    $isChild = (int) $section['depth'] === 1;
    $isHome = $section['type'] === \App\Domain\Content\SiteNodeType::Home->value;
@endphp

<div
    class="admin-hierarchy__row admin-pages__row {{ $isChild ? 'is-child' : '' }}"
    role="row"
    data-depth="{{ $section['depth'] }}"
    data-section-id="{{ $section['id'] }}"
    data-parent-id="{{ $section['parent_id'] ?? '' }}"
    data-has-children="{{ $section['has_children'] ? 'true' : 'false' }}"
>
    <div class="admin-pages__primary-grid" role="presentation">
        <div class="admin-hierarchy__position-cell" role="cell" data-cell="position">
            <span class="admin-position">{{ $section['position_label'] }}</span>
        </div>

        <div class="admin-pages__drag-cell" role="cell" data-cell="drag">
            @if ($section['can_reorder'])
                <button
                    class="admin-drag-handle"
                    type="button"
                    @if ($reorderEnabled) wire:sort:handle @else disabled @endif
                    aria-label="Drag {{ $label }} to a new position"
                    title="Drag to reorder"
                >⋮⋮</button>
            @else
                <span class="admin-pages__drag-placeholder" aria-hidden="true"></span>
            @endif
        </div>

        <div class="admin-hierarchy__content admin-pages__page" role="cell" data-cell="page">
            @if ($section['workspace_url'])
                <a class="admin-pages__page-link" href="{{ $section['workspace_url'] }}"><strong>{{ $label }}</strong></a>
            @else
                <strong>{{ $label }}</strong>
            @endif
        </div>

        <div class="admin-pages__status" role="cell" data-cell="status">
            <span class="admin-status {{ $section['state'] === 'published' ? 'is-published' : 'is-unpublished' }}">
                {{ $section['state'] === 'published' ? 'Published' : 'Unpublished' }}
            </span>
        </div>
    </div>

    <div class="admin-pages__type" role="cell" data-cell="page-type">
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

    <div class="admin-pages__template" role="cell" data-cell="template">
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
        @endif
    </div>

    <div class="admin-pages__utility-grid" role="presentation">
        <div class="admin-row-actions admin-row-actions--canonical admin-toolbar admin-pages__row-actions" role="cell" data-cell="actions" aria-label="Actions for {{ $label }}">
            @if ($section['can_reorder'])
                <button class="admin-action admin-action--with-icon admin-order-action admin-order-action--labeled" type="button" wire:click="moveSection({{ $section['id'] }}, 'up')" @disabled(! $reorderEnabled || ! $section['can_move_up']) aria-label="Move {{ $label }} up">
                    <x-filament::icon :icon="\App\Filament\Support\AdminIcon::MoveUp->mini()" class="admin-action__icon" />
                    <span class="admin-action__label">Move up</span>
                </button>
                <button class="admin-action admin-action--with-icon admin-order-action admin-order-action--labeled" type="button" wire:click="moveSection({{ $section['id'] }}, 'down')" @disabled(! $reorderEnabled || ! $section['can_move_down']) aria-label="Move {{ $label }} down">
                    <x-filament::icon :icon="\App\Filament\Support\AdminIcon::MoveDown->mini()" class="admin-action__icon" />
                    <span class="admin-action__label">Move down</span>
                </button>
            @else
                <span class="admin-pages__action-placeholder" aria-hidden="true"></span>
                <span class="admin-pages__action-placeholder" aria-hidden="true"></span>
            @endif

            @if ($isHome)
                @if ($section['workspace_url'])
                    <a class="admin-action admin-action--with-icon" href="{{ $section['workspace_url'] }}">
                        <x-filament::icon :icon="\App\Filament\Support\AdminIcon::Edit->mini()" class="admin-action__icon" />
                        <span class="admin-action__label">Edit</span>
                    </a>
                @else
                    <span class="admin-pages__action-placeholder" aria-hidden="true"></span>
                @endif
            @else
                <button class="admin-action admin-action--with-icon" type="button" wire:click="mountAction('editPlacement', { section: {{ $section['id'] }} })">
                    <x-filament::icon :icon="\App\Filament\Support\AdminIcon::Edit->mini()" class="admin-action__icon" />
                    <span class="admin-action__label">Edit</span>
                </button>
            @endif

            @if ($section['can_change_publication'])
                <button class="admin-action admin-action--with-icon admin-action--state" type="button" wire:click="toggleSectionState({{ $section['id'] }})">
                    <x-filament::icon :icon="($section['state'] === 'published' ? \App\Filament\Support\AdminIcon::Unpublish : \App\Filament\Support\AdminIcon::Publish)->mini()" class="admin-action__icon" />
                    <span class="admin-action__label">{{ $section['state'] === 'published' ? 'Unpublish' : 'Publish' }}</span>
                </button>
            @else
                <span class="admin-pages__action-placeholder" aria-hidden="true"></span>
            @endif

            @if ($section['can_delete'])
                <button class="admin-action admin-action--with-icon is-danger" type="button" wire:click="deleteSection({{ $section['id'] }})" wire:confirm="Delete this page? Page-specific content, child pages, publication and navigation safety rules still apply.">
                    <x-filament::icon :icon="\App\Filament\Support\AdminIcon::Delete->mini()" class="admin-action__icon" />
                    <span class="admin-action__label">Delete</span>
                </button>
            @else
                <span class="admin-pages__action-placeholder" aria-hidden="true"></span>
            @endif
        </div>

        <label class="admin-hierarchy__selection admin-hierarchy__selection--trailing" role="cell" data-cell="selection">
            <input type="checkbox" aria-label="Select {{ $label }}" value="{{ $section['id'] }}" wire:model.live="selectedSectionIds" @checked($selected)>
        </label>
    </div>

    @if (! $isChild && $reorderEnabled)
        <div
            class="admin-pages__nest-target"
            x-cloak
            x-show="dragging && !draggedHasChildren && draggedId !== {{ $section['id'] }} && draggedParentId !== {{ $section['id'] }}"
            x-bind:class="{ 'is-hovered': hoverParent === {{ $section['id'] }} }"
            x-on:dragenter.stop.prevent="hoverParent = {{ $section['id'] }}"
            x-on:dragover.stop.prevent="hoverParent = {{ $section['id'] }}"
            x-on:dragleave.stop="if (!$el.contains($event.relatedTarget) && hoverParent === {{ $section['id'] }}) hoverParent = null"
            x-on:drop.stop.prevent="
                if (dragging && draggedId !== null && !draggedHasChildren && draggedId !== {{ $section['id'] }} && draggedParentId !== {{ $section['id'] }}) {
                    $wire.sortSection(draggedId, 999999, {{ $section['id'] }});
                }
                dragging = false;
                hoverParent = null;
            "
            aria-hidden="true"
        >
            <span>Drop as child of {{ $label }}</span>
        </div>
    @endif
</div>
