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
                ->map(static fn (string $label): string => html_entity_decode($label, ENT_QUOTES | ENT_HTML5, 'UTF-8'))
                ->all())
            ->searchable()
            ->preload()
            ->native(false)
            ->placeholder('No material')
            ->nullable()
            ->getOptionLabelUsing(static fn (mixed $value): ?string => is_string($value) && trim($value) !== ''
                ? html_entity_decode($value, ENT_QUOTES | ENT_HTML5, 'UTF-8')
                : null);
    }
}
