<?php

namespace App\Filament\Support;

use App\Domain\Artwork\ArtworkDimensions;
use App\Domain\Artwork\ArtworkDraftService;
use App\Domain\Artwork\ArtworkPrimaryMediaService;
use App\Domain\Media\MediaTypePolicy;
use App\Filament\Resources\Artworks\Support\ArtworkMaterialSelect;
use App\Models\Artwork;
use App\Models\MediaAsset;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
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
            'medium' => $artwork->getAttribute('medium'),
            'dimension_height' => $dimensions['height'],
            'dimension_width' => $dimensions['width'],
            'dimension_depth' => $dimensions['depth'],
            'dimension_unit' => $dimensions['unit'],
            'dimension_custom' => $dimensions['custom'],
            'description' => $artwork->getAttribute('description'),
            'primary_media_asset_id' => $primary?->getAttribute('media_asset_id'),
            'work_year' => $artwork->getAttribute('work_year'),
            'work_date' => $artwork->getAttribute('work_date'),
            'featured_on_home' => (bool) $artwork->getAttribute('featured_on_home'),
        ];
    }

    /** @return list<mixed> */
    public function schema(): array
    {
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
                    TextInput::make('slug')
                        ->label('Public URL slug')
                        ->required()
                        ->maxLength(180)
                        ->regex('/^[a-z0-9]+(?:-[a-z0-9]+)*$/')
                        ->helperText('The URL is locked after first publication.'),
                    ArtworkMaterialSelect::make(),
                    TextInput::make('work_year')->label('Year')->numeric()->minValue(1000)->maxValue(9999)->nullable(),
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
                        ->helperText('Fallback for diameter, variable dimensions or legacy/free-form notation. When set, this value is saved instead of H × W × D.')
                        ->columnSpanFull(),
                    Textarea::make('description')->nullable()->maxLength(10000)->columnSpanFull(),
                    MediaAssetSelect::makeId(
                        'primary_media_asset_id',
                        'Existing Media File',
                        allowedMimeTypes: self::primaryMimeTypes(),
                    )
                        ->placeholder('No primary media')
                        ->nullable()
                        ->helperText('Images and videos only.')
                        ->columnSpanFull(),
                    FileUpload::make('primary_upload')
                        ->label('Or upload new primary media')
                        ->storeFiles(false)
                        ->acceptedFileTypes(self::primaryMimeTypes())
                        ->maxSize((int) ceil(MediaTypePolicy::maxUploadBytes() / 1024))
                        ->helperText('JPEG, PNG, WebP, H.264 MP4 or supported WebM. The file is ingested into Storage first.')
                        ->columnSpanFull(),
                    DatePicker::make('work_date')->label('Exact date')->helperText('If set, the year is derived from this date.')->nullable(),
                    Toggle::make('featured_on_home')->label('Feature on home when newest year is shared')->default(false),
                ]),
        ];
    }

    /** @param array<string, mixed> $data */
    public function save(Artwork $artwork, array $data): bool
    {
        $currentPrimary = $artwork->artworkMedia()->where('role', 'primary')->first();
        $currentAssetId = $currentPrimary === null ? 0 : (int) $currentPrimary->getAttribute('media_asset_id');
        $before = [
            'artwork' => $artwork->getAttributes(),
            'primary_media_asset_id' => $currentAssetId,
        ];
        $assetId = (int) ($data['primary_media_asset_id'] ?? 0);
        $upload = $data['primary_upload'] ?? null;

        if ($upload !== null && ! $upload instanceof TemporaryUploadedFile) {
            throw ValidationException::withMessages(['primary_upload' => 'Choose a valid image or video file.']);
        }
        if ($upload instanceof TemporaryUploadedFile && $assetId > 0 && $assetId !== $currentAssetId) {
            throw ValidationException::withMessages(['primary_upload' => 'Choose either a new upload or an existing Media File, not both.']);
        }
        if ($upload instanceof TemporaryUploadedFile) {
            $this->assertUploadIsPrimaryMedia($upload);
        }

        DB::transaction(function () use ($artwork, $data, $upload, $assetId, $currentAssetId, $currentPrimary): void {
            app(ArtworkDraftService::class)->update($artwork, $this->normalizeArtworkFormData($artwork, $data));

            if ($upload instanceof TemporaryUploadedFile) {
                $currentPrimary === null
                    ? app(ArtworkPrimaryMediaService::class)->attachUpload($artwork, $upload)
                    : app(ArtworkPrimaryMediaService::class)->replaceUpload($artwork, $upload);
            } elseif ($assetId > 0 && $assetId !== $currentAssetId) {
                /** @var MediaAsset $asset */
                $asset = MediaAsset::query()->findOrFail($assetId);
                $currentPrimary === null
                    ? app(ArtworkPrimaryMediaService::class)->attachAsset($artwork, $asset)
                    : app(ArtworkPrimaryMediaService::class)->replaceAsset($artwork, $asset);
            }
        });

        /** @var Artwork $freshArtwork */
        $freshArtwork = Artwork::query()->findOrFail($artwork->getKey());
        $freshPrimary = $freshArtwork->artworkMedia()->where('role', 'primary')->first();
        $after = [
            'artwork' => $freshArtwork->getAttributes(),
            'primary_media_asset_id' => $freshPrimary === null ? 0 : (int) $freshPrimary->getAttribute('media_asset_id'),
        ];

        return $before !== $after;
    }

    /** @param array<string, mixed> $data
     * @return array<string, mixed>
     */
    private function normalizeArtworkFormData(Artwork $artwork, array $data): array
    {
        $galleryId = filter_var($artwork->getAttribute('artwork_category_id'), FILTER_VALIDATE_INT);
        if ($galleryId === false || $galleryId <= 0) {
            throw ValidationException::withMessages(['artwork' => 'Artwork must belong to a Gallery before it can be edited here.']);
        }

        $data['artwork_category_id'] = (int) $galleryId;
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
