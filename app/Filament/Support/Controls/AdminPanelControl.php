<?php

namespace App\Filament\Support\Controls;

use Closure;
use Filament\Schemas\Components\Group;

/**
 * One reusable enable-and-configure panel for Filament schemas, shared by
 * Stage autosave and Edit modal forms. It does not own domain persistence.
 */
final class AdminPanelControl
{
    /** @param list<mixed> $fields */
    public static function make(string $title, string $enabledField, array $fields, ?Closure $onUpdated = null): Group
    {
        $toggle = AdminControl::checkbox($enabledField, $title)
            ->live()
            ->extraFieldWrapperAttributes(['class' => 'admin-option-panel__switch'], merge: true);
        if ($onUpdated instanceof Closure) {
            $toggle->afterStateUpdated($onUpdated);
        }

        return Group::make([
            $toggle->columnSpanFull(),
            Group::make($fields)
                ->visible(fn (callable $get): bool => (bool) $get($enabledField))
                ->extraAttributes(['class' => 'admin-option-panel__body'])
                ->columns(['default' => 2])
                ->columnSpanFull(),
        ])
            ->columns(1)
            ->extraAttributes(['class' => 'admin-option-panel'])
            ->columnSpanFull();
    }
}
