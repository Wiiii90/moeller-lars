@php
    $isChild = (bool) ($isChild ?? false);
    $type = $row['type'];
    $index = $row['index'];
    $imageIndex = $isChild ? $row['image_index'] : null;
    $args = $isChild
        ? "{ imageIndex: {$imageIndex}, childIndex: {$index}, componentType: '{$type}' }"
        : "{ componentIndex: {$index}, componentType: '{$type}' }";
    $nestedArgs = $isChild ? "{$imageIndex}, {$index}, '{$type}'" : null;
    $label = $row['type_label'];
@endphp
<div
    class="custom-page-component__header admin-hierarchy__row {{ $isChild ? 'custom-page-child-row is-child' : '' }}"
    data-component-index="{{ $isChild ? $imageIndex : $index }}"
    data-component-type="{{ $type }}"
    data-can-nest="{{ $isChild ? 'true' : ($row['can_nest'] ? 'true' : 'false') }}"
    @if ($isChild) data-parent-image="{{ $imageIndex }}" @endif
>
    <div class="admin-hierarchy__position-cell">
        <span class="admin-position" aria-label="Position {{ $row['position'] }}">
            {{ $isChild ? $row['position'] : str_pad((string) $row['position'], 2, '0', STR_PAD_LEFT) }}
        </span>
    </div>
    <button class="admin-drag-handle custom-page-component__drag" type="button"
        @if ($reorderEnabled) wire:sort:handle @endif
        @disabled(! $reorderEnabled)
        aria-label="Drag {{ $label }}" title="Drag to reorder or change parent"
    >⋮⋮</button>
    <div class="custom-page-component__type">
        <select class="admin-inline-select custom-page-component__type-select"
            aria-label="Component type"
            wire:change="mountAction('{{ $isChild ? 'changeImageChildType' : 'changeComponentType' }}', { ...{{ $args }}, targetType: $event.target.value })"
        >
            @foreach ($componentTypeOptions as $value => $typeLabel)
                @if (! $isChild || in_array($value, \App\Models\CustomPageSetting::IMAGE_CHILD_TYPES, true))
                    <option value="{{ $value }}" @selected($value === $type)>{{ $typeLabel }}</option>
                @endif
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
        <x-admin.row-action
            :action="\App\Filament\Support\AdminRowAction::MoveUp"
            wire:click="{{ $isChild ? 'moveImageChildSibling('.$nestedArgs.', \'up\')' : 'moveComponent('.$index.', \''.$type.'\', \'up\')' }}"
            :disabled="! $reorderEnabled || ! $row['can_move_up']"
            aria-label="Move component up"
        />
        <x-admin.row-action
            :action="\App\Filament\Support\AdminRowAction::MoveDown"
            wire:click="{{ $isChild ? 'moveImageChildSibling('.$nestedArgs.', \'down\')' : 'moveComponent('.$index.', \''.$type.'\', \'down\')' }}"
            :disabled="! $reorderEnabled || ! $row['can_move_down']"
            aria-label="Move component down"
        />
        <x-admin.row-action
            :action="\App\Filament\Support\AdminRowAction::Edit"
            wire:click="mountAction('{{ $isChild ? 'editImageChild' : ($type === 'list' ? 'editListComponent' : 'editComponent') }}', {{ $args }})"
            :disabled="! $row['editable']"
        />
        <x-admin.row-action
            :action="$row['published'] ? \App\Filament\Support\AdminRowAction::Unpublish : \App\Filament\Support\AdminRowAction::Publish"
            wire:click="{{ $isChild
                ? 'setImageChildPublished('.$nestedArgs.', '.($row['published'] ? 'false' : 'true').')'
                : 'setComponentPublished('.$index.', \''.$type.'\', '.($row['published'] ? 'false' : 'true').')' }}"
        />
        <x-admin.row-action
            :action="\App\Filament\Support\AdminRowAction::Delete"
            wire:click="mountAction('{{ $isChild ? 'deleteImageChild' : 'deleteComponent' }}', {{ $args }})"
        />
    </div>
    <label class="admin-hierarchy__selection admin-hierarchy__selection--trailing" aria-label="Select {{ $label }}">
        <input type="checkbox" value="{{ $row['target'] }}" wire:model.live="selectedComponentTargets">
    </label>
</div>
