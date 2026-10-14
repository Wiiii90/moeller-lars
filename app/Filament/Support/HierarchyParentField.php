<?php

namespace App\Filament\Support;

use Closure;
use Filament\Forms\Components\Select;

/** A shared parent placement field: ordering stays a separate concern. */
final class HierarchyParentField
{
    /** @param Closure(): array<int|string, string> $options */
    public static function make(string $name, Closure $options, string $label = 'Parent', string $root = 'Top level'): Select
    {
        return Select::make($name)
            ->label($label)
            ->options($options)
            ->placeholder($root)
            ->native()
            ->nullable();
    }
}
