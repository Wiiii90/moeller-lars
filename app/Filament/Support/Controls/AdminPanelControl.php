<?php

namespace App\Filament\Support\Controls;

use Closure;
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
     */
    public static function make(string $title, string $enabledField, array $fields, ?Closure $onUpdated = null, bool $collapsible = false, array $bodyColumns = ['default' => 2]): Group
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

        return Group::make([$header, $body])
            ->columns(1)
            ->extraAttributes($collapsible
                ? ['class' => 'admin-option-panel admin-option-panel--collapsible', 'x-data' => '{ expanded: true }']
                : ['class' => 'admin-option-panel'])
            ->columnSpanFull();
    }
}
