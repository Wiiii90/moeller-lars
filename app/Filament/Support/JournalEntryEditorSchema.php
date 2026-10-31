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
use Filament\Forms\Components\Repeater\TableColumn;
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
        if ($template === JournalTemplate::Exhibitions) {
            return $schema
                ->components([
                    ...self::basicsFields($template),
                    ...self::dateFields(),
                    self::imageViewerField(),
                    ...self::venueFields($exhibition),
                    ...AdminRichText::schema('description', __('Text'), 50000, allowEmbeddedMedia: true),
                    ...self::exhibitionGalleryFields(),
                    ...self::mapFields(),
                ])
                ->columns(['md' => 2]);
        }

        return $schema->components([
            self::basicsSection($template),
            self::textSection('body'),
            self::gallerySection(),
        ]);
    }

    private static function basicsSection(JournalTemplate $template): mixed
    {
        return AdminForm::section(__('Basics'))->schema(self::basicsFields($template))->columns(['md' => 2]);
    }

    private static function basicsFields(JournalTemplate $template): array
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

        if ($template === JournalTemplate::Exhibitions) {
            $schema[] = self::videoControlsToggle('cover_video_controls_enabled')->columnSpanFull();
        }

        if ($template === JournalTemplate::Blog) {
            $schema[] = self::imageViewerField();
        }

        return $schema;
    }

    private static function imageViewerField(): Toggle
    {
        return Toggle::make('image_viewer_enabled')
            ->label(__('Enlarge images on click'))
            ->default(false)
            ->columnSpanFull();
    }

    private static function venueFields(?Exhibition $exhibition): array
    {
        return [
            self::publicLocationField('organizer', 'Organizer', 240, $exhibition)->columnSpanFull(),
            self::publicLocationField('venue', 'Venue', 240, $exhibition)->columnSpanFull(),
            self::publicLocationField('external_url', 'Exhibition website', 2048, $exhibition)->url()->columnSpanFull(),
            self::publicLocationField('location_text', 'Street address', 500, $exhibition)->columnSpanFull(),
            self::publicLocationField('city', 'City', 160, $exhibition),
            self::publicLocationField('postal_code', 'Postal code', 16, $exhibition),
            self::publicLocationField('country', 'Country', 160, $exhibition)->columnSpanFull(),
        ];
    }

    private static function dateFields(): array
    {
        return [
            DatePicker::make('starts_on')->label(__('Starts'))->native(false)->displayFormat(fn (): string => RegionalFormats::pickerDateFormat())->nullable(),
            DatePicker::make('ends_on')->label(__('Ends'))->native(false)->displayFormat(fn (): string => RegionalFormats::pickerDateFormat())->afterOrEqual('starts_on')->nullable(),
            TextInput::make('date_text')->label(__('Display date override'))->maxLength(160)->nullable()->columnSpanFull(),
            Toggle::make('show_vernissage')->label(__('Show vernissage until it begins'))->default(false)->columnSpanFull(),
            DateTimePicker::make('vernissage_at')->label(__('Vernissage'))->native(false)->displayFormat(fn (): string => RegionalFormats::pickerDateTimeFormat())->seconds(false)->default(null)->nullable()->columnSpanFull(),
        ];
    }

    private static function gallerySection(): mixed
    {
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
            self::galleryImages(),
        ]);
    }

    private static function exhibitionGalleryFields(): array
    {
        $images = self::galleryImages(table: true);
        $images
            ->visible(fn (Get $get): bool => (bool) $get('gallery_enabled'))
            ->dehydrated(fn (Get $get): bool => (bool) $get('gallery_enabled'));

        return [
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
                ->visible(fn (Get $get): bool => (bool) $get('gallery_enabled'))
                ->columnSpanFull(),
            $images,
        ];
    }

    private static function mapFields(): array
    {
        return [
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

    private static function videoControlsToggle(string $name): Toggle
    {
        return Toggle::make($name)
            ->label(__('Show video playback controls'))
            ->default(true);
    }

    private static function galleryImages(bool $table = false): Repeater
    {
        if (! $table) {
            return Repeater::make('gallery_images')
                ->label(__('Gallery media'))
                ->schema([
                    MediaAssetSelect::makeId(
                        'media_asset_id',
                        __('Image or video'),
                        allowedMimeTypes: [...MediaTypePolicy::IMAGE_MIME_TYPES, ...MediaTypePolicy::VIDEO_MIME_TYPES],
                    )->required(),
                    self::videoControlsToggle('video_controls_enabled'),
                ])
                ->addActionLabel(__('Add media'))
                ->reorderableWithButtons()
                ->reorderableWithDragAndDrop(false)
                ->columnSpanFull();
        }

        return AdminTableRepeater::make('gallery_images')
            ->label(__('Gallery media'))
            ->hiddenLabel()
            ->table([TableColumn::make(__('Image or video')), TableColumn::make(__('Playback controls'))])
            ->compact()
            ->schema([
                MediaAssetSelect::makeId(
                    'media_asset_id',
                    __('Image or video'),
                    allowedMimeTypes: [...MediaTypePolicy::IMAGE_MIME_TYPES, ...MediaTypePolicy::VIDEO_MIME_TYPES],
                )
                    ->hiddenLabel()
                    ->required()
                    ->live()
                    ->extraAlpineAttributes(['data-admin-edit-change-owner' => 'livewire'], merge: true)
                    ->skipRenderAfterStateUpdated()
                    ->afterStateUpdated(static function (mixed $state, Component $livewire): void {
                        if (filled($state)
                            && $livewire instanceof \App\Filament\Pages\JournalWorkspace
                            && $livewire->getMountedAction()?->getName() === 'editExhibition') {
                            $livewire->callMountedAction();
                        }
                    }),
                self::videoControlsToggle('video_controls_enabled')->hiddenLabel(),
            ])
            ->addActionLabel(__('Add media'))
            ->reorderableWithButtons()
            ->reorderableWithDragAndDrop(false)
            ->deleteAction(fn (Action $action): Action => $action->after(
                static fn (Component $livewire) => $livewire->dispatch('admin-edit-list-updated'),
            ))
            ->moveUpAction(fn (Action $action): Action => $action->after(
                static fn (Component $livewire) => $livewire->dispatch('admin-edit-list-updated'),
            ))
            ->moveDownAction(fn (Action $action): Action => $action->after(
                static fn (Component $livewire) => $livewire->dispatch('admin-edit-list-updated'),
            ))
            ->columnSpanFull();
    }

    private static function publicLocationField(string $name, string $label, int $max, ?Exhibition $exhibition): TextInput
    {
        $field = TextInput::make($name)
            ->label(__($label))
            ->maxLength($max)
            ->nullable();

        if (! $exhibition instanceof Exhibition) {
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
