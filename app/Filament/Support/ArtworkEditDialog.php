<?php

namespace App\Filament\Support;

use App\Domain\Artwork\ArtworkDimensions;
use App\Domain\Artwork\ArtworkDraftService;
use App\Domain\Artwork\ArtworkGalleryAssignmentService;
use App\Domain\Artwork\ArtworkPrimaryMediaService;
use App\Domain\Media\MediaAssetEditorialService;
use App\Domain\Media\MediaTypePolicy;
use App\Domain\Media\MediaIngestService;
use App\Filament\Resources\Artworks\Support\ArtworkMaterialSelect;
use App\Models\Artwork;
use App\Models\ArtworkCategory;
use App\Models\MediaAsset;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
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
            'featured_on_home' => (bool) $artwork->getAttribute('featured_on_home'),
        ];
    }

    /** @return list<mixed> */
    public function schema(bool $includeGalleryAssignment = false, bool $creating = false, bool $autosave = false): array
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
            AdminForm::section('Basics')
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
                            ->preload()
                            ->helperText('An unpublished artwork may remain unassigned. Unpublish before removing it from a Gallery.'),
                    ] : []),
                    ArtworkMaterialSelect::make(),
                    TextInput::make('work_year')->label('Year')->numeric()->minValue(1000)->maxValue(9999)->nullable(),
                ])->columns(['md' => 2]),
            AdminForm::section('Dimensions')->schema([
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
                ])->columns(['md' => 2]),
            AdminForm::section('Content')->schema([
                    Textarea::make('description')->nullable()->maxLength(10000)->columnSpanFull(),
                ]),
            AdminForm::section('Media')->schema([
                    MediaAssetSelect::makeId(
                        'primary_media_asset_id',
                        'Existing Media File',
                        allowedMimeTypes: self::primaryMimeTypes(),
                    )
                        ->placeholder('No primary media')
                        ->nullable()
                        ->live()
                        ->afterStateUpdated(static function (callable $set): void {
                            // ALT belongs to the chosen Media File, not the previous primary.
                            $set('primary_alt_text', null);
                        })
                        ->helperText($autosave
                            ? 'Use Upload primary media in the Edit dialog actions to upload a new file.'
                            : 'Images and videos only.')
                        ->columnSpanFull(),
                    ...(! $autosave ? [FileUpload::make('primary_upload')
                        ->label('Or upload new primary media')
                        ->storeFiles(false)
                        ->acceptedFileTypes(self::primaryMimeTypes())
                        ->maxSize((int) ceil(MediaTypePolicy::maxUploadBytes() / 1024))
                        ->live()
                        ->afterStateUpdated(static function (mixed $state, callable $set): void {
                            if (filled($state)) {
                                $set('primary_alt_text', null);
                            }
                        })
                        ->helperText('JPEG, PNG, WebP, H.264 MP4 or supported WebM. The file is ingested into Storage first.')
                        ->columnSpanFull()] : []),
                    Textarea::make('primary_alt_text')
                        ->label('Primary media ALT text')
                        ->maxLength(500)
                        ->nullable()
                        ->helperText('Description for screen readers. Leave blank to inherit the Storage Media File ALT text.')
                        ->columnSpanFull(),
                ])->columns(['md' => 2]),
            AdminForm::section('Presentation')->schema([
                    DatePicker::make('work_date')->label('Exact date')->nullable(),
                    Toggle::make('featured_on_home')->label('Feature on home when newest year is shared')->default(false),
                ])->columns(['md' => 2]),
        ];
    }

    /** @param array<string, mixed> $data */
    public function save(Artwork $artwork, array $data, bool $allowGalleryAssignment = false): bool
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
        $uploadedAsset = $upload instanceof TemporaryUploadedFile
            ? app(MediaIngestService::class)->ingest($upload)
            : null;

        DB::transaction(function () use ($artwork, $data, $uploadedAsset, $assetId, $currentAssetId, $currentPrimary, $currentGalleryId, $requestedGalleryId, $allowGalleryAssignment): void {
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
            } elseif ($assetId > 0 && $assetId !== $currentAssetId) {
                /** @var MediaAsset $asset */
                $asset = MediaAsset::query()->findOrFail($assetId);
                $currentPrimary === null
                    ? $primaryMedia->attachAsset($artwork, $asset)
                    : $primaryMedia->replaceAsset($artwork, $asset);
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
