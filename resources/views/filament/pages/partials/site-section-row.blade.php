@php
    $label = $section['navigation_label'] ?: $section['title'];
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
            <span class="admin-inline-control-value">Landing Page</span>
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
        <div class="admin-row-actions admin-row-actions--canonical admin-row-actions--stable admin-row-actions--move-state-delete admin-toolbar admin-pages__row-actions" role="cell" data-cell="actions" aria-label="Actions for {{ $label }}">
            @if ($isHome)
                @if ($homeState['skip_home'] ?? false)
                    <div
                        class="admin-pages__redirect-target"
                        x-data="{ open: false }"
                        x-on:click.outside="open = false"
                        x-on:keydown.escape.window="open = false"
                    >
                        <button
                            class="admin-action admin-action--with-icon"
                            type="button"
                            x-on:click="open = ! open"
                            x-bind:aria-expanded="open.toString()"
                            aria-haspopup="menu"
                            aria-label="Skip Home destination: {{ $homeState['skip_target_label'] ?: 'next page' }}. Change destination"
                            title="Change Skip Home destination"
                        >
                            <x-filament::icon :icon="\App\Filament\Support\AdminIcon::Redirect->mini()" class="admin-action__icon" />
                            <span class="admin-action__label">{{ $homeState['skip_target_label'] ?: 'Next page' }}</span>
                        </button>
                        <div class="admin-selection__menu" role="menu" x-cloak x-show="open">
                            <button
                                class="admin-action"
                                type="button"
                                role="menuitemradio"
                                aria-checked="{{ $homeState['skip_target_section_id'] === null ? 'true' : 'false' }}"
                                wire:click="changeSkipHomeTarget('')"
                                x-on:click="open = false"
                            >Next page in order</button>
                            @foreach ($homeState['skip_target_options'] as $targetId => $targetLabel)
                                <button
                                    class="admin-action"
                                    type="button"
                                    role="menuitemradio"
                                    aria-checked="{{ (int) $homeState['skip_target_section_id'] === (int) $targetId && $homeState['skip_target_section_id'] !== null ? 'true' : 'false' }}"
                                    wire:click="changeSkipHomeTarget('{{ (int) $targetId }}')"
                                    x-on:click="open = false"
                                >{{ $targetLabel }}</button>
                            @endforeach
                        </div>
                    </div>
                @else
                    <span class="admin-pages__action-placeholder" aria-hidden="true"></span>
                    <span class="admin-pages__action-placeholder" aria-hidden="true"></span>
                @endif

                <x-admin.row-action
                    :action="\App\Filament\Support\AdminRowAction::Edit"
                    wire:click="mountAction('editHome')"
                />

                <x-admin.row-action
                    :action="($homeState['skip_home'] ?? false) ? \App\Filament\Support\AdminRowAction::ShowHome : \App\Filament\Support\AdminRowAction::SkipHome"
                    wire:click="toggleSkipHome"
                    wire:loading.attr="disabled"
                    wire:target="toggleSkipHome"
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
                        wire:click="mountAction('deletePage', { section: {{ $section['id'] }} })"
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

    @if (! $isChild && $reorderEnabled && $nestedNavigationEnabled)
        <div class="admin-pages__child-drop" data-pages-drop="child" data-pages-parent="{{ $section['id'] }}" role="button" aria-label="Move dragged page under {{ $label }}"><span>Drop as child</span></div>
    @endif
</div>
