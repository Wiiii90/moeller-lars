<?php

namespace App\Filament\Support\Controls;

use Closure;
use Filament\Forms\Components\Checkbox;
use Filament\Schemas\Components\Group;
use Filament\Schemas\Components\View;

/**
 * One reusable enable-and-configure panel for Filament schemas, shared by
 * Stage autosave and Edit modal forms. It does not own domain persistence.
 */
final class AdminPanelControl
{
    /** @param list<mixed> $fields
     *  @param array<string, int> $bodyColumns
     *  The optional unlined surface and compact field-row layout are panel
     *  presentation variants. The original Filament fields keep their own
     *  state, labels, validation, and event handling.
     */
    public static function make(string $title, string $enabledField, array $fields, ?Closure $onUpdated = null, bool $collapsible = false, array $bodyColumns = ['default' => 2], bool $unlinedFields = false, bool $compactRows = false, bool $dense = false): Group
    {
        $toggle = AdminControl::checkbox($enabledField, $title)
            ->live()
            ->extraFieldWrapperAttributes(['class' => 'admin-option-panel__switch'], merge: true);
        if ($onUpdated instanceof Closure) {
            $toggle->afterStateUpdated($onUpdated);
        }

        // A disclosure controls presentation only. Enabling/disabling the
        // option remains exclusively owned by its checkbox and save callback.
        $header = $collapsible
            ? Group::make([
                $toggle,
                View::make('filament.support.admin-panel-disclosure'),
            ])->columns(['default' => 2])->extraAttributes(['class' => 'admin-option-panel__header'])
            : $toggle->columnSpanFull();

        $body = Group::make($fields)
            ->visible(fn (callable $get): bool => (bool) $get($enabledField))
            ->extraAttributes($collapsible
                ? ['class' => 'admin-option-panel__body', 'x-show' => 'expanded', 'x-cloak' => true]
                : ['class' => 'admin-option-panel__body'])
            ->columns($bodyColumns)
            ->columnSpanFull();

        $classes = 'admin-option-panel'
            .($collapsible ? ' admin-option-panel--collapsible' : '')
            .($unlinedFields ? ' admin-option-panel--unlined-fields' : '')
            .($compactRows ? ' admin-option-panel--compact-rows' : '')
            .($dense ? ' admin-option-panel--dense' : '');

        return Group::make([$header, $body])
            ->columns(1)
            ->extraAttributes($collapsible
                ? ['class' => $classes, 'x-data' => '{ expanded: true }']
                : ['class' => $classes])
            ->columnSpanFull();
    }
    /**
     * One reusable presentation of dependent checkbox options. Checkbox
     * callbacks remain the only owners of disabled state and persistence.
     * Nested trees and multiple dependents use the same connector structure.
     *
     * @param list<Checkbox|Group> $dependents
     */
    public static function dependencyTree(Checkbox $required, array $dependents): Group
    {
        $branches = array_map(
            static fn (Checkbox|Group $dependent): Group => Group::make([$dependent])
                ->columns(1)
                ->extraAttributes(['class' => 'admin-option-panel__dependency-branch'])
                ->columnSpanFull(),
            $dependents,
        );

        return Group::make([
            $required,
            Group::make($branches)
                ->columns(1)
                ->extraAttributes(['class' => 'admin-option-panel__dependency-branches'])
                ->columnSpanFull(),
        ])
            ->columns(1)
            ->extraAttributes(['class' => 'admin-option-panel__dependency-tree'])
            ->columnSpanFull();
    }
}
