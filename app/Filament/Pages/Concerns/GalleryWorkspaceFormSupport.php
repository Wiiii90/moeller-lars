<?php

namespace App\Filament\Pages\Concerns;

use App\Domain\Artwork\ArtworkDimensions;
use App\Domain\Media\MediaTypePolicy;
use App\Filament\Resources\Artworks\Support\ArtworkMaterialSelect;
use App\Filament\Support\MediaAssetSelect;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Grid;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;

trait GalleryWorkspaceFormSupport
{
    private function artworkCreateFormSchema(): array
    {
        $slug = TextInput::make('slug')
            ->label('Public URL slug')
            ->required()
            ->maxLength(180)
            ->regex('/^[a-z0-9]+(?:-[a-z0-9]+)*$/')
            ->unique('artworks', 'slug');

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

    /** @return array<string, mixed> */
    private function normalizeArtworkFormData(array $data): array
    {
        $data['artwork_category_id'] = (int) $this->galleryContext['id'];
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
