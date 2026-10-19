@php
    /** @var \App\Filament\Support\AdminTableRepeater $repeater */
    $items = $repeater->getItems();
    $columns = $repeater->getTableColumns() ?? [];
    $isAddable = $repeater->isAddable();
    $hasActions = $repeater->isDeletable();
    $canDelete = $hasActions && count($items) > ($repeater->getMinItems() ?? 0);
    $componentKey = $repeater->getKey();
@endphp

<div class="general-color-table" role="group" aria-label="{{ $repeater->getLabel() }}">
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
                                @if ($canDelete)
                                    <button
                                        type="button"
                                        class="admin-action admin-action--with-icon"
                                        x-on:click="$wire.mountAction('delete', { item: @js($itemKey) }, { schemaComponent: @js($componentKey) })"
                                        aria-label="Remove color {{ $loop->iteration }}"
                                        title="Remove color"
                                    >
                                        <x-filament::icon :icon="\App\Filament\Support\AdminIcon::Remove->mini()" :size="\Filament\Support\Enums\IconSize::Small" class="admin-action__icon" />
                                        <span class="admin-action__label">Remove</span>
                                    </button>
                                @endif
                            </td>
                        @endif
                    </tr>
                @endforeach
            </tbody>
        </table>
    </x-admin.table>

    @if ($isAddable)
        <x-admin.add-row x-on:click="$wire.mountAction('add', {}, { schemaComponent: @js($componentKey) })">{{ $repeater->getAddActionLabel() }}</x-admin.add-row>
    @endif
</div>
