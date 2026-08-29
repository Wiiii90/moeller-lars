<?php

namespace App\Filament\Support;

use Filament\Forms\Components\ColorPicker;

final class AdminColorControl
{
    public static function make(string $name, string $label): ColorPicker
    {
        return ColorPicker::make($name)
            ->label($label)
            ->nullable();
    }
}
