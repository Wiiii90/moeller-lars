<?php

namespace App\Filament\Support;

use App\Domain\Content\ExhibitionMapAddress;
use App\Domain\Content\ExhibitionMapPresentation;
use App\Domain\Content\JournalTemplate;
use App\Domain\Media\MediaTypePolicy;
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
        return self::entry($schema, JournalTemplate::Blog);
    }

    public static function exhibition(Schema $schema): Schema
    {
        return self::entry($schema, JournalTemplate::Exhibitions);
    }

    private static function entry(Schema $schema, JournalTemplate $template): Schema
    {
        $components = [self::basicsSection($template)];

        if ($template === JournalTemplate::Exhibitions) {
            $components[] = self::venueSection();
        }

        $components[] = self::textSection($template === JournalTemplate::Blog ? 'body' : 'description');

        if ($template === JournalTemplate::Exhibitions) {
            $components[] = self::datesSection();
        }

        $components[] = self::gallerySection($template);

        if ($template === JournalTemplate::Exhibitions) {
            $components[] = self::mapSection();
        }

        return $schema->components($components);
    }

    private static function basicsSection(JournalTemplate $template): mixed
    {
        $schema = [
            self::title(),
            self::slug($template === JournalTemplate::Blog ? 220 : 180),
        ];

        if ($template === JournalTemplate::Blog) {
            $schema[] = Textarea::make('excerpt')->label('Excerpt')->maxLength(1000)->nullable()->columnSpanFull();
        }

        $cover = $template === JournalTemplate::Exhibitions
            ? MediaAssetSelect::makeId(
                'cover_media_asset_id',
                'Cover image or video',
                allowedMimeTypes: [...MediaTypePolicy::IMAGE_MIME_TYPES, ...MediaTypePolicy::VIDEO_MIME_TYPES],
            )->placeholder('No cover media')
            : MediaAssetSelect::makeId('cover_media_asset_id', 'Cover image', imagesOnly: true)
                ->placeholder('No cover image');

        $schema[] = $cover->nullable()->columnSpanFull();

        return AdminForm::section('Basics')->schema($schema)->columns(['md' => 2]);
    }

    private static function venueSection(): mixed
    {
        return AdminForm::section('Venue')->schema([
            TextInput::make('venue')->label('Venue')->maxLength(240)->nullable()->columnSpan(['default' => 1, 'md' => 3]),
            TextInput::make('external_url')->label('Venue website')->url()->maxLength(2048)->nullable()->columnSpan(['default' => 1, 'md' => 3]),
            self::locationField('location_text', 'Street address', 500)->columnSpan(['default' => 1, 'md' => 2]),
            self::locationField('postal_code', 'Postal code', 16),
            self::locationField('city', 'City', 160)->columnSpan(['default' => 1, 'md' => 2]),
            self::locationField('country', 'Country', 160),
        ])->columns(['md' => 6]);
    }

    private static function datesSection(): mixed
    {
        return AdminForm::section('Dates')->schema([
            DatePicker::make('starts_on')->label('Starts')->nullable(),
            DatePicker::make('ends_on')->label('Ends')->afterOrEqual('starts_on')->nullable(),
            TextInput::make('date_text')->label('Display date override')->maxLength(160)->nullable()->columnSpanFull(),
            DateTimePicker::make('vernissage_at')->label('Vernissage')->seconds(false)->nullable()->columnSpanFull(),
        ])->columns(['md' => 2]);
    }

    private static function gallerySection(JournalTemplate $template): mixed
    {
        $images = self::galleryImages();

        if ($template === JournalTemplate::Blog) {
            return AdminForm::section('Gallery')->schema([
                Select::make('gallery_presentation')
                    ->label('Gallery presentation')
                    ->options([
                        'sequence' => 'Media sequence',
                        'grid' => 'Grid',
                        'mosaic' => 'Mosaic',
                        'slideshow' => 'Slideshow',
                    ])
                    ->default('sequence')
                    ->required(),
                $images,
            ]);
        }

        $images
            ->visible(fn (Get $get): bool => (bool) $get('gallery_enabled'))
            ->dehydrated(fn (Get $get): bool => (bool) $get('gallery_enabled'));

        return AdminForm::section('Gallery')->schema([
            Toggle::make('gallery_enabled')
                ->label('Gallery enabled')
                ->live()
                ->columnSpanFull(),
            Select::make('gallery_presentation')
                ->label('Gallery presentation')
                ->options([
                    'grid' => 'Grid',
                    'mosaic' => 'Mosaic',
                    'slideshow' => 'Slideshow',
                ])
                ->default('grid')
                ->required()
                ->visible(fn (Get $get): bool => (bool) $get('gallery_enabled')),
            $images,
        ]);
    }

    private static function mapSection(): mixed
    {
        return AdminForm::section('Map')->schema([
            Toggle::make('map_enabled')
                ->label('Map enabled')
                // The central Edit change event is the only persistence request.
                // A second eager Livewire request can race with the first
                // enable immediately after a newly entered country is saved.
                // Enabled maps remain switchable off, even if older records
                // lack a complete address. New maps need an identifiable place.
                ->disabled(fn (Get $get): bool => ! (bool) $get('map_enabled')
                    && ! ExhibitionMapAddress::isSufficient([
                        'postal_code' => $get('postal_code'),
                        'city' => $get('city'),
                        'country' => $get('country'),
                    ]))
                ->helperText('Requires Country and either City or Postal code.')
                ->columnSpanFull(),
            Hidden::make('latitude')->dehydrated(false),
            Hidden::make('longitude')->dehydrated(false),
            Hidden::make('geocoded_at')->dehydrated(false),
            Placeholder::make('map_preview')
                ->label('Preview')
                ->content(fn (Get $get): HtmlString => self::mapPreview($get))
                ->visible(fn (Get $get): bool => (bool) $get('map_enabled'))
                ->columnSpanFull(),
        ])->columns(['md' => 2]);
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
            ->label('Gallery media')
            ->schema([
                MediaAssetSelect::makeId(
                    'media_asset_id',
                    'Image or video',
                    allowedMimeTypes: [...MediaTypePolicy::IMAGE_MIME_TYPES, ...MediaTypePolicy::VIDEO_MIME_TYPES],
                )->required(),
            ])
            ->addActionLabel('Add media')
            ->reorderableWithButtons()
            ->reorderableWithDragAndDrop(false)
            ->columnSpanFull();
    }

    private static function locationField(string $name, string $label, int $max): TextInput
    {
        return TextInput::make($name)
            ->label($label)
            ->maxLength($max)
            ->nullable();
    }

    private static function mapPreview(Get $get): HtmlString
    {
        $latitude = filter_var($get('latitude'), FILTER_VALIDATE_FLOAT);
        $longitude = filter_var($get('longitude'), FILTER_VALIDATE_FLOAT);
        if ($latitude === false || $longitude === false || $latitude < -90 || $latitude > 90 || $longitude < -180 || $longitude > 180) {
            return new HtmlString(
                '<div class="journal-entry-editor__map is-empty">'
                .'<p>Map location will appear once the address has been resolved.</p>'
                .'</div>',
            );
        }

        $preview = new Exhibition;
        $preview->setAttribute('latitude', (float) $latitude);
        $preview->setAttribute('longitude', (float) $longitude);
        $presentation = app(ExhibitionMapPresentation::class)->for($preview);
        if ($presentation === null) {
            return new HtmlString('<div class="journal-entry-editor__map is-empty"><p>Map preview unavailable.</p></div>');
        }
        return new HtmlString(
            '<div class="journal-entry-editor__map">'
            .'<iframe src="'.e($presentation['embed_url']).'" title="Venue map preview" loading="lazy" referrerpolicy="no-referrer-when-downgrade"></iframe>'
            .'<div class="journal-entry-editor__map-meta">'
            .'<a href="'.e($presentation['public_url']).'" target="_blank" rel="noopener noreferrer">Open map</a></div>'
            .'</div>',
        );
    }
}
