@php
    /** @var \App\Filament\Support\AdminTableRepeater $repeater */
    $items = $repeater->getItems();
    $columns = $repeater->getTableColumns() ?? [];

    // Use Filament's registered schema actions and their own Livewire handlers.
    // A manually constructed mountAction() call lacks the correct form context.
    $addAction = $repeater->getAction($repeater->getAddActionName());
    $deleteAction = $repeater->getAction($repeater->getDeleteActionName());

    $isAddable = $repeater->isAddable() && $addAction?->isVisible();
    $hasActions = $repeater->isDeletable();
    $canDelete = $hasActions && count($items) > ($repeater->getMinItems() ?? 0);
    $canReorder = $repeater->isReorderableWithButtons();
    $moveUpAction = $canReorder ? $repeater->getAction($repeater->getMoveUpActionName()) : null;
    $moveDownAction = $canReorder ? $repeater->getAction($repeater->getMoveDownActionName()) : null;
    $isMediaTable = $repeater->getName() === 'gallery_images';
@endphp

<div class="admin-repeater-table @if ($isMediaTable) admin-repeater-table--media @endif" role="group" aria-label="{{ $repeater->getLabel() }}">
    <x-admin.table class="admin-table--data admin-repeater-table__table @if ($isMediaTable) admin-table--ranked @endif" aria-label="{{ $repeater->getLabel() }}">
        <table>
            @if ($isMediaTable)
                <colgroup>
                    <col class="admin-table__col-position">
                    <col class="admin-table__col-drag">
                    <col class="admin-repeater-table__col-media">
                    <col class="admin-repeater-table__col-controls">
                    <col class="admin-repeater-table__col-actions">
                </colgroup>
            @endif
            <thead>
                <tr>
                    @if ($isMediaTable)
                        <th scope="colgroup" colspan="2" class="admin-table__ordering-heading">Position</th>
                    @endif
                    @foreach ($columns as $column)
                        <th scope="col">{{ $column->getLabel() }}</th>
                    @endforeach
                    @if ($hasActions)
                        <th scope="col" class="admin-table__actions"><span class="admin-row-actions-heading">Actions</span></th>
                    @endif
                </tr>
            </thead>
            <tbody @if ($isMediaTable && $canReorder) wire:sort="sortExhibitionGalleryMedia" @endif>
                @foreach ($items as $itemKey => $item)
                    <tr wire:key="{{ $item->getLivewireKey() }}.item" @if ($isMediaTable && $canReorder) wire:sort:item="{{ $itemKey }}" @endif>
                        @if ($isMediaTable)
                            <td class="admin-table__position">
                                <span class="admin-position">{{ $loop->iteration }}</span>
                            </td>
                            <td class="admin-table__drag">
                                <button
                                    class="admin-drag-handle"
                                    type="button"
                                    @if ($canReorder) wire:sort:handle @else disabled @endif
                                    aria-label="Drag gallery media {{ $loop->iteration }} to reorder"
                                    title="Drag to reorder"
                                >⋮⋮</button>
                            </td>
                        @endif
                        @foreach ($item->getComponents(withHidden: true) as $component)
                            @if ($component instanceof \Filament\Forms\Components\Hidden)
                                {!! $component->toHtml() !!}
                            @else
                                <td>{!! $component->toSchemaHtml() !!}</td>
                            @endif
                        @endforeach
                        @if ($hasActions)
                            <td class="admin-table__actions">
                                @if ($isMediaTable)
                                    <div class="admin-row-actions--stable admin-row-actions--move-delete">
                                @endif
                                @if ($canReorder)
                                    <x-admin.row-action
                                        :action="\App\Filament\Support\AdminRowAction::MoveUp"
                                        :disabled="$loop->first"
                                        wire:click="{{ $moveUpAction(['item' => $itemKey])->getLivewireClickHandler() }}"
                                    />
                                    <x-admin.row-action
                                        :action="\App\Filament\Support\AdminRowAction::MoveDown"
                                        :disabled="$loop->last"
                                        wire:click="{{ $moveDownAction(['item' => $itemKey])->getLivewireClickHandler() }}"
                                    />
                                @endif
                                <x-admin.row-action
                                    :action="\App\Filament\Support\AdminRowAction::Delete"
                                    :disabled="! $canDelete || ! $deleteAction(['item' => $itemKey])->isVisible()"
                                    wire:click="{{ $deleteAction(['item' => $itemKey])->getLivewireClickHandler() }}"
                                    :aria-label="$isMediaTable ? __('Delete').' '.__('Gallery media').' '.$loop->iteration : 'Delete color '.$loop->iteration"
                                />
                                @if ($isMediaTable)
                                    </div>
                                @endif
                            </td>
                        @endif
                    </tr>
                @endforeach
            </tbody>
        </table>
    </x-admin.table>

    @if ($isAddable)
        <x-admin.add-row wire:click="{{ $addAction->getLivewireClickHandler() }}">{{ $addAction->getLabel() }}</x-admin.add-row>
    @endif
</div>
