<?php

namespace App\Filament\Support;

use App\Domain\Content\ExhibitionMapAddress;
use App\Filament\Support\AdminIcon;
use Filament\Actions\Action;
use Livewire\Component;
use App\Domain\Content\RegionalFormats;
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

    public static function exhibition(Schema $schema, ?Exhibition $exhibition = null): Schema
    {
        return self::entry($schema, JournalTemplate::Exhibitions, $exhibition);
    }

    private static function entry(Schema $schema, JournalTemplate $template, ?Exhibition $exhibition = null): Schema
    {
        $components = [self::basicsSection($template)];

        if ($template === JournalTemplate::Exhibitions) {
            $components[] = self::venueSection($exhibition);
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
            $schema[] = Textarea::make('excerpt')->label(__('Excerpt'))->maxLength(1000)->nullable()->columnSpanFull();
        }

        $cover = $template === JournalTemplate::Exhibitions
            ? MediaAssetSelect::makeId(
                'cover_media_asset_id',
                __('Cover image or video'),
                allowedMimeTypes: [...MediaTypePolicy::IMAGE_MIME_TYPES, ...MediaTypePolicy::VIDEO_MIME_TYPES],
            )->placeholder(__('No cover media'))
            : MediaAssetSelect::makeId('cover_media_asset_id', __('Cover image'), imagesOnly: true)
                ->placeholder(__('No cover image'));

        $schema[] = $cover->nullable()->columnSpanFull();

        return AdminForm::section(__('Basics'))->schema($schema)->columns(['md' => 2]);
    }

    private static function venueSection(?Exhibition $exhibition): mixed
    {
        return AdminForm::section(__('Organizer & location'))->schema([
            self::publicLocationField('organizer', 'Organizer / gallery', 240, $exhibition)->columnSpan(['default' => 1, 'md' => 3]),
            self::publicLocationField('venue', 'Exhibition venue (actual location)', 240, $exhibition)->columnSpan(['default' => 1, 'md' => 3]),
            self::publicLocationField('external_url', 'Exhibition website', 2048, $exhibition)->url()->columnSpan(['default' => 1, 'md' => 3]),
            self::publicLocationField('location_text', 'Street address', 500, $exhibition)->columnSpan(['default' => 1, 'md' => 2]),
            self::publicLocationField('postal_code', 'Postal code', 16, $exhibition),
            self::publicLocationField('city', 'City', 160, $exhibition)->columnSpan(['default' => 1, 'md' => 2]),
            self::publicLocationField('country', 'Country', 160, $exhibition),
        ])->columns(['md' => 6]);
    }

    private static function datesSection(): mixed
    {
        return AdminForm::section(__('Dates'))->schema([
            DatePicker::make('starts_on')->label(__('Starts'))->native(false)->displayFormat(fn (): string => RegionalFormats::pickerDateFormat())->nullable(),
            DatePicker::make('ends_on')->label(__('Ends'))->native(false)->displayFormat(fn (): string => RegionalFormats::pickerDateFormat())->afterOrEqual('starts_on')->nullable(),
            TextInput::make('date_text')->label(__('Display date override'))->maxLength(160)->nullable()->columnSpanFull(),
            Toggle::make('show_vernissage')->label(__('Show vernissage until it begins'))->default(false)->columnSpanFull(),
            DateTimePicker::make('vernissage_at')->label(__('Vernissage'))->native(false)->displayFormat(fn (): string => RegionalFormats::pickerDateTimeFormat())->seconds(false)->default(null)->nullable()->columnSpanFull(),
        ])->columns(['md' => 2]);
    }

    private static function gallerySection(JournalTemplate $template): mixed
    {
        $images = self::galleryImages();

        if ($template === JournalTemplate::Blog) {
            return AdminForm::section(__('Gallery'))->schema([
                Select::make('gallery_presentation')
                    ->label(__('Gallery presentation'))
                    ->options([
                        'sequence' => __('Media sequence'),
                        'grid' => __('Grid'),
                        'mosaic' => __('Mosaic'),
                        'slideshow' => __('Slideshow'),
                    ])
                    ->default('sequence')
                    ->required(),
                $images,
            ]);
        }

        $images
            ->visible(fn (Get $get): bool => (bool) $get('gallery_enabled'))
            ->dehydrated(fn (Get $get): bool => (bool) $get('gallery_enabled'));

        return AdminForm::section(__('Gallery'))->schema([
            Toggle::make('gallery_enabled')
                ->label(__('Gallery enabled'))
                ->live()
                ->columnSpanFull(),
            Select::make('gallery_presentation')
                ->label(__('Gallery presentation'))
                ->options([
                    'grid' => __('Grid'),
                    'mosaic' => __('Mosaic'),
                    'slideshow' => __('Slideshow'),
                ])
                ->default('grid')
                ->required()
                ->visible(fn (Get $get): bool => (bool) $get('gallery_enabled')),
            $images,
        ]);
    }

    private static function mapSection(): mixed
    {
        return AdminForm::section(__('Map'))->schema([
            Toggle::make('map_enabled')
                ->label(__('Map enabled'))
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
                ->hint(AdminHelp::make(__('Map location help'), __('Requires Country and either City or Postal code.')))
                ->columnSpanFull(),
            Hidden::make('latitude')->dehydrated(false),
            Hidden::make('longitude')->dehydrated(false),
            Hidden::make('geocoded_at')->dehydrated(false),
            Placeholder::make('map_preview')
                ->label(__('Preview'))
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
            ->label(__('Public URL slug'))
            ->required()
            ->maxLength($max)
            ->regex('/^[a-z0-9]+(?:-[a-z0-9]+)*$/');
    }

    private static function textSection(string $name): mixed
    {
        return AdminForm::section(__('Text'))->schema([
            ...AdminRichText::schema($name, __('Text'), 50000, allowEmbeddedMedia: true),
        ]);
    }

    private static function galleryImages(): Repeater
    {
        return Repeater::make('gallery_images')
            ->label(__('Gallery media'))
            ->schema([
                MediaAssetSelect::makeId(
                    'media_asset_id',
                    __('Image or video'),
                    allowedMimeTypes: [...MediaTypePolicy::IMAGE_MIME_TYPES, ...MediaTypePolicy::VIDEO_MIME_TYPES],
                )->required(),
            ])
            ->addActionLabel(__('Add media'))
            ->reorderableWithButtons()
            ->reorderableWithDragAndDrop(false)
            ->columnSpanFull();
    }

    private static function publicLocationField(string $name, string $label, int $max, ?Exhibition $exhibition): TextInput
    {
        $field = TextInput::make($name)
            ->label(__($label))
            ->maxLength($max)
            ->nullable();

        if (! $exhibition instanceof Exhibition) {
            // New exhibitions retain the default-visible policy. Discrete
            // visibility controls appear once the draft has been created.
            return $field;
        }

        $visible = static fn (): bool => $exhibition->fresh()->publicFieldVisible($name);

        return $field->hintAction(
            Action::make('togglePublicVisibility_'.$name)
                ->label(static fn (): string => $visible()
                    ? __('Hide from public page')
                    : __('Show on public page'))
                ->icon(static fn (): string => $visible()
                    ? AdminIcon::Unpublish->value
                    : AdminIcon::Publish->value)
                ->color('gray')
                ->iconButton()
                ->action(static function (Component $livewire) use ($exhibition, $name, $visible): void {
                    /** @var \App\Filament\Pages\JournalWorkspace $livewire */
                    $livewire->setExhibitionFieldVisibility((int) $exhibition->getKey(), $name, ! $visible());
                })
                // As for shared Edit header actions: retain the mounted Editor,
                // and render the new icon/visibility state without a reload.
                ->after(static function (Action $action): void {
                    $action->getLivewire()->forceRender();
                    $action->halt();
                }),
        );
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
