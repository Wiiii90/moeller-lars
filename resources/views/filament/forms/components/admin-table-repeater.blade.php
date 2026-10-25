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

<div class="general-color-table @if ($isMediaTable) admin-media-table @endif" role="group" aria-label="{{ $repeater->getLabel() }}">
    <x-admin.table class="admin-table--data general-color-table__table" aria-label="{{ $repeater->getLabel() }}">
        <table>
            <thead>
                <tr>
                    @foreach ($columns as $column)
                        <th scope="col">{{ $column->getLabel() }}</th>
                    @endforeach
                    @if ($hasActions)
                        <th scope="col" class="admin-table__actions"><span class="admin-row-actions-heading">Actions</span></th>
                    @endif
                </tr>
            </thead>
            <tbody>
                @foreach ($items as $itemKey => $item)
                    <tr wire:key="{{ $item->getLivewireKey() }}.item">
                        @foreach ($item->getComponents(withHidden: true) as $component)
                            @if ($component instanceof \Filament\Forms\Components\Hidden)
                                {!! $component->toHtml() !!}
                            @else
                                <td>{!! $component->toSchemaHtml() !!}</td>
                            @endif
                        @endforeach
                        @if ($hasActions)
                            <td class="admin-table__actions">
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
