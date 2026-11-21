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
     * Present a prerequisite on the left, and dependent fields in a shared
     * right column. The dependency edges are decorative: the original form
     * fields retain their own disabled state, validation and persistence.
     *
     * @param list<Checkbox|Group> $dependents
     */
    public static function dependencyTree(Checkbox $required, array $dependents): Group
    {
        $required->extraFieldWrapperAttributes(
            ['class' => 'admin-option-panel__dependency-target'],
            merge: true,
        );

        foreach ($dependents as $dependent) {
            if ($dependent instanceof Checkbox) {
                $dependent->extraFieldWrapperAttributes(
                    ['class' => 'admin-option-panel__dependency-leaf'],
                    merge: true,
                );
            } else {
                $dependent->extraAttributes(
                    ['class' => 'admin-option-panel__dependency-leaf'],
                    merge: true,
                );
            }
        }

        $branches = Group::make($dependents)
            ->columns(['default' => 1])
            ->extraAttributes([
                'class' => 'admin-option-panel__dependency-branches'
                    . (count($dependents) > 1 ? ' admin-option-panel__dependency-branches--multiple' : ''),
            ]);

        return Group::make([$required, $branches])
            ->columns(['default' => 2])
            ->extraAttributes(['class' => 'admin-option-panel__dependency-map'])
            ->columnSpanFull();
    }

}
