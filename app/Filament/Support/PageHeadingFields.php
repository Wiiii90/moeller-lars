<?php

namespace App\Filament\Support;

use App\Domain\Content\SitePageHeading;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Utilities\Get;

/** Shared form schema for the Pages Edit dialog and Home settings. */
final class PageHeadingFields
{
    public static function schema(): Grid
    {
        return Grid::make()
            ->columns(['md' => 2])
            ->schema([
                Toggle::make('content_heading_enabled')
                    ->label('Show page heading')
                    ->default(false)
                    ->live()
                    ->columnSpanFull(),
                TextInput::make('content_heading_override')
                    ->label('Heading text')
                    ->placeholder('Use page name')
                    ->maxLength(160)
                    ->visible(fn (Get $get): bool => (bool) $get('content_heading_enabled'))
                    ->columnSpanFull(),
                Select::make('content_heading_alignment')
                    ->label('Alignment')
                    ->options(SitePageHeading::ALIGNMENTS)
                    ->default('left')
                    ->native()
                    ->required()
                    ->visible(fn (Get $get): bool => (bool) $get('content_heading_enabled')),
                Select::make('content_heading_divider')
                    ->label('Divider below heading')
                    ->options(SitePageHeading::DIVIDERS)
                    ->default('none')
                    ->native()
                    ->required()
                    ->visible(fn (Get $get): bool => (bool) $get('content_heading_enabled')),
            ]);
    }
}
