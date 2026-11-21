<?php

namespace App\Filament\Support;

use App\Domain\Content\ExhibitionMapAddress;
use App\Domain\Content\JournalTemplate;
use App\Domain\Content\RegionalFormats;
use App\Domain\Media\MediaTypePolicy;
use App\Filament\Pages\JournalWorkspace;
use App\Filament\Support\Controls\AdminControl;
use App\Models\Exhibition;
use App\Models\ExhibitionOrganizerPreset;
use App\Models\ExhibitionVenuePreset;
use App\Models\MediaAsset;
use Filament\Actions\Action;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Repeater\TableColumn;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;
use Illuminate\Support\HtmlString;
use Livewire\Component;

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

    /** The Stage reuses the canonical Journal fields in collapsible editor groups. */
    public static function staged(Schema $schema, JournalTemplate $template): Schema
    {
        $fields = $template === JournalTemplate::Exhibitions
            ? self::basicsFields($template)
            : [
                self::title(),
                MediaAssetSelect::makeId('cover_media_asset_id', __('Cover image'), imagesOnly: true)
                    ->placeholder(__('No cover image'))->nullable()->columnSpanFull(),
                self::imageViewerField(),
            ];
        // Unfinished Stage drafts may be saved without a title.
        $fields[0]->required(false)->columnSpanFull();

        if ($template === JournalTemplate::Exhibitions) {
            $fields[] = self::dateFields()[0];
            $fields[] = Grid::make()->columns(['default' => 1, 'sm' => 2])->schema([
                self::organizerSelect(),
                AdminControl::text('venue', 'Venue')->maxLength(240),
                self::venuePresetSelect()->columnSpanFull(),
            ])->columnSpanFull();
            foreach (['location_text', 'postal_code', 'city', 'country'] as $field) {
                $fields[] = Hidden::make($field);
            }
            $fields[] = AdminForm::section(__('Description'))
                ->schema(AdminRichText::schema('description', __('Text'), 50000, allowEmbeddedMedia: true, pageAutosave: true))
                ->collapsible()->collapsed()->columnSpanFull();
            $fields[] = AdminForm::section(__('Exhibition gallery'))
                ->schema(self::exhibitionGalleryFields())
                ->collapsible()->collapsed()->columnSpanFull();
        } else {
            $fields[] = AdminForm::section(__('Excerpt'))
                ->schema(AdminRichText::schema('excerpt', __('Excerpt'), 1000, allowEmbeddedMedia: false, pageAutosave: true))
                ->collapsible()->collapsed()->columnSpanFull();
            $fields[] = AdminForm::section(__('Text'))
                ->schema(AdminRichText::schema('body', __('Text'), 50000, allowEmbeddedMedia: true, pageAutosave: true))
                ->collapsible()->collapsed()->columnSpanFull();
            $fields[] = self::gallerySection()->collapsible()->collapsed()->columnSpanFull();
            $fields[] = AdminControl::select('stage_publication_mode', 'On submit')
                ->options(['publish' => 'Publish', 'schedule' => 'Schedule', 'draft' => 'Save draft'])
                ->default('publish')->live()->required()->columnSpanFull();
            $fields[] = DateTimePicker::make('stage_scheduled_at')
                ->label(__('Publish at'))->seconds(false)->native(false)->nullable()
                ->visible(fn (Get $get): bool => $get('stage_publication_mode') === 'schedule')
                ->columnSpanFull();
        }

        return $schema->components($fields)->columns(['default' => 1]);
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
                    ...self::mapFields(),
                    ...AdminRichText::schema('description', __('Text'), 50000, allowEmbeddedMedia: true),
                    ...self::exhibitionGalleryFields(),
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
        ];

        if ($template === JournalTemplate::Blog) {
            array_push($schema, ...AdminRichText::schema('excerpt', __('Excerpt'), 1000, allowEmbeddedMedia: false));
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
            Grid::make()
                ->columns(['default' => 1, 'sm' => 2])
                ->schema([
                    self::publicLocationField('organizer', 'Organizer', 240, $exhibition),
                    self::publicLocationField('venue', 'Venue', 240, $exhibition),
                ])
                ->columnSpanFull(),
            self::venuePresetSelect()->columnSpanFull(),
            self::publicLocationField('external_url', 'Exhibition website', 2048, $exhibition)->url()->columnSpanFull(),
            Grid::make()
                // Four fields are one composite address row at every breakpoint.
                // A narrower dialog must not silently split this into two rows.
                ->columns(4)
                ->schema([
                    self::publicLocationField('location_text', 'Street address', 500, $exhibition),
                    self::publicLocationField('city', 'City', 160, $exhibition),
                    self::publicLocationField('postal_code', 'Postal code', 16, $exhibition),
                    self::publicLocationField('country', 'Country', 160, $exhibition),
                ])
                ->columnSpanFull(),
        ];
    }

    private static function dateFields(): array
    {
        return [
            Grid::make()
                ->columns(['default' => 1, 'sm' => 2])
                ->schema([
                    AdminPartialDateControl::make(
                        'starts_on',
                        __('Starts'),
                        __('For a one-day exhibition, enter only the start date and leave Ends empty.'),
                        RegionalFormats::adminRegion(),
                    ),
                    AdminPartialDateControl::make('ends_on', __('Ends'), region: RegionalFormats::adminRegion()),
                ])
                ->columnSpanFull(),
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
                    'carousel' => __('Carousel'),
                ])
                ->disableOptionWhen(static fn (string $value, Get $get): bool => self::carouselOptionDisabled($value, $get))
                ->default('sequence')
                ->required(),
            self::galleryImages(),
        ]);
    }

    private static function carouselOptionDisabled(string $value, Get $get): bool
    {
        return $value === 'carousel' && ! self::hasCarouselMedia($get('gallery_images'));
    }

    private static function hasCarouselMedia(mixed $rows): bool
    {
        return is_array($rows)
            && count(array_filter($rows, static fn (mixed $row): bool => is_array($row) && filled($row['media_asset_id'] ?? null))) >= 3;
    }

    private static function reconcileCarouselEditor(mixed $rows, Get $get, Set $set): void
    {
        if ($get('gallery_presentation') === 'carousel' && ! self::hasCarouselMedia($rows)) {
            $set('gallery_presentation', 'slideshow');
        }
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
                    'carousel' => __('Carousel'),
                ])
                ->disableOptionWhen(static fn (string $value, Get $get): bool => self::carouselOptionDisabled($value, $get))
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
                ->label(new HtmlString(
                    '<span class="admin-form-label-with-help">'.e(__('Map enabled'))
                    .AdminHelp::make(__('Map location help'), __('Requires Country and either City or Postal code.'))->toHtml()
                    .'</span>',
                ))
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
                ->columnSpanFull(),
        ];
    }

    private static function title(): TextInput
    {
        return TextInput::make('title')->required()->maxLength(240)->live(onBlur: true);
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
        $onMediaChanged = static function (mixed $state, Get $get, Set $set): void {
            self::reconcileCarouselEditor($state, $get, $set);
        };

        if (! $table) {
            return Repeater::make('gallery_images')
                ->label(__('Gallery media'))
                ->schema([
                    MediaAssetSelect::makeId(
                        'media_asset_id',
                        __('Image or video'),
                        allowedMimeTypes: [...MediaTypePolicy::IMAGE_MIME_TYPES, ...MediaTypePolicy::VIDEO_MIME_TYPES],
                    )->required()->live(),
                    self::videoControlsToggle('video_controls_enabled'),
                ])
                ->addActionLabel(__('Add media'))
                ->reorderableWithButtons()
                ->reorderableWithDragAndDrop(false)
                ->afterStateUpdated($onMediaChanged)
                ->live()
                ->columnSpanFull();
        }

        return AdminTableRepeater::make('gallery_images')
            ->label(__('Gallery media'))
            ->hiddenLabel()
            ->table([TableColumn::make(__('Image or video')), TableColumn::make(__('Video controls'))])
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
                            && $livewire instanceof JournalWorkspace
                            && $livewire->getMountedAction()?->getName() === 'editExhibition') {
                            $livewire->callMountedAction();
                        }
                    }),
                self::videoControlsToggle('video_controls_enabled')
                    ->hiddenLabel()
                    ->visible(static function (Get $get): bool {
                        $assetId = filter_var($get('media_asset_id'), FILTER_VALIDATE_INT);

                        return $assetId !== false
                            && $assetId > 0
                            && MediaAsset::query()
                                ->whereKey((int) $assetId)
                                ->whereIn('mime_type', MediaTypePolicy::VIDEO_MIME_TYPES)
                                ->exists();
                    }),
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
            ->afterStateUpdated($onMediaChanged)
            ->live()
            ->columnSpanFull();
    }

    /** Organizer names remain exact canonical text in Exhibition snapshots. */
    private static function organizerSelect(): Select
    {
        return AdminControl::select('organizer', 'Organizer')
            ->options(fn (): array => ExhibitionOrganizerPreset::query()
                ->orderBy('position')->orderBy('id')->pluck('name', 'name')->all())
            ->getOptionLabelUsing(static fn (mixed $value): ?string =>
                is_string($value) && trim($value) !== '' ? $value : null)
            ->searchable()->preload()->native(false)->nullable()->live()
            ->afterStateUpdated(static function (Set $set): void {
                $set('venue_preset_id', null);
            });
    }

    /** Choosing a known venue fills editable exhibition-specific address fields. */
    private static function venuePresetSelect(): Select
    {
        return AdminControl::select('venue_preset_id', 'Saved venue')
            ->options(static function (Get $get): array {
                $organizer = trim((string) $get('organizer'));
                $preset = ExhibitionOrganizerPreset::query()->where('name', $organizer)->first();
                if (! $preset instanceof ExhibitionOrganizerPreset) {
                    return [];
                }

                return ExhibitionVenuePreset::query()->where('organizer_preset_id', $preset->getKey())
                    ->orderBy('position')->orderBy('id')->pluck('name', 'id')->all();
            })
            ->searchable()->preload()->native(false)
            ->nullable()->dehydrated(false)
            ->afterStateUpdated(static function (mixed $state, Set $set): void {
                $id = filter_var($state, FILTER_VALIDATE_INT);
                if ($id === false || $id <= 0) {
                    return;
                }
                $venue = ExhibitionVenuePreset::query()->with('organizer')->find($id);
                if (! $venue instanceof ExhibitionVenuePreset) {
                    return;
                }
                $set('organizer', (string) $venue->organizer?->getAttribute('name'));
                $set('venue', (string) $venue->getAttribute('name'));
                $set('location_text', $venue->getAttribute('street'));
                $set('postal_code', $venue->getAttribute('postal_code'));
                $set('city', $venue->getAttribute('city'));
                $set('country', $venue->getAttribute('country'));
            });
    }

    private static function publicLocationField(string $name, string $label, int $max, ?Exhibition $exhibition): TextInput|Select
    {
        $field = $name === 'organizer'
            ? self::organizerSelect()
            : TextInput::make($name)->label(__($label))->maxLength($max)->nullable();

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
                    /** @var JournalWorkspace $livewire */
                    $livewire->setExhibitionFieldVisibility((int) $exhibition->getKey(), $name, ! $visible());
                })
                ->after(static function (Action $action): void {
                    $action->getLivewire()->forceRender();
                    $action->halt();
                }),
        );
    }
}
