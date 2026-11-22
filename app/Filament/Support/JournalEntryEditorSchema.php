<?php

namespace App\Filament\Support;

use App\Domain\Admin\AdminNotifier;
use App\Domain\Content\ExhibitionDirectoryService;
use App\Domain\Content\ExhibitionMapAddress;
use App\Domain\Content\JournalTemplate;
use App\Domain\Content\RegionalFormats;
use App\Domain\Media\MediaTypePolicy;
use App\Filament\Pages\JournalWorkspace;
use App\Filament\Support\Controls\AdminControl;
use App\Filament\Support\Controls\AdminPanelControl;
use App\Filament\Support\Dialogs\AdminDialog;
use App\Filament\Support\Dialogs\AdminDialogSize;
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
    public static function stageEditor(Schema $schema, JournalTemplate $template, bool $creating = true): Schema
    {
        $fields = $template === JournalTemplate::Exhibitions
            ? self::basicsFields($template, stage: true)
            : [
                self::title(),
                MediaAssetSelect::makeId('cover_media_asset_id', __('Cover image'), imagesOnly: true)
                    ->placeholder(__('No cover image'))->nullable()->live()->columnSpanFull(),
                self::imageViewerField()->live(),
            ];
        // Only the new-entry form may remain untitled until direct Submit.
        if ($creating) {
            $fields[0]->required(false);
        }
        $fields[0]->columnSpanFull();

        if ($template === JournalTemplate::Exhibitions) {
            $fields[] = self::dateFields()[0];
            $fields[] = Grid::make()->columns(['default' => 2])->schema([
                self::stageDirectoryInput('organizer'),
                self::stageDirectoryInput('venue'),
            ])->extraAttributes(['class' => 'journal-stage-directory'])->columnSpanFull();
            // The Stage edits a venue, not individual address inputs.
            // Snapshot location values still flow into Submit and Public Preview.
            foreach (['location_text', 'postal_code', 'city', 'country'] as $field) {
                $fields[] = Hidden::make($field);
            }
            $fields[] = self::exhibitionDescriptionPanel(stage: true);
            $fields[] = self::exhibitionGalleryPanel(stage: true);
        } else {
            $fields[] = AdminForm::section(__('Excerpt'))
                ->schema(AdminRichText::schema('excerpt', __('Excerpt'), 1000, allowEmbeddedMedia: false, pageAutosave: true, stageCompact: true))
                ->collapsible()->collapsed()->columnSpanFull();
            $fields[] = AdminForm::section(__('Text'))
                ->schema(AdminRichText::schema('body', __('Text'), 50000, allowEmbeddedMedia: true, pageAutosave: true, stageCompact: true))
                ->collapsible()->collapsed()->columnSpanFull();
            $fields[] = self::gallerySection(stage: true)->collapsible()->collapsed()->columnSpanFull();
            if ($creating) {
                $fields[] = AdminControl::select('stage_publication_mode', 'On submit')
                    ->options(['publish' => 'Publish', 'schedule' => 'Schedule', 'draft' => 'Save draft'])
                    ->default('publish')->live()->required()->columnSpanFull();
                $fields[] = DateTimePicker::make('stage_scheduled_at')
                    ->label(__('Publish at'))->seconds(false)->native(false)->nullable()
                    ->visible(fn (Get $get): bool => $get('stage_publication_mode') === 'schedule')
                    ->columnSpanFull();
            }
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
                    self::exhibitionDescriptionPanel(),
                    self::exhibitionGalleryPanel(),
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

    private static function basicsFields(JournalTemplate $template, bool $stage = false): array
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

        if ($stage) {
            $cover->live();
        }
        $schema[] = $cover->nullable()->columnSpanFull();

        if ($template === JournalTemplate::Exhibitions && ! $stage) {
            $schema[] = self::videoControlsChoice('cover_video_controls_enabled')->columnSpanFull();
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
            DateTimePicker::make('vernissage_at')->label(__('Vernissage'))->native(false)->displayFormat(fn (): string => RegionalFormats::pickerDateTimeFormat())->seconds(false)->default(null)->nullable()->columnSpanFull(),
        ];
    }

    private static function gallerySection(bool $stage = false): mixed
    {
        $presentation = Select::make('gallery_presentation')
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
            ->required();
        if ($stage) {
            $presentation->live();
        }

        return AdminForm::section(__('Gallery'))->schema([
            $presentation,
            self::galleryImages(stage: $stage),
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

    private static function exhibitionDescriptionPanel(bool $stage = false): mixed
    {
        return AdminPanelControl::make(
            'Description',
            'description_enabled',
            AdminRichText::schema(
                'description', __('Description'), 50000,
                allowEmbeddedMedia: true,
                pageAutosave: $stage,
                hideLabel: true,
                stageCompact: $stage,
            ),
            collapsible: true,
            bodyColumns: ['default' => 1],
            dense: $stage,
            hideWhenDisabled: true,
        );
    }

    private static function exhibitionGalleryPanel(bool $stage = false): mixed
    {
        // The panel switch is the only Gallery visibility control.
        // Presentation remains optional; the canonical default is Grid.
        $presentation = Select::make('gallery_presentation')
            ->label($stage ? __('Presentation') : __('Gallery presentation'))
            ->options([
                'grid' => __('Grid'),
                'mosaic' => __('Mosaic'),
                'slideshow' => __('Slideshow'),
                'carousel' => __('Carousel'),
            ])
            ->disableOptionWhen(static fn (string $value, Get $get): bool => self::carouselOptionDisabled($value, $get))
            ->default('grid')
            ->nullable()
            ->columnSpanFull();
        if ($stage) {
            $presentation->live();
        }

        return AdminPanelControl::make(
            'Exhibition gallery',
            'gallery_enabled',
            [$presentation, self::galleryImages(table: true, stage: $stage)],
            collapsible: true,
            bodyColumns: ['default' => 1],
            compactRows: $stage,
            dense: $stage,
            hideWhenDisabled: true,
        );
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

    /** Each media entry can inherit the Journal's playback setting or override it. */
    private static function videoControlsChoice(string $name): Select
    {
        return AdminControl::select($name, 'Show video playback controls')
            ->options([
                'inherit' => 'Use Journal setting',
                'show' => 'Show',
                'hide' => 'Hide',
            ])
            ->default('inherit')
            ->required();
    }

    private static function galleryImages(bool $table = false, bool $stage = false): Repeater
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
                    ...($stage ? [] : [self::videoControlsChoice('video_controls_enabled')]),
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
            ->table($stage
                ? [TableColumn::make(__('Image or video'))]
                : [TableColumn::make(__('Image or video')), TableColumn::make(__('Video controls'))])
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
                ...($stage ? [] : [
                    self::videoControlsChoice('video_controls_enabled')
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
                ]),
            ])
            ->addActionLabel(__('Add media'))
            ->extraAttributes($stage ? ['data-admin-stage-gallery' => 'true'] : [])
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

    /** Reusable catalog fields shared by dialog management and inline creation. */
    public static function directoryFields(string $kind): array
    {
        return [
            AdminControl::text('name', $kind)->required()->maxLength(240)->columnSpanFull(),
            AdminControl::url('website', 'Website')->maxLength(2048)->nullable()->columnSpanFull(),
            AdminControl::text('street', 'Street address')->maxLength(500)->nullable()->columnSpanFull(),
            Grid::make()->columns(['default' => 1, 'sm' => 2])->schema([
                AdminControl::text('postal_code', 'Postal code')->maxLength(16)->nullable(),
                AdminControl::text('city', 'City')->maxLength(160)->nullable(),
            ])->columnSpanFull(),
            AdminControl::text('country', 'Country')->maxLength(160)->nullable()->columnSpanFull(),
        ];
    }

    /**
     * Stage-only single-row combobox: native completion suggests the reusable
     * directory while allowing a new name without an intervening modal.
     * The editorial write transaction is the only automatic create boundary.
     */
    private static function stageDirectoryInput(string $kind): TextInput
    {
        $isVenue = $kind === 'venue';
        $title = $isVenue ? 'Venue' : 'Organizer';
        $field = AdminControl::text($kind, $title)
            ->maxLength(240)->nullable()
            ->datalist($isVenue
                ? fn (): array => ExhibitionVenuePreset::query()->orderByRaw('LOWER(name)')->orderBy('name')->pluck('name')->all()
                : fn (): array => ExhibitionOrganizerPreset::query()->orderByRaw('LOWER(name)')->orderBy('name')->pluck('name')->all())
            ->live(onBlur: true);

        if ($isVenue) {
            $field->afterStateUpdated(static fn (mixed $state, Set $set) => self::fillVenueSnapshot($state, $set));
        }

        $action = Action::make('addStage'.ucfirst($kind))
            ->label('Add '.strtolower($title))
            ->modalHeading('Add '.strtolower($title))
            ->schema(self::directoryFields($title))
            ->action(static function (array $data, Set $schemaSet) use ($isVenue, $kind, $title): void {
                if ($isVenue) {
                    app(ExhibitionDirectoryService::class)->addVenue($data);
                } else {
                    app(ExhibitionDirectoryService::class)->addOrganizer($data);
                }
                $schemaSet($kind, trim((string) $data['name']));
                if ($isVenue) {
                    self::fillVenueSnapshot($data['name'], $schemaSet);
                }
                app(AdminNotifier::class)->notification(title: $title.' added', status: 'success');
            });
        $field->hintAction(AdminDialog::create($action, 'Add '.$title, AdminDialogSize::Default)->iconButton());

        return $field;
    }

    private static function fillVenueSnapshot(mixed $state, Set $set): void
    {
        if (! is_string($state) || trim($state) === '') {
            // No linked Venue: leave the Exhibition's independent address intact.
            return;
        }
        $venue = ExhibitionVenuePreset::query()->whereRaw('LOWER(name) = ?', [mb_strtolower(trim($state))])->first();
        if (! $venue instanceof ExhibitionVenuePreset) {
            return;
        }
        foreach ([
            'street' => 'location_text',
            'postal_code' => 'postal_code',
            'city' => 'city',
            'country' => 'country',
        ] as $source => $target) {
            $set($target, $venue->getAttribute($source));
        }
    }

    /** Organizer names remain exact canonical text in Exhibition snapshots. */
    private static function organizerSelect(): Select
    {
        return AdminControl::select('organizer', 'Organizer')
            ->options(fn (): array => ExhibitionOrganizerPreset::query()
                ->orderByRaw('LOWER(name)')->orderBy('name')->pluck('name', 'name')->all())
            ->getOptionLabelUsing(static fn (mixed $value): ?string =>
                is_string($value) && trim($value) !== '' ? $value : null)
            ->searchable()->preload()->native(false)->nullable()
            ->createOptionModalHeading('Add organizer')
            ->createOptionForm(self::directoryFields('Organizer'))
            ->createOptionAction(static fn (Action $action): Action =>
                AdminDialog::create($action->label('Add organizer'), 'Add organizer', AdminDialogSize::Default))
            ->createOptionUsing(static function (array $data): string {
                app(ExhibitionDirectoryService::class)->addOrganizer($data);
                app(AdminNotifier::class)->notification(title: 'Organizer added', status: 'success');

                return trim((string) $data['name']);
            });
    }

    /** The Venue selection never changes the Organizer. */
    private static function venueSelect(): Select
    {
        return AdminControl::select('venue', 'Venue')
            ->options(fn (): array => ExhibitionVenuePreset::query()
                ->orderByRaw('LOWER(name)')->orderBy('name')->pluck('name', 'name')->all())
            ->getOptionLabelUsing(static fn (mixed $value): ?string =>
                is_string($value) && trim($value) !== '' ? $value : null)
            ->searchable()->preload()->native(false)->nullable()->live()
            ->afterStateUpdated(static fn (mixed $state, Set $set) => self::fillVenueSnapshot($state, $set))
            ->createOptionModalHeading('Add venue')
            ->createOptionForm(self::directoryFields('Venue'))
            ->createOptionAction(static fn (Action $action): Action =>
                AdminDialog::create($action->label('Add venue'), 'Add venue', AdminDialogSize::Default))
            ->createOptionUsing(static function (array $data): string {
                app(ExhibitionDirectoryService::class)->addVenue($data);
                app(AdminNotifier::class)->notification(title: 'Venue added', status: 'success');

                return trim((string) $data['name']);
            });
    }

    private static function publicLocationField(string $name, string $label, int $max, ?Exhibition $exhibition): TextInput|Select
    {
        $field = $name === 'organizer'
            ? self::organizerSelect()
            : ($name === 'venue'
                ? self::venueSelect()
                : TextInput::make($name)->label(__($label))->maxLength($max)->nullable());

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
