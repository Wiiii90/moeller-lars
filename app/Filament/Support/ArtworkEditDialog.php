<?php

namespace App\Filament\Support;

use App\Domain\Artwork\ArtworkDimensions;
use App\Domain\Artwork\ArtworkDraftService;
use App\Domain\Artwork\ArtworkGalleryAssignmentService;
use App\Domain\Artwork\ArtworkPrimaryMediaService;
use App\Domain\Media\MediaAssetEditorialService;
use App\Domain\Media\MediaTypePolicy;
use App\Domain\Media\MediaIngestService;
use Closure;
use App\Filament\Resources\Artworks\Support\ArtworkMaterialSelect;
use App\Models\Artwork;
use App\Models\ArtworkCategory;
use App\Models\MediaAsset;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Grid;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;

final class ArtworkEditDialog
{
    /** @return array<string, mixed> */
    public function fill(Artwork $artwork): array
    {
        $dimensions = ArtworkDimensions::split($artwork->getAttribute('dimensions'));
        $primary = $artwork->artworkMedia()->where('role', 'primary')->first();

        return [
            'title' => $artwork->getAttribute('title'),
            'slug' => $artwork->getAttribute('slug'),
            'artwork_category_id' => $artwork->getAttribute('artwork_category_id'),
            'medium' => $artwork->getAttribute('medium'),
            'dimension_height' => $dimensions['height'],
            'dimension_width' => $dimensions['width'],
            'dimension_depth' => $dimensions['depth'],
            'dimension_unit' => $dimensions['unit'],
            'dimension_custom' => $dimensions['custom'],
            'description' => $artwork->getAttribute('description'),
            'primary_media_asset_id' => $primary?->getAttribute('media_asset_id'),
            'primary_alt_text' => $primary?->getAttribute('alt_text_override'),
            'work_year' => $artwork->getAttribute('work_year'),
            'work_date' => $artwork->getAttribute('work_date'),
            'exclude_from_home' => (int) (bool) $artwork->getAttribute('exclude_from_home'),
        ];
    }

    /** @return list<mixed> */
    public function schema(bool $includeGalleryAssignment = false, bool $creating = false, bool $autosave = false, bool $primaryReadOnly = false, ?Closure $onPrimaryMediaChanged = null, ?int $positionCount = null, bool $positionEditable = true): array
    {
        $slug = TextInput::make('slug')
            ->label('Public URL slug')
            ->required()
            ->maxLength(180)
            ->regex('/^[a-z0-9]+(?:-[a-z0-9]+)*$/');
        if ($creating) {
            $slug->unique('artworks', 'slug');
        }

        return [
            Grid::make()
                ->columns(['md' => 2])
                ->schema([
                    TextInput::make('title')
                        ->required()
                        ->maxLength(240)
                        ->live(onBlur: true)
                        ->afterStateUpdated(function (?string $state, callable $set, callable $get): void {
                            if (blank($get('slug')) && filled($state)) {
                                $set('slug', Str::slug($state));
                            }
                        }),
                    $slug,
                    ...($includeGalleryAssignment ? [
                        Select::make('artwork_category_id')
                            ->label('Gallery')
                            ->options(static fn (): array => ArtworkCategory::query()->orderBy('name')->pluck('name', 'id')->all())
                            ->nullable()
                            ->searchable()
                            ->preload(),
                    ] : []),
                    ArtworkMaterialSelect::make(),
                    TextInput::make('work_year')->label('Year')->numeric()->minValue(1000)->maxValue(9999)->nullable(),
                    DatePicker::make('work_date')->label('Exact date')->nullable(),
                    ...($positionCount !== null ? [
                        TextInput::make('position')
                            ->label('Position')
                            ->numeric()
                            ->integer()
                            ->minValue(1)
                            ->maxValue(max(1, $positionCount))
                            ->required()
                            ->disabled(! $positionEditable),
                    ] : []),
                    Select::make('exclude_from_home')
                        ->label('Home eligibility')
                        ->options([
                            0 => __('Follow Gallery setting'),
                            1 => __('Exclude this Artwork from Home'),
                        ])
                        ->default(0)
                        ->columnSpanFull(),

                    TextInput::make('dimension_height')->label('Height (H)')->numeric()->minValue(0.01)->nullable(),
                    TextInput::make('dimension_width')->label('Width (W)')->numeric()->minValue(0.01)->nullable(),
                    TextInput::make('dimension_depth')->label('Depth (D)')->numeric()->minValue(0.01)->nullable(),
                    Select::make('dimension_unit')
                        ->label('Unit')
                        ->options(['cm' => 'cm', 'mm' => 'mm', 'in' => 'in'])
                        ->default('cm')
                        ->required(),
                    TextInput::make('dimension_custom')
                        ->label('Custom dimensions')
                        ->maxLength(240)
                        ->nullable()
                        ->columnSpanFull(),
                    ...AdminRichText::schema('description', 'Description', 10000),
                    MediaAssetSelect::makeId(
                        'primary_media_asset_id',
                        'Existing Media File',
                        allowedMimeTypes: self::primaryMimeTypes(),
                    )
                        ->placeholder(__('No primary media'))
                        ->nullable()
                        ->disabled($primaryReadOnly)
                        ->selectablePlaceholder(! $primaryReadOnly)
                        ->extraFieldWrapperAttributes($primaryReadOnly ? [
                            'title' => 'Unpublish this Artwork before changing or removing its primary Media File.',
                        ] : [])
                        ->live()
                        ->afterStateUpdated(static function (mixed $state, callable $set) use ($onPrimaryMediaChanged): void {
                            // Gallery Edit persists this discrete Media File choice at
                            // the field boundary, not through a bubbled modal change.
                            // Create and the standalone EditRecord keep their existing
                            // full-form submission lifecycle.
                            if ($onPrimaryMediaChanged instanceof Closure) {
                                $onPrimaryMediaChanged(filled($state) ? (int) $state : null);
                            }

                            // ALT belongs to the newly chosen file, not the old primary.
                            $set('primary_alt_text', null);
                        })
                        ->columnSpanFull(),
                    ...(! $autosave ? [FileUpload::make('primary_upload')
                        ->label('Or upload new primary media')
                        ->storeFiles(false)
                        ->disabled($primaryReadOnly)
                        ->acceptedFileTypes(self::primaryMimeTypes())
                        ->maxSize((int) ceil(MediaTypePolicy::maxUploadBytes() / 1024))
                        ->live()
                        ->afterStateUpdated(static function (mixed $state, callable $set): void {
                            if (filled($state)) {
                                $set('primary_alt_text', null);
                            }
                        })
                        ->extraFieldWrapperAttributes($primaryReadOnly ? [
                            'title' => 'Unpublish this Artwork before uploading a different primary file.',
                        ] : [])
                        ->columnSpanFull()] : []),
                    Textarea::make('primary_alt_text')
                        ->label('Primary media ALT text')
                        ->maxLength(500)
                        ->nullable()
                        ->columnSpanFull(),
                ]),
        ];
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
        $upload = $data['primary_upload'] ?? null;
        $currentGalleryId = $artwork->getAttribute('artwork_category_id');
        $currentGalleryId = $currentGalleryId === null ? null : (int) $currentGalleryId;
        $requestedGalleryId = $allowGalleryAssignment && filled($data['artwork_category_id'] ?? null)
            ? (int) $data['artwork_category_id']
            : ($allowGalleryAssignment ? null : $currentGalleryId);

        if ($upload !== null && ! $upload instanceof TemporaryUploadedFile) {
            throw ValidationException::withMessages(['primary_upload' => 'Choose a valid image or video file.']);
        }
        if ($upload instanceof TemporaryUploadedFile && $assetId > 0 && $assetId !== $currentAssetId) {
            throw ValidationException::withMessages(['primary_upload' => 'Choose either a new upload or an existing Media File, not both.']);
        }
        if ($upload instanceof TemporaryUploadedFile) {
            $this->assertUploadIsPrimaryMedia($upload);
        }

        // Storage owns durable originals and variants. Ingest the new file
        // before the Artwork database transaction so a later editorial failure
        // cannot roll back the MediaAsset while leaving its files on disk.
        // The successfully ingested file remains reusable in Storage.
        try {
            $uploadedAsset = $upload instanceof TemporaryUploadedFile
                ? app(MediaIngestService::class)->ingest($upload)
                : null;
        } catch (ValidationException $exception) {
            throw ValidationException::withMessages([
                'primary_upload' => collect($exception->errors())->flatten()->first()
                    ?? 'The selected image or video could not be processed.',
            ]);
        }

        DB::transaction(function () use ($artwork, $data, $uploadedAsset, $assetId, $currentPrimary, $currentGalleryId, $requestedGalleryId, $allowGalleryAssignment, $savePrimaryMedia): void {
            if ($allowGalleryAssignment && $requestedGalleryId !== $currentGalleryId) {
                $assignments = app(ArtworkGalleryAssignmentService::class);
                if ($requestedGalleryId === null) {
                    $assignments->detach($artwork);
                } else {
                    /** @var ArtworkCategory $gallery */
                    $gallery = ArtworkCategory::query()->findOrFail($requestedGalleryId);
                    $assignments->reassign($artwork, $gallery);
                }
            }

            app(ArtworkDraftService::class)->update(
                $artwork,
                $this->normalizeArtworkFormData($data, $requestedGalleryId),
            );

            $primaryMedia = app(ArtworkPrimaryMediaService::class);
            if ($uploadedAsset instanceof MediaAsset) {
                $currentPrimary === null
                    ? $primaryMedia->attachAsset($artwork, $uploadedAsset, recordIngest: true)
                    : $primaryMedia->replaceAsset($artwork, $uploadedAsset, recordIngest: true);
            } elseif ($savePrimaryMedia && array_key_exists('primary_media_asset_id', $data)) {
                // Gallery autosave persists this field in afterStateUpdated.
                // This full-form path remains for standalone EditRecord.
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
                    'primary_alt_text' => 'Choose or upload primary media before entering ALT text.',
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
    private function normalizeArtworkFormData(array $data, ?int $galleryId): array
    {
        $data['artwork_category_id'] = $galleryId;
        $data['work_date'] = $data['work_date'] ?? null;
        $data['medium'] = filled($data['medium'] ?? null) ? trim((string) $data['medium']) : null;
        $data['dimensions'] = ArtworkDimensions::compose(
            $data['dimension_height'] ?? null,
            $data['dimension_width'] ?? null,
            $data['dimension_depth'] ?? null,
            $data['dimension_unit'] ?? 'cm',
            $data['dimension_custom'] ?? null,
        );

        foreach ([
            'dimension_height',
            'dimension_width',
            'dimension_depth',
            'dimension_unit',
            'dimension_custom',
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

    private function assertUploadIsPrimaryMedia(TemporaryUploadedFile $upload): void
    {
        $mime = (string) $upload->getMimeType();
        if (! MediaTypePolicy::isImage($mime) && ! MediaTypePolicy::isVideo($mime)) {
            throw ValidationException::withMessages([
                'primary_upload' => 'Primary media must be an image or video. Audio is not supported.',
            ]);
        }
    }
}
