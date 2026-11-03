<?php

namespace App\Filament\Support;

use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Grid;

/**
 * One compact four-part editor for dimensions. Separators live BETWEEN
 * controls in this shared component, never inside numeric field values.
 */
final class AdminDimensionsControl
{
    public static function make(): Grid
    {
        return Grid::make()
            ->columns(['default' => 4])
            ->columnSpanFull()
            ->extraAttributes(['class' => 'admin-dimensions-control'])
            ->schema([
                TextInput::make('dimension_height')
                    ->label('Height')
                    ->numeric()
                    ->minValue(0.01)
                    ->nullable()
                    ->extraFieldWrapperAttributes(['class' => 'admin-dimensions-control__factor'], merge: true),
                TextInput::make('dimension_width')
                    ->label('Width')
                    ->numeric()
                    ->minValue(0.01)
                    ->nullable()
                    ->extraFieldWrapperAttributes(['class' => 'admin-dimensions-control__factor'], merge: true),
                TextInput::make('dimension_depth')
                    ->label('Depth')
                    ->numeric()
                    ->minValue(0.01)
                    ->nullable(),
                Select::make('dimension_unit')
                    ->label('Unit')
                    ->options(['cm' => 'cm', 'mm' => 'mm', 'in' => 'in'])
                    ->placeholder('Select unit')
                    ->selectablePlaceholder()
                    ->nullable(),
            ]);
    }
}
