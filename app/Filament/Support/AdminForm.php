<?php

namespace App\Filament\Support;

use Filament\Schemas\Components\Fieldset;

final class AdminForm
{
    public static function section(string $label, string ...$classes): Fieldset
    {
        return Fieldset::make($label)
            ->contained(false)
            ->extraAttributes([
                'class' => implode(' ', ['admin-form-section', 'artist-editor-form-section', ...$classes]),
            ]);
    }
}
