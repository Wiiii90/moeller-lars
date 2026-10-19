@php
    /** @var \App\Filament\Support\AdminTableRepeater $repeater */
    $items = $repeater->getItems();
    $isSolid = str_ends_with($repeater->getName(), '_solid_colors');
    $isAddable = $repeater->isAddable();
    $isDeletable = $repeater->isDeletable() && count($items) > 2;
    $componentKey = $repeater->getKey();
@endphp

<div class="general-color-table" role="group" aria-label="Colors">
    <x-admin.table class="admin-table--data general-color-table__table" aria-label="Colors">
        <table>
            <colgroup>
                <col class="general-color-table__color-col">
                @unless ($isSolid)
                    <col class="general-color-table__position-col">
                    <col class="general-color-table__action-col">
                @endunless
            </colgroup>
            <thead>
                <tr>
                    <th scope="col">Color</th>
                    @unless ($isSolid)
                        <th scope="col">Position</th>
                        <th scope="col" class="admin-table__actions"><span class="admin-row-actions-heading">Actions</span></th>
                    @endunless
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
                        @unless ($isSolid)
                            <td class="admin-table__actions">
                                @if ($isDeletable)
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
                        @endunless
                    </tr>
                @endforeach
            </tbody>
        </table>
    </x-admin.table>

    @if ($isAddable)
        <x-admin.add-row x-on:click="$wire.mountAction('add', {}, { schemaComponent: @js($componentKey) })">Add color</x-admin.add-row>
    @endif
</div>
