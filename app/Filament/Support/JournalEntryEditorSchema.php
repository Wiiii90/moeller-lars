<?php

namespace App\Filament\Support;

use App\Domain\Content\ExhibitionGeocodingService;
use App\Domain\Content\JournalEntryContent;
use Filament\Actions\Action;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\RichEditor;
use Filament\Forms\Components\RichEditor\RichEditorTool;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;
use Illuminate\Support\Str;

final class JournalEntryEditorSchema
{
    public static function blog(Schema $schema): Schema
    {
        return $schema->components(self::blogComponents());
    }

    public static function exhibition(Schema $schema): Schema
    {
        return $schema->components(self::exhibitionComponents());
    }

    /** @return array<int, mixed> */
    public static function blogComponents(): array
    {
        return [
            AdminForm::section('Entry')
                ->schema([
                    self::title(),
                    self::slug(220),
                    Textarea::make('excerpt')->maxLength(1000)->nullable()->columnSpanFull(),
                ])
                ->columns(2),
            self::contentSection(),
            self::mediaSection(),
        ];
    }

    /** @return array<int, mixed> */
    public static function exhibitionComponents(): array
    {
        return [
            AdminForm::section('Entry')
                ->schema([
                    self::title(),
                    self::slug(180),
                ])
                ->columns(2),
            self::contentSection(),
            self::mediaSection(),
            AdminForm::section('Exhibition details')
                ->schema([
                    DatePicker::make('starts_on')->label('Starts')->nullable(),
                    DatePicker::make('ends_on')->label('Ends')->afterOrEqual('starts_on')->nullable(),
                    TextInput::make('date_text')->label('Display date override')->maxLength(160)->nullable()->columnSpanFull(),
                    DateTimePicker::make('vernissage_at')->label('Vernissage')->seconds(false)->nullable()->columnSpanFull(),
                    TextInput::make('venue')->label('Venue')->maxLength(240)->nullable(),
                    TextInput::make('external_url')
                        ->label('Venue Website')
                        ->url()
                        ->maxLength(2048)
                        ->helperText('Optional link to the venue website.')
                        ->nullable(),
                    self::locationField('location_text', 'Address', 500),
                    self::locationField('city', 'City / location', 160),
                    self::locationField('country', 'Country', 160),
                    Hidden::make('latitude'),
                    Hidden::make('longitude'),
                    Hidden::make('geocoded_at'),
                ])
                ->columns(2),
        ];
    }

    private static function title(): TextInput
    {
        return TextInput::make('title')
            ->required()
            ->maxLength(240)
            ->live(onBlur: true)
            ->afterStateUpdated(function (?string $state, Set $set, Get $get): void {
                if (blank($get('slug')) && filled($state)) {
                    $set('slug', Str::slug($state));
                }
            });
    }

    private static function slug(int $max): TextInput
    {
        return TextInput::make('slug')
            ->label('Public URL slug')
            ->required()
            ->maxLength($max)
            ->regex('/^[a-z0-9]+(?:-[a-z0-9]+)*$/');
    }

    private static function contentSection(): mixed
    {
        $insertImage = RichEditorTool::make('insertJournalImage')
            ->label('Insert image')
            ->icon('heroicon-o-photo')
            ->action('customBlock', "{ id: '".JournalEntryContent::INLINE_IMAGE_BLOCK_ID."', mode: 'insert' }");

        return AdminForm::section('Content')->schema([
            RichEditor::make('content_blocks')
                ->label('Content')
                ->json()
                ->customBlocks([JournalInlineImageBlock::class])
                ->tools([$insertImage])
                ->toolbarButtons([
                    ['bold', 'italic', 'link'],
                    ['bulletList', 'orderedList'],
                    ['insertJournalImage'],
                    ['undo', 'redo'],
                ])
                ->extraAttributes(['class' => 'journal-entry-editor__content'])
                ->columnSpanFull(),
        ]);
    }

    private static function mediaSection(): mixed
    {
        return AdminForm::section('Media')->schema([
            MediaAssetSelect::makeId('cover_media_asset_id', 'Cover image', imagesOnly: true)
                ->nullable()
                ->columnSpanFull(),
            TextInput::make('cover_alt_text_override')
                ->label('Cover ALT override')
                ->maxLength(500)
                ->nullable()
                ->columnSpanFull(),
            Repeater::make('gallery_images')
                ->label('Gallery images')
                ->schema([
                    MediaAssetSelect::makeId('media_asset_id', 'Image', imagesOnly: true)->required(),
                    TextInput::make('alt_text_override')->label('ALT override')->maxLength(500)->nullable(),
                ])
                ->addActionLabel('Add image')
                ->reorderableWithButtons()
                ->reorderableWithDragAndDrop(false)
                ->columnSpanFull(),
        ]);
    }

    private static function locationField(string $name, string $label, int $max): TextInput
    {
        $field = TextInput::make($name)
            ->label($label)
            ->maxLength($max)
            ->live(onBlur: true)
            ->afterStateUpdated(function (Set $set): void {
                $set('latitude', null);
                $set('longitude', null);
                $set('geocoded_at', null);
            })
            ->nullable();

        if ($name !== 'location_text') {
            return $field;
        }

        return $field
            ->columnSpanFull()
            ->belowContent(
                Action::make('locateExhibitionOnMap')
                    ->label('Locate on map')
                    ->modalHidden()
                    ->action(function (Get $schemaGet, Set $schemaSet): void {
                        $parts = collect([
                            $schemaGet('location_text'),
                            $schemaGet('city'),
                            $schemaGet('country'),
                        ])->filter(static fn (mixed $value): bool => is_string($value) && trim($value) !== '')
                            ->map(static fn (string $value): string => trim($value))
                            ->unique()
                            ->values()
                            ->all();

                        if ($parts === []) {
                            Notification::make()
                                ->title('Enter an address before locating it on the map')
                                ->warning()
                                ->send();
                            return;
                        }

                        $match = app(ExhibitionGeocodingService::class)->locate(implode(', ', $parts));
                        if ($match === null) {
                            Notification::make()
                                ->title('Map location not found')
                                ->body('The address was not changed. You can edit it and try again.')
                                ->warning()
                                ->send();
                            return;
                        }

                        $schemaSet('latitude', $match['latitude']);
                        $schemaSet('longitude', $match['longitude']);
                        $schemaSet('geocoded_at', now()->toIso8601String());

                        Notification::make()
                            ->title('Map location set')
                            ->body($match['label'])
                            ->success()
                            ->send();
                    }),
            );
    }
}
