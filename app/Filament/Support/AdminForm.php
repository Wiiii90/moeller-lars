<?php

namespace App\Filament\Support;

use Filament\Schemas\Components\Section;

final class AdminForm
{
    public static function section(string $label, string ...$classes): Section
    {
        return Section::make($label)
            ->contained(false)
            ->extraAttributes([
                'class' => implode(' ', ['admin-form-section', ...$classes]),
            ]);
    }
}
