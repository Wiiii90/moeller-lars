<?php

namespace App\Filament\Resources\Artworks\Support;

use App\Models\ArtworkMaterialPreset;
use Filament\Forms\Components\Select;

final class ArtworkMaterialSelect
{
    public static function make(string $name = 'medium'): Select
    {
        return Select::make($name)
            ->label('Material')
            ->options(fn (): array => ArtworkMaterialPreset::query()
                ->orderBy('name')
                ->pluck('name', 'name')
                ->all())
            ->searchable()
            ->preload()
            ->native(false)
            ->placeholder('No material')
            ->nullable()
            ->getOptionLabelUsing(static fn (mixed $value): ?string => is_string($value) && trim($value) !== '' ? $value : null);
    }
}
