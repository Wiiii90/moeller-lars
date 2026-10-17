<?php

namespace App\Filament\Support;

use Filament\Forms\Components\ViewField;

final class AdminAngleControl
{
    public static function make(string $name, string $label = 'Angle'): ViewField
    {
        return ViewField::make($name)
            ->label($label)
            ->view('filament.forms.components.admin-angle-control');
    }
}
