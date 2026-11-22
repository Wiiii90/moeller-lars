<?php

namespace App\Filament\Support;

use App\Domain\Artwork\ArtworkDate;
use App\Domain\Artwork\ArtworkDimensions;
use App\Domain\Artwork\ArtworkInformation;
use App\Domain\Artwork\ArtworkDraftService;
use App\Domain\Artwork\ArtworkGalleryAssignmentService;
use App\Domain\Artwork\ArtworkPrimaryMediaService;
use App\Domain\Media\MediaAssetEditorialService;
use App\Domain\Media\MediaTypePolicy;
use App\Filament\Resources\Artworks\Support\ArtworkMaterialSelect;
use App\Filament\Support\Controls\AdminControl;
use App\Filament\Support\Controls\AdminPanelControl;
use App\Models\Artwork;
use App\Models\ArtworkCategory;
use App\Models\MediaAsset;
use Closure;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Grid;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

final class ArtworkEditDialog
{
    /** @return array<string, mixed> */
    public function fill(Artwork $artwork): array
    {
        $dimensions = ArtworkDimensions::split($artwork->getAttribute('dimensions'));
        $primary = $artwork->artworkMedia()->where('role', 'primary')->first();

        $gallery = $artwork->getRelationValue('category');
        $configuration = $gallery instanceof ArtworkCategory
            ? ArtworkInformation::effective($gallery, $artwork)
            : ArtworkInformation::defaults();
        $overrides = $artwork->getAttribute('artwork_information_override');

        return [
            ...ArtworkInformation::formValues($configuration),
            'info_individual' => is_array($overrides),
            'title' => $artwork->getAttribute('title'),
            'slug' => $artwork->getAttribute('slug'),
            'artwork_category_id' => $artwork->getAttribute('artwork_category_id'),
            'medium' => $artwork->getAttribute('medium'),
            'dimension_height' => $dimensions['height'],
            'dimension_width' => $dimensions['width'],
            'dimension_depth' => $dimensions['depth'],
            'dimension_unit' => $dimensions['unit'],
            'description' => $artwork->getAttribute('description'),
            'primary_media_asset_id' => $primary?->getAttribute('media_asset_id'),
            'primary_alt_text' => $primary?->getAttribute('alt_text_override'),
            'artwork_date' => ArtworkDate::fromArtwork($artwork),
        ];
    }

    /** @return list<mixed> */
    public function schema(bool $includeGalleryAssignment = false, bool $creating = false, bool $autosave = false, bool $primaryReadOnly = false, ?Closure $onPrimaryMediaChanged = null, ?int $positionCount = null, bool $positionEditable = true, ?Closure $onPositionChanged = null, ?Closure $positionSuggestion = null, bool $compact = false, ?Closure $titleSuggestion = null): array
    {
        $slug = TextInput::make('slug')
            ->label('Public URL slug')
            ->required(! $creating)
            ->maxLength(180)
            ->regex('/^[a-z0-9]+(?:-[a-z0-9]+)*$/');
        if ($creating) {
            $slug->unique('artworks', 'slug');
        }

        $material = ArtworkMaterialSelect::make();
        if ($positionCount === null) {
            $material->columnSpanFull();
        }

        $dateControl = AdminPartialDateControl::make(
            liveUpdates: $creating,
        )
            ->rule(static fn (): Closure => static function (string $attribute, mixed $value, Closure $fail): void {
                try {
                    ArtworkDate::normalize($value);
                } catch (ValidationException $exception) {
                    $fail($exception->getMessage());
                }
            })
            ->columnSpanFull();

        if ($creating) {
            $dateControl->live();
        }

        if ($compact) {
            // Reuse the canonical Storage thumbnail/filename presentation.
            // The Stage queue owns the ID: this disabled picker never loads
            // options or submits a second media-assignment state.
            $primaryMedia = MediaAssetSelect::makeId(
                'primary_media_asset_id',
                'Media file',
                allowedMimeTypes: self::primaryMimeTypes(),
            )
                ->placeholder(__('No primary media'))
                ->disabled()
                ->dehydrated(false)
                ->selectablePlaceholder(false)
                ->columnSpanFull();
        } else {
            $primaryMedia = MediaAssetSelect::makeId(
                'primary_media_asset_id',
                'Media file',
                allowedMimeTypes: self::primaryMimeTypes(),
            )
                ->placeholder(__('No primary media'))
                ->nullable()
                ->disabled($primaryReadOnly)
                ->selectablePlaceholder(! $primaryReadOnly)
                ->extraFieldWrapperAttributes($primaryReadOnly ? [
                    'title' => $creating
                        ? 'This Media File belongs to the selected Gallery Stage item.'
                        : 'Unpublish this Artwork before changing or removing its primary Media File.',
                ] : [], merge: true)
                ->live()
                ->afterStateUpdated(static function (mixed $state, callable $set) use ($onPrimaryMediaChanged): void {
                    // On Edit, persist only through the audited primary owner.
                    if ($onPrimaryMediaChanged instanceof Closure) {
                        $onPrimaryMediaChanged(filled($state) ? (int) $state : null);
                    }
                    $set('primary_alt_text', null);
                })
                ->columnSpanFull();
        }

        $position = $positionCount !== null
            ? $this->positionControl($positionCount, $positionEditable, $onPositionChanged)
            : null;
        if ($creating && $position !== null && $positionSuggestion instanceof Closure) {
            // The automatic rank is a grey hint, never a submitted manual
            // position. Only typing a number switches to explicit placement.
            $position->placeholder(static fn (callable $get): string => (string) $positionSuggestion($get));
        }

        return [
            // The Stage uses two immediate columns within its own editor pane;
            // ordinary Add/Edit Artwork retains its existing responsive schema.
            Grid::make($compact ? ['default' => 2] : ['md' => 2])
                ->schema([
                    TextInput::make('title')
                        ->required(! $creating)
                        ->maxLength(240)
                        ->placeholder($compact ? $titleSuggestion : null)
                        ->live(onBlur: true)
                        ->afterStateUpdated(function (?string $state, callable $set, callable $get) use ($compact): void {
                            if (! $compact && blank($get('slug')) && filled($state)) {
                                $set('slug', Str::slug($state));
                            }
                        }),
                    ...($compact ? [$dateControl->columnSpan(1)] : [$slug]),
                    ...($includeGalleryAssignment ? [
                        Select::make('artwork_category_id')
                            ->label('Gallery')
                            ->options(static fn (): array => ArtworkCategory::query()->orderBy('name')->pluck('name', 'id')->all())
                            ->required()
                            ->searchable()
                            ->preload()
                            ->columnSpanFull(),
                    ] : []),
                    ...($compact ? [] : [$primaryMedia]),
                    ...($compact ? [] : [
                        TextInput::make('primary_alt_text')
                            ->label('ALT text override')
                            ->maxLength(500)
                            ->nullable()
                            ->columnSpanFull(),
                        $dateControl,
                    ]),

                    // Creating, including Stage, presents Position before Material.
                    // Existing Edit dialogs retain their established order.
                    ...($creating && $position !== null
                        ? [$position, $material]
                        : [$material, ...($position !== null ? [$position] : [])]),

                    AdminDimensionsControl::make(),
                    ...($compact ? [$primaryMedia] : []),

                    ...($compact ? [] : AdminRichText::schema('description', 'Description', 10000)),

                    ...(! $creating ? [$this->individualInformationPanel()] : []),
                ]),
            ...($creating && $positionCount !== null ? [
                // Transient form intent only: never store a second ranking.
                Hidden::make('position_explicit')->default(false),
            ] : []),
        ];
    }

    /** One individually enabled override panel on the same Artwork edit path. */
    private function individualInformationPanel(string $label = 'Individual Artwork information'): \Filament\Schemas\Components\Group
    {
        $fields = [
            AdminControl::checkbox('info_enabled', 'Show information')->live(),
        ];
        foreach (ArtworkInformation::FIELDS as $field => $label) {
            $fields[] = AdminControl::checkbox('info_'.$field, $label)->live();
        }

        return AdminPanelControl::make(
            $label,
            'info_individual',
            $fields,
        );
    }

    /**
     * Position is an explicit user mutation, not data to reapply whenever an
     * unrelated Artwork field autosaves while the ranked list changes.
     */
    private function positionControl(int $count, bool $editable, ?Closure $onPositionChanged): TextInput
    {
        $field = TextInput::make('position')
            ->label('Position')
            ->numeric()
            ->integer()
            ->minValue(1)
            ->maxValue(max(1, $count))
            ->required(false)
            ->disabled(! $editable);

        if ($onPositionChanged instanceof Closure) {
            $field->live(onBlur: true)->afterStateUpdated($onPositionChanged);
        }

        return $field;
    }

    /**
     * Persist one primary Media File selection using the existing audited
     * primary-usage owner. Storage originals are never overwritten or deleted.
     */
    public function changePrimaryMedia(Artwork $artwork, ?int $assetId): bool
    {
        $artwork = Artwork::query()->findOrFail($artwork->getKey());
        $primary = $artwork->artworkMedia()->where('role', 'primary')->first();
        $currentId = $primary === null ? null : (int) $primary->getAttribute('media_asset_id');
        if ($currentId === $assetId) {
            return false;
        }

        if ((string) $artwork->getAttribute('state') !== 'draft') {
            throw ValidationException::withMessages([
                'primary_media_asset_id' => 'Unpublish this Artwork before changing or removing its primary Media File.',
            ]);
        }

        $service = app(ArtworkPrimaryMediaService::class);
        if ($assetId === null) {
            return $service->detachPrimary($artwork);
        }

        /** @var MediaAsset $asset */
        $asset = MediaAsset::query()->findOrFail($assetId);
        if ($primary === null) {
            $service->attachAsset($artwork, $asset);
        } else {
            $service->replaceAsset($artwork, $asset);
        }

        return true;
    }

    /** @param array<string, mixed> $data */
    public function save(Artwork $artwork, array $data, bool $allowGalleryAssignment = false, bool $savePrimaryMedia = true): bool
    {
        // Services below persist fresh models rather than updating the caller's
        // instance. Compare against persisted state, including on repeated saves.
        $artwork = Artwork::query()->findOrFail($artwork->getKey());
        $currentPrimary = $artwork->artworkMedia()->where('role', 'primary')->first();
        $currentAssetId = $currentPrimary === null ? 0 : (int) $currentPrimary->getAttribute('media_asset_id');
        $before = [
            'artwork' => $artwork->getAttributes(),
            'primary_media_asset_id' => $currentAssetId,
            'primary_alt_text' => $currentPrimary?->getAttribute('alt_text_override'),
        ];
        $assetId = (int) ($data['primary_media_asset_id'] ?? 0);
        $currentGalleryId = $artwork->getAttribute('artwork_category_id');
        $currentGalleryId = $currentGalleryId === null ? null : (int) $currentGalleryId;
        $requestedGalleryId = $allowGalleryAssignment && filled($data['artwork_category_id'] ?? null)
            ? (int) $data['artwork_category_id']
            : ($allowGalleryAssignment ? null : $currentGalleryId);

        DB::transaction(function () use ($artwork, $data, $assetId, $currentGalleryId, $requestedGalleryId, $allowGalleryAssignment, $savePrimaryMedia): void {
            if ($allowGalleryAssignment && $requestedGalleryId !== $currentGalleryId) {
                $assignments = app(ArtworkGalleryAssignmentService::class);
                if ($requestedGalleryId === null) {
                    throw ValidationException::withMessages([
                        'artwork_category_id' => 'Choose a Gallery for this Artwork.',
                    ]);
                }

                /** @var ArtworkCategory $gallery */
                $gallery = ArtworkCategory::query()->findOrFail($requestedGalleryId);
                $assignments->reassign($artwork, $gallery);
            }

            app(ArtworkDraftService::class)->update(
                $artwork,
                $this->normalizeArtworkFormData($data, $requestedGalleryId, $artwork),
            );

            if ($savePrimaryMedia && array_key_exists('primary_media_asset_id', $data)) {
                $this->changePrimaryMedia($artwork, $assetId > 0 ? $assetId : null);
            }

            $primary = $artwork->artworkMedia()->where('role', 'primary')->first();
            if ($primary !== null) {
                app(MediaAssetEditorialService::class)->updatePrimaryAltOverride(
                    $artwork,
                    filled($data['primary_alt_text'] ?? null) ? (string) $data['primary_alt_text'] : null,
                );
            } elseif (filled($data['primary_alt_text'] ?? null)) {
                throw ValidationException::withMessages([
                    'primary_alt_text' => 'Choose a Media file before entering an ALT text override.',
                ]);
            }
        });

        /** @var Artwork $freshArtwork */
        $freshArtwork = Artwork::query()->findOrFail($artwork->getKey());
        $freshPrimary = $freshArtwork->artworkMedia()->where('role', 'primary')->first();
        $after = [
            'artwork' => $freshArtwork->getAttributes(),
            'primary_media_asset_id' => $freshPrimary === null ? 0 : (int) $freshPrimary->getAttribute('media_asset_id'),
            'primary_alt_text' => $freshPrimary?->getAttribute('alt_text_override'),
        ];

        return $before !== $after;
    }

    /** @param array<string, mixed> $data
     * @return array<string, mixed>
     */
    private function normalizeArtworkFormData(array $data, ?int $galleryId, ?Artwork $original = null): array
    {
        $data['artwork_category_id'] = $galleryId;
        if ($galleryId !== null && array_key_exists('info_individual', $data)) {
            /** @var ArtworkCategory $gallery */
            $gallery = ArtworkCategory::query()->findOrFail($galleryId);
            $data['artwork_information_override'] = ArtworkInformation::individualFromForm($data, $gallery);
        }
        foreach (['info_enabled', 'info_individual', ...array_map(
            static fn (string $field): string => 'info_'.$field,
            array_keys(ArtworkInformation::FIELDS),
        )] as $field) {
            unset($data[$field]);
        }
        $data = array_merge($data, ArtworkDate::normalize($data['artwork_date'] ?? null));
        unset($data['artwork_date']);
        $data['medium'] = filled($data['medium'] ?? null) ? trim((string) $data['medium']) : null;
        $structuredDimensions = filled($data['dimension_height'] ?? null)
            || filled($data['dimension_width'] ?? null)
            || filled($data['dimension_depth'] ?? null);
        $legacyDimensions = $original === null
            ? null
            : ArtworkDimensions::split((string) ($original->getAttribute('dimensions') ?? ''))['custom'];

        // No separate custom-dimensions column exists. Preserve historical
        // freeform text when the new numeric controls are untouched; never
        // erase saved artist data as a side-effect of editing another field.
        $data['dimensions'] = ! $structuredDimensions && $legacyDimensions !== null
            ? $legacyDimensions
            : ArtworkDimensions::compose(
                $data['dimension_height'] ?? null,
                $data['dimension_width'] ?? null,
                $data['dimension_depth'] ?? null,
                $data['dimension_unit'] ?? null,
                null,
            );

        foreach ([
            'dimension_height',
            'dimension_width',
            'dimension_depth',
            'dimension_unit',
            'primary_media_asset_id',
            'primary_upload',
            'primary_alt_text',
        ] as $field) {
            unset($data[$field]);
        }

        return $data;
    }

    /** @return list<string> */
    private static function primaryMimeTypes(): array
    {
        return [...MediaTypePolicy::IMAGE_MIME_TYPES, ...MediaTypePolicy::VIDEO_MIME_TYPES];
    }
}
