<?php

namespace App\Filament\Resources\CustomPageSettings;

use App\Domain\Content\SocialLinks;
use App\Filament\Resources\CustomPageSettings\Pages\EditCustomPageSetting;
use App\Models\CustomPageSetting;
use App\Models\MediaAsset;
use Filament\Forms\Components\MarkdownEditor;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Fieldset;
use Filament\Schemas\Schema;
use Illuminate\Database\Eloquent\Model;

final class CustomPageSettingResource extends Resource
{
    protected static ?string $model = CustomPageSetting::class;

    protected static bool $shouldRegisterNavigation = false;

    protected static ?string $modelLabel = 'custom page';

    protected static ?string $pluralModelLabel = 'custom pages';

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Fieldset::make('Page components')
                ->contained(false)
                ->extraAttributes(['class' => 'artist-editor-form-section'])
                ->schema([
                    Repeater::make('blocks')
                        ->label('Components')
                        ->extraAttributes(['class' => 'artist-component-repeater'])
                        ->schema([
                            Select::make('type')
                                ->options([
                                    'text' => 'Text',
                                    'list' => 'List',
                                    'contact' => 'Contact',
                                ])
                                ->required()
                                ->live(),
                            Toggle::make('divider')
                                ->label('Divider after this component')
                                ->default(true),
                            TextInput::make('title')
                                ->label('Component heading')
                                ->maxLength(160)
                                ->visible(fn (callable $get): bool => in_array($get('type'), ['text', 'list'], true)),
                            MarkdownEditor::make('body')
                                ->label('Text')
                                ->toolbarButtons([
                                    ['bold', 'italic', 'link'],
                                    ['bulletList', 'orderedList'],
                                    ['undo', 'redo'],
                                ])
                                ->maxLength(20000)
                                ->columnSpanFull()
                                ->visible(fn (callable $get): bool => $get('type') === 'text'),
                            Select::make('media_asset_id')
                                ->label('Image on the right')
                                ->options(fn (): array => MediaAsset::query()
                                    ->where('state', 'available')
                                    ->where('mime_type', 'like', 'image/%')
                                    ->orderBy('original_filename')
                                    ->pluck('original_filename', 'id')
                                    ->all())
                                ->searchable()
                                ->nullable()
                                ->visible(fn (callable $get): bool => in_array($get('type'), ['text', 'list'], true)),
                            Repeater::make('items')
                                ->label('List entries')
                                ->extraAttributes(['class' => 'artist-component-repeater artist-component-repeater--nested'])
                                ->schema([
                                    Toggle::make('visible')
                                        ->label('Visible on public page')
                                        ->default(true),
                                    TextInput::make('date')->label('Date / year')->maxLength(120),
                                    TextInput::make('title')->required()->maxLength(240),
                                    TextInput::make('meta')->label('Organisation / context')->maxLength(240),
                                    TextInput::make('location')->maxLength(240),
                                    TextInput::make('url')
                                        ->label('Optional link')
                                        ->url()
                                        ->maxLength(2048),
                                    MarkdownEditor::make('body')
                                        ->label('Details')
                                        ->toolbarButtons([
                                            ['bold', 'italic', 'link'],
                                            ['bulletList', 'orderedList'],
                                            ['undo', 'redo'],
                                        ])
                                        ->maxLength(10000)
                                        ->columnSpanFull(),
                                ])
                                ->columns(2)
                                ->defaultItems(0)
                                ->reorderableWithButtons()
                                ->reorderableWithDragAndDrop(false)
                                ->itemLabel(fn (array $state): ?string => isset($state['title']) && is_string($state['title']) ? $state['title'] : null)
                                ->columnSpanFull()
                                ->visible(fn (callable $get): bool => $get('type') === 'list'),
                            Toggle::make('show_email')
                                ->label('Show public email from General')
                                ->default(true)
                                ->visible(fn (callable $get): bool => $get('type') === 'contact'),
                            Toggle::make('show_form')
                                ->label('Show contact form')
                                ->default(true)
                                ->visible(fn (callable $get): bool => $get('type') === 'contact'),
                            Select::make('social_platforms')
                                ->label('Social links from General')
                                ->options(SocialLinks::options())
                                ->multiple()
                                ->default(array_keys(SocialLinks::options()))
                                ->visible(fn (callable $get): bool => $get('type') === 'contact'),
                        ])
                        ->columns(2)
                        ->defaultItems(0)
                        ->reorderableWithButtons()
                        ->reorderableWithDragAndDrop(false)
                        ->itemLabel(function (array $state): string {
                            $type = $state['type'] ?? null;
                            $title = $state['title'] ?? null;
                            if (is_string($title) && trim($title) !== '') {
                                return $title;
                            }

                            return match ($type) {
                                'text' => 'Text',
                                'list' => 'List',
                                'contact' => 'Contact',
                                default => 'Component',
                            };
                        })
                        ->columnSpanFull(),
                ]),
        ]);
    }

    public static function getPages(): array
    {
        return [
            'edit' => EditCustomPageSetting::route('/{record}/edit'),
        ];
    }

    public static function canCreate(): bool
    {
        return false;
    }

    public static function canDelete(Model $record): bool
    {
        return false;
    }
}
