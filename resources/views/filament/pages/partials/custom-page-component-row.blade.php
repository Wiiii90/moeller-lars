@php
    $type = $row['type'];
    $index = $row['index'];
    $args = "{ componentIndex: {$index}, componentType: '{$type}' }";
    $label = $row['type_label'];
@endphp
<div class="custom-page-component__header admin-hierarchy__row"
    data-component-index="{{ $index }}" data-component-type="{{ $type }}">
    <div class="admin-hierarchy__position-cell">
        <span class="admin-position" aria-label="Position {{ $row['position'] }}">
            {{ str_pad((string) $row['position'], 2, '0', STR_PAD_LEFT) }}
        </span>
    </div>
    <button class="admin-drag-handle custom-page-component__drag" type="button"
        @if ($reorderEnabled) wire:sort:handle @endif
        @disabled(! $reorderEnabled)
        aria-label="Drag {{ $label }}" title="Drag to reorder"
    >⋮⋮</button>
    <div class="custom-page-component__layout">
        <select class="admin-inline-select custom-page-component__layout-select"
            aria-label="Assign {{ $label }} to layout area"
            wire:change="setTableComponentLayout({{ $index }}, '{{ $type }}', $event.target.value)">
            <option value="" @selected($row['layout_area_id'] === null)>{{ __('Full width') }}</option>
            @foreach ($this->stageLayoutAreaOptions() as $areaId => $areaLabel)
                <option value="{{ $areaId }}" @selected($row['layout_area_id'] === $areaId)>{{ $areaLabel }}</option>
            @endforeach
        </select>
    </div>
    <div class="custom-page-component__type">
        <select class="admin-inline-select custom-page-component__type-select"
            aria-label="Component type"
            wire:change="mountAction('changeComponentType', { ...{{ $args }}, targetType: $event.target.value })">
            @foreach ($componentTypeOptions as $value => $typeLabel)
                <option value="{{ $value }}" @selected($value === $type)>{{ $typeLabel }}</option>
            @endforeach
        </select>
    </div>
    <div class="custom-page-component__content admin-hierarchy__content {{ in_array($type, ['contact', 'legal_disclaimer'], true) ? 'custom-page-component__content--two-lines' : '' }}">
        <strong>{{ $row['content']['primary'] }}</strong>
        @if ($row['content']['secondary'] !== '')
            <span>{{ $row['content']['secondary'] }}</span>
        @endif
        @if ($row['content']['meta'] !== '')
            <small>{{ $row['content']['meta'] }}</small>
        @endif
    </div>
    <div class="admin-row-actions admin-row-actions--canonical admin-row-actions--stable admin-row-actions--move-state-delete custom-page-row-actions custom-page-component__actions admin-toolbar">
        <x-admin.row-action :action="\App\Filament\Support\AdminRowAction::MoveUp"
            wire:click="moveComponent({{ $index }}, '{{ $type }}', 'up')"
            :disabled="! $reorderEnabled || ! $row['can_move_up']"
            aria-label="Move component up" />
        <x-admin.row-action :action="\App\Filament\Support\AdminRowAction::MoveDown"
            wire:click="moveComponent({{ $index }}, '{{ $type }}', 'down')"
            :disabled="! $reorderEnabled || ! $row['can_move_down']"
            aria-label="Move component down" />
        <x-admin.row-action :action="\App\Filament\Support\AdminRowAction::Edit"
            wire:click="mountAction('editComponent', {{ $args }})"
            :disabled="! $row['editable']" />
        <x-admin.row-action
            :action="$row['published'] ? \App\Filament\Support\AdminRowAction::Unpublish : \App\Filament\Support\AdminRowAction::Publish"
            wire:click="setComponentPublished({{ $index }}, '{{ $type }}', {{ $row['published'] ? 'false' : 'true' }})" />
        <x-admin.row-action :action="\App\Filament\Support\AdminRowAction::Delete"
            :disabled="! $row['can_delete']"
            wire:click="mountAction('deleteComponent', {{ $args }})" />
    </div>
    <label class="admin-hierarchy__selection admin-hierarchy__selection--trailing" aria-label="Select {{ $label }}">
        <input type="checkbox" value="{{ $row['target'] }}" wire:model.live="selectedComponentTargets">
    </label>
</div>
