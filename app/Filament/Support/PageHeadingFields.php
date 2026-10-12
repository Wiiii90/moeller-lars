<?php

namespace App\Filament\Support;

use App\Domain\Content\SitePageHeading;
use Closure;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Utilities\Get;

/** Shared form schema for the Pages Edit dialog and Home settings. */
final class PageHeadingFields
{
    /** Persist each heading field through the owner on a Livewire update. */
    public static function schema(Closure $persist): Grid
    {
        return Grid::make()
            ->columns(['md' => 2])
            ->schema([
                Toggle::make('content_heading_enabled')
                    ->label('Show page heading')
                    ->default(false)
                    ->live()
                    ->afterStateUpdated(fn (mixed $state) => $persist('content_heading_enabled', (bool) $state))
                    ->columnSpanFull(),
                TextInput::make('content_heading_override')
                    ->label('Heading text')
                    ->placeholder('Use page name')
                    ->maxLength(160)
                    ->live(onBlur: true)
                    ->afterStateUpdated(fn (mixed $state) => $persist('content_heading_override', $state))
                    ->visible(fn (Get $get): bool => (bool) $get('content_heading_enabled'))
                    ->columnSpanFull(),
                Select::make('content_heading_alignment')
                    ->label('Alignment')
                    ->options(SitePageHeading::ALIGNMENTS)
                    ->default('left')
                    ->native()
                    ->required()
                    ->live()
                    ->afterStateUpdated(fn (mixed $state) => $persist('content_heading_alignment', $state))
                    ->visible(fn (Get $get): bool => (bool) $get('content_heading_enabled')),
                Select::make('content_heading_divider')
                    ->label('Divider below heading')
                    ->options(SitePageHeading::DIVIDERS)
                    ->default('none')
                    ->native()
                    ->required()
                    ->live()
                    ->afterStateUpdated(fn (mixed $state) => $persist('content_heading_divider', $state))
                    ->visible(fn (Get $get): bool => (bool) $get('content_heading_enabled')),
            ]);
    }
}
