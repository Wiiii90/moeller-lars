<?php

namespace App\Filament\Support\Controls;

use App\Domain\Artwork\ArtworkInformation;
use Closure;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\Repeater;

/**
 * Constrained responsive information rows. Filament owns both drag sorting
 * and keyboard-accessible Move up/down actions; both change one ordered list.
 */
final class AdminArrangementControl
{
    public static function make(string $field = 'info_arrangement', ?Closure $onUpdated = null): Repeater
    {
        $row = AdminControl::select('row', 'Row')
            ->options([1 => '1', 2 => '2', 3 => '3', 4 => '4'])
            ->required()->native(false)->live();
        $align = AdminControl::select('align', 'Align')
            ->options(ArtworkInformation::ALIGNMENTS)
            ->required()->native(false)->live();

        if ($onUpdated instanceof Closure) {
            $row->afterStateUpdated($onUpdated);
            $align->afterStateUpdated($onUpdated);
        }

        $repeater = Repeater::make($field)
            ->label('Arrangement')
            ->schema([Hidden::make('field'), $row, $align])
            ->itemLabel(fn (array $state): string => ArtworkInformation::FIELDS[$state['field'] ?? ''] ?? 'Information')
            ->addable(false)
            ->deletable(false)
            ->cloneable(false)
            ->reorderable()
            ->reorderableWithButtons()
            ->reorderableWithDragAndDrop()
            ->collapsible()
            ->collapsed()
            ->default(ArtworkInformation::defaultArrangement())
            ->columns(2)
            ->live()
            ->extraAttributes(['class' => 'admin-arrangement-control'])
            ->columnSpanFull();

        if ($onUpdated instanceof Closure) {
            $repeater->afterStateUpdated($onUpdated);
        }

        return $repeater;
    }
}
