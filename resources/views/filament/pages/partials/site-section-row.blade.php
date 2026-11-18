@php
    $label = $section['navigation_label'] ?: $section['title'];
    $isChild = (int) $section['depth'] === 1;
    $isHome = $section['is_home'];
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
                    data-hierarchy-drag-handle="true"
                    @if ($reorderEnabled) wire:sort:handle @else disabled @endif
                    aria-label="Drag {{ $label }} to a new position"
                    title="Drag to reorder"
                >⋮⋮</button>
            @else
                <span class="admin-pages__drag-placeholder" aria-hidden="true"></span>
            @endif
        </div>

        <div class="admin-hierarchy__content admin-pages__page" role="cell" data-cell="page" title="{{ $label }}">
            @if ($section['workspace_url'])
                <a class="admin-pages__page-link" href="{{ $section['workspace_url'] }}" wire:navigate><strong>{{ $label }}</strong></a>
            @else
                <strong>{{ $label }}</strong>
            @endif
        </div>
    </div>

    <div class="admin-pages__type" role="cell" data-cell="page-type">
        @if ($isHome)
            <span class="admin-inline-control-value">{{ __('Landing Page') }}</span>
        @elseif ($section['can_convert'])
            <select
                class="admin-inline-select"
                aria-label="Page type for {{ $label }}"
                x-data
                x-on:change="$wire.convertSectionType({{ $section['id'] }}, $event.target.value).then(type => { $el.value = type })"
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
                x-data
                x-on:change="$wire.changeHomeTemplate({{ $section['id'] }}, $event.target.value).then(template => { $el.value = template })"
            >
                @foreach ($homeTemplateOptions as $value => $templateLabel)
                    <option value="{{ $value }}" @selected(($homeState['template'] ?? null) === $value)>{{ $templateLabel }}</option>
                @endforeach
            </select>
        @elseif ($section['type'] === \App\Domain\Content\SiteSectionType::Gallery->value)
            <span class="admin-inline-control-value">{{ __($section['template'] === \App\Domain\Content\GalleryTemplate::Showcase->value ? 'Showcase' : 'Hero Artworks') }}</span>
        @elseif ($section['type'] === \App\Domain\Content\SiteSectionType::Journal->value)
            <select
                class="admin-inline-select"
                aria-label="Journal template for {{ $label }}"
                x-data
                x-on:change="$wire.changeJournalTemplate({{ $section['id'] }}, $event.target.value).then(template => { $el.value = template })"
            >
                @foreach ($journalTemplateOptions as $value => $templateLabel)
                    <option value="{{ $value }}" @selected($section['template'] === $value)>{{ $templateLabel }}</option>
                @endforeach
            </select>
        @endif
    </div>

    <div class="admin-pages__utility-grid" role="presentation">
        <div class="admin-row-actions admin-row-actions--canonical admin-row-actions--stable admin-row-actions--move-state-delete admin-toolbar admin-pages__row-actions" role="cell" data-cell="actions" aria-label="Actions for {{ $label }}">
            @if ($isHome)
                <span class="admin-pages__action-placeholder" aria-hidden="true"></span>
                <span class="admin-pages__action-placeholder" aria-hidden="true"></span>

                <x-admin.row-action
                    :action="\App\Filament\Support\AdminRowAction::Edit"
                    wire:click="mountAction('editHome')"
                />

                <span class="admin-pages__action-placeholder" aria-hidden="true"></span>

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
                        wire:click="mountAction('deletePage', { section: {{ $section['id'] }} })"
                        :disabled="! $section['delete_available']"
                        :title="$section['delete_help'] ?? 'Delete page'"
                    />
                @else
                    <span class="admin-pages__action-placeholder" aria-hidden="true"></span>
                @endif
            @endif
        </div>

        <label class="admin-hierarchy__selection admin-hierarchy__selection--trailing" role="cell" data-cell="selection">
            <input type="checkbox" aria-label="Select {{ $label }}" value="{{ $section['id'] }}" wire:model.live="selectedSectionIds">
        </label>
    </div>

</div>
