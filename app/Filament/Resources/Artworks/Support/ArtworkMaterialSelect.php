<?php

namespace App\Filament\Resources\Artworks\Support;

use App\Domain\Artwork\ArtworkMaterialPresetService;
use App\Models\ArtworkMaterialPreset;
use Filament\Actions\Action;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;

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
            ->nullable()
            ->getOptionLabelUsing(static fn (mixed $value): ?string => is_string($value) && trim($value) !== '' ? $value : null)
            ->createOptionModalHeading('Add material')
            ->createOptionForm([
                TextInput::make('name')
                    ->label('Material')
                    ->required()
                    ->maxLength(240),
            ])
            ->createOptionUsing(function (array $data): string {
                $preset = app(ArtworkMaterialPresetService::class)->add((string) ($data['name'] ?? ''));

                return (string) $preset->getAttribute('name');
            })
            ->createOptionAction(fn (Action $action): Action => $action
                ->label('Add material')
                ->modalSubmitActionLabel('Add material'));
    }
}
