<?php

namespace App\Filament\Support;

use App\Domain\Content\RegionalFormats;
use Filament\Forms\Components\ViewField;

/**
 * One reusable date-picker field with Year / Month / Day precision.
 * It owns the same single canonical ISO partial state in every consumer.
 */
final class AdminPartialDateControl
{
    public static function make(string $name = 'artwork_date'): ViewField
    {
        return ViewField::make($name)
            ->label('Artwork date')
            ->view('filament.forms.components.admin-partial-date-control')
            ->viewData(['region' => RegionalFormats::publicRegion()]);
    }
}
