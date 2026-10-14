<?php

namespace App\Filament\Support;

use App\Models\PublicContentSetting;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Utilities\Get;

/** One level of ordered, independently published prose owned by a decorative Image. */
final class DecorativeImageChildren
{
    /** @param array<string,string> $platforms */
    public static function make(array $platforms): Repeater
    {
        return Repeater::make('image_children')
            ->label('Content around image')
            ->schema([
                Select::make('type')
                    ->label('Component')
                    ->options([
                        'text' => 'Text',
                        'list' => 'List',
                        'contact' => 'Contact details',
                        'legal_disclaimer' => 'Legal Disclaimer',
                    ])
                    ->required()
                    ->live(),
                Select::make('publication_state')
                    ->label('Status')
                    ->options(['published' => 'Published', 'unpublished' => 'Unpublished'])
                    ->default('published')
                    ->required(),
                Grid::make()
                    ->columns(['md' => 2])
                    ->schema(fn (Get $get): array => match ($get('type')) {
                        'text' => [
                            TextInput::make('title')->label('Heading')->maxLength(160)->columnSpanFull(),
                            ...AdminRichText::schema('body', 'Rich Text', 20000),
                        ],
                        'list' => [
                            TextInput::make('title')->label('Heading')->maxLength(160)->columnSpanFull(),
                            ComponentListEditor::make(),
                        ],
                        'contact' => [
                            TextInput::make('title')->label('Heading')->maxLength(160)->columnSpanFull(),
                            Toggle::make('contact_email_enabled')->label('Public email')->default(true),
                            Toggle::make('contact_social_enabled')->label('Social links')->default(true)->live(),
                            Select::make('contact_social_platforms')
                                ->label('Social links from General')
                                ->options($platforms)
                                ->multiple()
                                ->default(array_keys($platforms))
                                ->visible(fn (Get $get): bool => (bool) $get('contact_social_enabled'))
                                ->columnSpanFull(),
                        ],
                        'legal_disclaimer' => [
                            Placeholder::make('legal_disclaimer_note')
                                ->label('Legal disclaimer from General')
                                ->content(fn (): string =>
                                    trim((string) PublicContentSetting::general()->getAttribute('legal_disclaimer'))
                                    ?: 'No legal disclaimer is configured in General.')
                                ->columnSpanFull(),
                        ],
                        default => [],
                    })
                    ->columnSpanFull(),
            ])
            ->columns(['md' => 2])
            ->defaultItems(0)
            ->itemLabel(fn (array $state): string => match ($state['type'] ?? '') {
                'text', 'list', 'contact' => trim((string) ($state['title'] ?? '')) ?: ucfirst((string) $state['type']),
                'legal_disclaimer' => 'Legal Disclaimer',
                default => 'Content',
            })
            ->collapsible()
            ->collapsed()
            ->addActionLabel('Add content beside image')
            ->reorderableWithButtons()
            ->reorderableWithDragAndDrop(false)
            ->visible(fn (Get $get): bool => (bool) $get('image_decorative'))
            ->columnSpanFull();
    }
}
