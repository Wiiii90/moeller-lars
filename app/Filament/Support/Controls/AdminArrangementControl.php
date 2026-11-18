<?php

namespace App\Filament\Support\Controls;

use App\Domain\Artwork\ArtworkInformation;
use Closure;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Repeater\TableColumn;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Utilities\Get;

/**
 * A compact, real Filament table repeater: the same ordered field list drives
 * both native drag and Move up/down, without a separate sorting lifecycle.
 * Row and alignment are visible inline rather than in nested item cards.
 */
final class AdminArrangementControl
{
    public static function make(string $field = 'info_arrangement', ?Closure $onUpdated = null): Repeater
    {
        $row = AdminControl::select('row', 'Row')
            ->options([1 => '1', 2 => '2', 3 => '3', 4 => '4'])
            ->native(true)
            ->placeholder(null)->selectablePlaceholder(false)
            ->live();
        $alignment = AdminControl::select('align', 'Alignment')
            ->options(ArtworkInformation::ALIGNMENTS)
            ->native(true)
            ->placeholder(null)->selectablePlaceholder(false)
            ->live();

        if ($onUpdated instanceof Closure) {
            $row->afterStateUpdated($onUpdated);
            $alignment->afterStateUpdated($onUpdated);
        }

        $repeater = Repeater::make($field)
            ->label('Arrangement')
            ->hiddenLabel()
            ->table([
                TableColumn::make('Information'),
                TableColumn::make('Row')->width('3.5rem'),
                TableColumn::make('Align')->width('5.5rem'),
            ])
            ->compact()
            ->schema([
                Hidden::make('field'),
                TextEntry::make('field_label')
                    ->hiddenLabel()
                    ->state(fn (Get $get): string => ArtworkInformation::FIELDS[$get('field') ?? ''] ?? ''),
                $row,
                $alignment,
            ])
            ->addable(false)
            ->deletable(false)
            ->cloneable(false)
            ->reorderable()
            ->reorderableWithButtons()
            ->reorderableWithDragAndDrop()
            ->default(ArtworkInformation::defaultArrangement())
            ->live()
            ->extraAttributes(['class' => 'admin-arrangement-control admin-table'])
            ->columnSpanFull();

        if ($onUpdated instanceof Closure) {
            $repeater->afterStateUpdated($onUpdated);
        }

        return $repeater;
    }
}
