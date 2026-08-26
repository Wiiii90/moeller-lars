<?php

namespace App\Filament\Support;

use App\Domain\Content\ExhibitionMapPresentation;
use App\Models\Exhibition;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
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
                MediaAssetSelect::makeId('cover_media_asset_id', 'Cover image', imagesOnly: true)
                    ->nullable()
                    ->columnSpanFull(),
            ])->columns(2),
            self::textSection('body'),
            AdminForm::section('Gallery')->schema([
                self::galleryImages(),
            ]),
        ]);
    }

    public static function exhibition(Schema $schema): Schema
    {
        return $schema->components([
            AdminForm::section('Basics')->schema([
                self::title(),
                self::slug(180),
                MediaAssetSelect::makeId('cover_media_asset_id', 'Cover image', imagesOnly: true)
                    ->nullable()
                    ->columnSpanFull(),
            ])->columns(2),

            AdminForm::section('Venue')->schema([
                TextInput::make('venue')->label('Venue')->maxLength(240)->nullable()->columnSpan(3),
                TextInput::make('external_url')->label('Venue website')->url()->maxLength(2048)->nullable()->columnSpan(3),
                self::locationField('location_text', 'Street address', 500)->columnSpan(2),
                self::locationField('city', 'City', 160)->columnSpan(2),
                self::locationField('country', 'Country', 160)->columnSpan(2),
            ])->columns(6),

            self::textSection('description'),

            AdminForm::section('Dates')->schema([
                DatePicker::make('starts_on')->label('Starts')->nullable(),
                DatePicker::make('ends_on')->label('Ends')->afterOrEqual('starts_on')->nullable(),
                TextInput::make('date_text')->label('Display date override')->maxLength(160)->nullable()->columnSpanFull(),
                DateTimePicker::make('vernissage_at')->label('Vernissage')->seconds(false)->nullable()->columnSpanFull(),
            ])->columns(2),

            AdminForm::section('Gallery')->schema([
                Toggle::make('gallery_enabled')
                    ->label('Gallery enabled')
                    ->live()
                    ->columnSpanFull(),
                self::galleryImages()
                    ->visible(fn (Get $get): bool => (bool) $get('gallery_enabled'))
                    ->dehydrated(fn (Get $get): bool => (bool) $get('gallery_enabled')),
            ]),

            AdminForm::section('Map')->schema([
                Toggle::make('map_enabled')
                    ->label('Map enabled')
                    ->live()
                    ->columnSpanFull(),
                Select::make('map_shape')
                    ->label('Map shape')
                    ->options(['wide' => 'Wide', 'square' => 'Square'])
                    ->default('wide')
                    ->required()
                    ->live()
                    ->visible(fn (Get $get): bool => (bool) $get('map_enabled')),
                Hidden::make('latitude')->dehydrated(false),
                Hidden::make('longitude')->dehydrated(false),
                Hidden::make('geocoded_at')->dehydrated(false),
                Placeholder::make('map_preview')
                    ->label('Preview')
                    ->content(fn (Get $get): HtmlString => self::mapPreview($get))
                    ->visible(fn (Get $get): bool => (bool) $get('map_enabled'))
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

    private static function textSection(string $name): mixed
    {
        return AdminForm::section('Text')->schema([
            ...AdminRichText::schema($name, 'Text', 50000, allowEmbeddedMedia: true),
        ]);
    }

    private static function galleryImages(): Repeater
    {
        return Repeater::make('gallery_images')
            ->label('Gallery images')
            ->schema([
                MediaAssetSelect::makeId('media_asset_id', 'Image', imagesOnly: true)->required(),
            ])
            ->addActionLabel('Add image')
            ->reorderableWithButtons()
            ->reorderableWithDragAndDrop(false)
            ->columnSpanFull();
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

    private static function mapPreview(Get $get): HtmlString
    {
        $latitude = filter_var($get('latitude'), FILTER_VALIDATE_FLOAT);
        $longitude = filter_var($get('longitude'), FILTER_VALIDATE_FLOAT);
        if ($latitude === false || $longitude === false || $latitude < -90 || $latitude > 90 || $longitude < -180 || $longitude > 180) {
            return new HtmlString(
                '<div class="journal-entry-editor__map is-empty">'
                .'<p>The map location will be resolved from Street address, City and Country when you save.</p>'
                .'</div>',
            );
        }

        $preview = new Exhibition;
        $preview->setAttribute('latitude', (float) $latitude);
        $preview->setAttribute('longitude', (float) $longitude);
        $preview->setAttribute('map_shape', (string) ($get('map_shape') ?? 'wide'));
        $presentation = app(ExhibitionMapPresentation::class)->for($preview);
        if ($presentation === null) {
            return new HtmlString('<div class="journal-entry-editor__map is-empty"><p>Map preview unavailable.</p></div>');
        }
        $aspect = app(ExhibitionMapPresentation::class)->aspectRatio($presentation['shape']);
        $key = sha1($latitude.'|'.$longitude.'|'.(string) ($get('geocoded_at') ?? '').'|'.$presentation['shape']);

        return new HtmlString(
            '<div wire:key="journal-map-'.$key.'" x-data="{ stale: false }" x-on:journal-location-stale.window="stale = true">'
            .'<div class="journal-entry-editor__map" x-show="! stale">'
            .'<iframe src="'.e($presentation['embed_url']).'" title="Venue map preview" loading="lazy" referrerpolicy="no-referrer-when-downgrade" style="aspect-ratio:'.e($aspect).';height:auto;min-height:16rem"></iframe>'
            .'<div class="journal-entry-editor__map-meta"><span>'.e(ucfirst($presentation['shape'])).' map</span>'
            .'<a href="'.e($presentation['public_url']).'" target="_blank" rel="noopener noreferrer">Open map</a></div>'
            .'</div>'
            .'<div class="journal-entry-editor__map is-empty" x-show="stale" x-cloak>'
            .'<p>Address changed. The map location will be refreshed when you save.</p>'
            .'</div>'
            .'</div>',
        );
    }
}
