<?php

namespace App\Filament\Support;

use Filament\Forms\Components\Select;

final class AdminBooleanControl
{
    /** @return array<string, string> */
    public static function options(string $trueLabel = 'Visible', string $falseLabel = 'Hidden'): array
    {
        return [
            '1' => $trueLabel,
            '0' => $falseLabel,
        ];
    }

    public static function make(
        string $name,
        string $label,
        string $trueLabel = 'Visible',
        string $falseLabel = 'Hidden',
    ): Select {
        return Select::make($name)
            ->label($label)
            ->options(self::options($trueLabel, $falseLabel))
            ->required()
            ->native()
            ->extraInputAttributes([
                'class' => 'admin-form-control admin-boolean-control',
            ]);
    }
}
