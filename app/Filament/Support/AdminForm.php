<?php

namespace App\Filament\Support;

use Filament\Schemas\Components\Fieldset;

final class AdminForm
{
    public static function section(string $label): Fieldset
    {
        return Fieldset::make($label)
            ->contained(false)
            ->extraAttributes(['class' => 'admin-form-section artist-editor-form-section']);
    }
}
