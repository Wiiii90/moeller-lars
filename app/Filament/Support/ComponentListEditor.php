<?php

namespace App\Filament\Support;

use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\Toggle;

/**
 * One list-entry editor for Custom Pages and both editable Home templates.
 * Items remain an ordered array within their owning List component.
 */
final class ComponentListEditor
{
    public static function make(): Repeater
    {
        return Repeater::make('items')
            ->label('List entries')
            ->schema([
                TextInput::make('title')->label('Entry')->required()->maxLength(240),
                TextInput::make('date')->label('Date / year')->maxLength(120),
                TextInput::make('meta')->label('Organisation / context')->maxLength(240),
                TextInput::make('location')->maxLength(240),
                TextInput::make('url')->label('Optional link')->url()->maxLength(2048),
                Toggle::make('published')->label('Published')->default(true),
                Textarea::make('body')->label('Details (Markdown)')->rows(4)->maxLength(10000)->columnSpanFull(),
            ])
            ->columns(['md' => 2])
            ->defaultItems(0)
            ->addActionLabel('Add list item')
            ->reorderableWithButtons()
            ->reorderableWithDragAndDrop(false)
            ->columnSpanFull();
    }
}
