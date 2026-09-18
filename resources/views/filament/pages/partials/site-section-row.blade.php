@php
    $label = $section['navigation_label'] ?: $section['title'];
    $selected = in_array((int) $section['id'], array_map('intval', $selectedSectionIds), true);
    $isChild = (int) $section['depth'] === 1;
    $isHome = $section['type'] === \App\Domain\Content\SiteSectionType::Home->value;
    $homeState = $isHome ? app(\App\Filament\Support\HomeSettingsDialog::class)->tableState() : null;
    $homeTemplateOptions = $isHome ? \App\Domain\Content\HomeTemplate::options() : [];
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
    </div>

    <div class="admin-pages__type" role="cell" data-cell="page-type">
        @if ($isHome)
            <span>Landing Page</span>
        @elseif ($section['can_convert'])
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
        @if ($isHome)
            <select
                class="admin-inline-select"
                aria-label="Home template"
                wire:change="changeHomeTemplate({{ $section['id'] }}, $event.target.value)"
            >
                @foreach ($homeTemplateOptions as $value => $templateLabel)
                    <option value="{{ $value }}" @selected(($homeState['template'] ?? null) === $value)>{{ $templateLabel }}</option>
                @endforeach
            </select>
        @elseif ($section['type'] === \App\Domain\Content\SiteSectionType::Journal->value)
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
            @if ($isHome)
                @if ($homeState['skip_home'] ?? false)
                    <button
                        class="admin-action admin-pages__redirect-target"
                        type="button"
                        wire:click="mountAction('skipHome')"
                        title="Change Skip Home target"
                        aria-label="Skip Home to {{ $homeState['skip_target_label'] ?: 'target page' }}"
                    >
                        <x-filament::icon :icon="\App\Filament\Support\AdminIcon::Redirect->mini()" class="admin-action__icon" />
                        <span class="admin-action__label">{{ $homeState['skip_target_label'] ?: 'Set target' }}</span>
                    </button>
                @else
                    <span class="admin-pages__action-placeholder" aria-hidden="true"></span>
                    <span class="admin-pages__action-placeholder" aria-hidden="true"></span>
                @endif

                <x-admin.row-action
                    :action="\App\Filament\Support\AdminRowAction::Edit"
                    wire:click="mountAction('editHome')"
                />

                <x-admin.row-action
                    :action="\App\Filament\Support\AdminRowAction::SkipHome"
                    wire:click="mountAction('skipHome')"
                    aria-label="{{ ($homeState['skip_home'] ?? false) ? 'Edit Skip Home' : 'Configure Skip Home' }}"
                    title="{{ ($homeState['skip_home'] ?? false) ? 'Edit Skip Home' : 'Configure Skip Home' }}"
                />

                <span class="admin-pages__action-placeholder" aria-hidden="true"></span>
            @else
                @if ($section['can_reorder'])
                    <x-admin.row-action
                        :action="\App\Filament\Support\AdminRowAction::MoveUp"
                        :disabled="! $reorderEnabled || ! $section['can_move_up']"
                        wire:click="moveSection({{ $section['id'] }}, 'up')"
                        aria-label="Move {{ $label }} up"
                    />
                    <x-admin.row-action
                        :action="\App\Filament\Support\AdminRowAction::MoveDown"
                        :disabled="! $reorderEnabled || ! $section['can_move_down']"
                        wire:click="moveSection({{ $section['id'] }}, 'down')"
                        aria-label="Move {{ $label }} down"
                    />
                @else
                    <span class="admin-pages__action-placeholder" aria-hidden="true"></span>
                    <span class="admin-pages__action-placeholder" aria-hidden="true"></span>
                @endif

                <x-admin.row-action
                    :action="\App\Filament\Support\AdminRowAction::Edit"
                    wire:click="mountAction('editPage', { section: {{ $section['id'] }} })"
                />

                @if ($section['can_change_publication'])
                    <x-admin.row-action
                        :action="$section['state'] === 'published' ? \App\Filament\Support\AdminRowAction::Unpublish : \App\Filament\Support\AdminRowAction::Publish"
                        wire:click="toggleSectionState({{ $section['id'] }})"
                    />
                @else
                    <span class="admin-pages__action-placeholder" aria-hidden="true"></span>
                @endif

                @if ($section['can_delete'])
                    <x-admin.row-action
                        :action="\App\Filament\Support\AdminRowAction::Delete"
                        wire:click="deleteSection({{ $section['id'] }})"
                        wire:confirm="Delete this page? Page-specific content, child pages, publication and navigation safety rules still apply."
                    />
                @else
                    <span class="admin-pages__action-placeholder" aria-hidden="true"></span>
                @endif
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
            x-on:pointerenter="if (dragging) hoverParent = {{ $section['id'] }}"
            x-on:pointerleave="if (hoverParent === {{ $section['id'] }}) hoverParent = null"
            x-on:dragenter.stop.prevent="hoverParent = {{ $section['id'] }}"
            x-on:dragover.stop.prevent="hoverParent = {{ $section['id'] }}"
            x-on:dragleave.stop="if (!$el.contains($event.relatedTarget) && hoverParent === {{ $section['id'] }}) hoverParent = null"
            x-on:pointerup.stop.prevent="
                if (dragging && draggedId !== null && !draggedHasChildren && draggedId !== {{ $section['id'] }} && draggedParentId !== {{ $section['id'] }}) {
                    $wire.sortSection(draggedId, 999999, {{ $section['id'] }});
                }
                resetDrag();
            "
            x-on:drop.stop.prevent="
                if (dragging && draggedId !== null && !draggedHasChildren && draggedId !== {{ $section['id'] }} && draggedParentId !== {{ $section['id'] }}) {
                    $wire.sortSection(draggedId, 999999, {{ $section['id'] }});
                }
                resetDrag();
            "
            aria-hidden="true"
        >
            <span>Drop as child of {{ $label }}</span>
        </div>
    @endif
</div>
