<?php

namespace App\Filament\Support;

use App\Domain\Content\ExhibitionGeocodingService;
use App\Models\Exhibition;
use Filament\Actions\Action;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;
use Illuminate\Support\HtmlString;
use Illuminate\Support\Str;

final class JournalEntryEditorSchema
{
    public static function blog(Schema $schema): Schema
    {
        return $schema->components([
            AdminForm::section('Basics')->schema([
                self::title(),
                self::slug(220),
                Textarea::make('excerpt')->label('Excerpt')->maxLength(1000)->nullable()->columnSpanFull(),
            ])->columns(2),
            self::textSection('body'),
            self::imagesSection(),
        ]);
    }

    public static function exhibition(Schema $schema): Schema
    {
        return $schema->components([
            AdminForm::section('Basics')->schema([
                self::title(),
                self::slug(180),
            ])->columns(2),
            self::textSection('description'),
            self::imagesSection(),
            AdminForm::section('Dates and venue')->schema([
                DatePicker::make('starts_on')->label('Starts')->nullable(),
                DatePicker::make('ends_on')->label('Ends')->afterOrEqual('starts_on')->nullable(),
                TextInput::make('date_text')->label('Display date override')->maxLength(160)->nullable()->columnSpanFull(),
                DateTimePicker::make('vernissage_at')->label('Vernissage')->seconds(false)->nullable()->columnSpanFull(),
                TextInput::make('venue')->label('Venue')->maxLength(240)->nullable(),
                TextInput::make('external_url')->label('Venue website')->url()->maxLength(2048)->nullable(),
                self::locationField('location_text', 'Street address', 500)->columnSpanFull()->belowContent(self::findLocationAction()),
                self::locationField('city', 'City', 160),
                self::locationField('country', 'Country', 160),
                Hidden::make('latitude'),
                Hidden::make('longitude'),
                Hidden::make('geocoded_at'),
                Placeholder::make('map_preview')
                    ->label('Map')
                    ->content(fn (Get $get): HtmlString => self::mapPreview($get))
                    ->columnSpanFull(),
            ])->columns(2),
        ]);
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

    private static function textSection(string $field): mixed
    {
        return AdminForm::section('Text')->schema([
            ...AdminRichText::schema($field, 'Text', null),
        ]);
    }

    private static function imagesSection(): mixed
    {
        return AdminForm::section('Images')->schema([
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
        return TextInput::make($name)
            ->label($label)
            ->maxLength($max)
            ->nullable()
            ->afterStateUpdatedJs(<<<'JS'
                $set('latitude', null)
                $set('longitude', null)
                $set('geocoded_at', null)
                window.dispatchEvent(new CustomEvent('journal-location-stale'))
            JS);
    }

    private static function findLocationAction(): Action
    {
        return Action::make('findExhibitionLocation')
            ->label('Find location')
            ->icon('heroicon-o-map-pin')
            ->modalHidden()
            ->action(function (Get $schemaGet, Set $schemaSet): void {
                $parts = collect([
                    $schemaGet('location_text'),
                    $schemaGet('city'),
                    $schemaGet('country'),
                ])->filter(static fn (mixed $value): bool => is_string($value) && trim($value) !== '')
                    ->map(static fn (string $value): string => trim($value))
                    ->values()
                    ->all();

                if ($parts === []) {
                    Notification::make()
                        ->title('Enter a street address, city or country first')
                        ->warning()
                        ->send();
                    return;
                }

                $match = app(ExhibitionGeocodingService::class)->locate(implode(', ', $parts));
                if ($match === null) {
                    Notification::make()
                        ->title('Location not found')
                        ->body('The address was not changed. Adjust it and try again.')
                        ->warning()
                        ->send();
                    return;
                }

                $schemaSet('latitude', $match['latitude']);
                $schemaSet('longitude', $match['longitude']);
                $schemaSet('geocoded_at', now()->toIso8601String());
                Notification::make()->title('Map location set')->success()->send();
            });
    }

    private static function mapPreview(Get $get): HtmlString
    {
        $latitude = filter_var($get('latitude'), FILTER_VALIDATE_FLOAT);
        $longitude = filter_var($get('longitude'), FILTER_VALIDATE_FLOAT);
        $mapKey = 'journal-map-'.hash('sha256', implode('|', [
            $latitude === false ? '' : (string) $latitude,
            $longitude === false ? '' : (string) $longitude,
            (string) ($get('geocoded_at') ?? ''),
        ]));

        if ($latitude === false || $longitude === false || $latitude < -90 || $latitude > 90 || $longitude < -180 || $longitude > 180) {
            return new HtmlString(
                '<div wire:key="'.e($mapKey).'" x-data="{ stale: false }" x-on:journal-location-stale.window="stale = true">'
                .'<div class="journal-entry-editor__map is-empty">'
                .'<p>No map location set. Enter the venue address and use <strong>Find location</strong>.</p>'
                .'</div>'
                .'</div>',
            );
        }

        $preview = new Exhibition;
        $preview->setAttribute('latitude', (float) $latitude);
        $preview->setAttribute('longitude', (float) $longitude);
        $embedUrl = $preview->mapEmbedUrl();
        $mapUrl = $preview->publicMapUrl();

        return new HtmlString(
            '<div wire:key="'.e($mapKey).'" x-data="{ stale: false }" x-on:journal-location-stale.window="stale = true">'
            .'<div class="journal-entry-editor__map" x-show="! stale">'
            .'<iframe src="'.e((string) $embedUrl).'" title="Venue map preview" loading="lazy" referrerpolicy="no-referrer-when-downgrade"></iframe>'
            .'<div class="journal-entry-editor__map-meta"><span>Map location set</span>'
            .'<a href="'.e((string) $mapUrl).'" target="_blank" rel="noopener noreferrer">Open map</a></div>'
            .'</div>'
            .'<div class="journal-entry-editor__map is-empty" x-show="stale" x-cloak>'
            .'<p>Address changed. Use <strong>Find location</strong> to update the map.</p>'
            .'</div>'
            .'</div>',
        );
    }
}
