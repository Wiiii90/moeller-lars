<?php

namespace App\Filament\Resources\Artworks\RelationManagers;

use App\Domain\Artwork\ArtworkEditorialService;
use App\Domain\Media\MediaIngestService;
use App\Filament\Resources\MediaAssets\MediaAssetResource;
use App\Models\Artwork;
use App\Models\ArtworkMedia;
use App\Models\MediaAsset;
use App\Models\MediaVariant;
use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Actions\DetachAction;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables\Columns\ImageColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Validation\ValidationException;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;

final class GalleryImagesRelationManager extends RelationManager
{
    protected static string $relationship = 'artworkMedia';

    protected static ?string $title = 'Gallery images';

    public function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query): Builder => $query
                ->where('role', 'additional')
                ->with('mediaAsset.variants'))
            ->columns([
                ImageColumn::make('preview')
                    ->label('Preview')
                    ->state(fn (ArtworkMedia $record): ?string => $this->thumbnailUrl($record))
                    ->imageHeight(180),
                TextColumn::make('mediaAsset.original_filename')
                    ->label('File')
                    ->wrap(),
                TextColumn::make('alt_status')
                    ->label('ALT')
                    ->state(function (ArtworkMedia $record): string {
                        $override = trim((string) ($record->getAttribute('alt_text_override') ?? ''));
                        if ($override !== '') {
                            return 'Override set';
                        }

                        $asset = $this->asset($record);

                        return $asset !== null && filled($asset->getAttribute('alt_text')) ? 'Canonical set' : 'Missing';
                    }),
                TextColumn::make('position')->label('Order'),
            ])
            ->defaultSort('position')
            ->headerActions([
                Action::make('attachExisting')
                    ->label('Attach from Media')
                    ->schema([
                        Select::make('media_asset_id')
                            ->label('Media asset')
                            ->options(fn (): array => MediaAsset::query()
                                ->where('state', 'available')
                                ->orderByDesc('created_at')
                                ->pluck('original_filename', 'id')
                                ->all())
                            ->searchable()
                            ->required(),
                        TextInput::make('alt_text_override')
                            ->label('ALT text override')
                            ->maxLength(500)
                            ->nullable()
                            ->helperText('Optional. Leave empty to use the canonical media ALT text.'),
                    ])
                    ->action(function (array $data): void {
                        $asset = MediaAsset::query()->findOrFail($data['media_asset_id']);
                        try {
                            app(ArtworkEditorialService::class)->attachAdditionalMedia(
                                $this->artwork(),
                                $asset,
                                $data['alt_text_override'] ?? null,
                            );
                        } catch (ValidationException $exception) {
                            Notification::make()
                                ->title('Image could not be attached')
                                ->body(collect($exception->errors())->flatten()->first())
                                ->danger()
                                ->send();

                            return;
                        }

                        Notification::make()->title('Image attached to artwork gallery')->success()->send();
                    }),
                Action::make('uploadAdditional')
                    ->label('Upload image')
                    ->schema([
                        FileUpload::make('media')
                            ->required()
                            ->storeFiles(false)
                            ->acceptedFileTypes(['image/jpeg', 'image/png', 'image/webp'])
                            ->maxSize((int) ceil(MediaIngestService::MAX_BYTES / 1024)),
                        TextInput::make('alt_text')
                            ->label('Canonical ALT text')
                            ->required()
                            ->maxLength(500),
                    ])
                    ->action(function (array $data): void {
                        if (! array_key_exists('media', $data) || ! $data['media'] instanceof TemporaryUploadedFile) {
                            throw ValidationException::withMessages(['media' => 'A valid uploaded image is required.']);
                        }

                        try {
                            app(ArtworkEditorialService::class)->uploadAdditionalMedia(
                                $this->artwork(),
                                $data['media'],
                                $data['alt_text'] ?? null,
                            );
                        } catch (ValidationException $exception) {
                            Notification::make()
                                ->title('Image upload failed')
                                ->body(collect($exception->errors())->flatten()->first())
                                ->danger()
                                ->send();

                            return;
                        }

                        Notification::make()->title('Image uploaded and attached')->success()->send();
                    }),
            ])
            ->recordActions([
                Action::make('preview')
                    ->label('Inspect')
                    ->url(fn (ArtworkMedia $record): string => $this->viewerUrl($record))
                    ->visible(fn (ArtworkMedia $record): bool => $this->asset($record)?->getAttribute('state') === 'available'),
                Action::make('moveUp')
                    ->label('Move up')
                    ->visible(fn (ArtworkMedia $record): bool => app(ArtworkEditorialService::class)->canMoveAdditionalMedia($this->artwork(), $record, 'up'))
                    ->action(function (ArtworkMedia $record): void {
                        app(ArtworkEditorialService::class)->moveAdditionalMedia($this->artwork(), $record, 'up');
                        Notification::make()->title('Gallery image moved up')->success()->send();
                    }),
                Action::make('moveDown')
                    ->label('Move down')
                    ->visible(fn (ArtworkMedia $record): bool => app(ArtworkEditorialService::class)->canMoveAdditionalMedia($this->artwork(), $record, 'down'))
                    ->action(function (ArtworkMedia $record): void {
                        app(ArtworkEditorialService::class)->moveAdditionalMedia($this->artwork(), $record, 'down');
                        Notification::make()->title('Gallery image moved down')->success()->send();
                    }),
                ActionGroup::make([
                    Action::make('editAltOverride')
                        ->label('Edit ALT override')
                        ->schema([
                            TextInput::make('alt_text_override')
                                ->label('ALT text override')
                                ->maxLength(500)
                                ->nullable()
                                ->default(fn (ArtworkMedia $record): ?string => $record->getAttribute('alt_text_override')),
                        ])
                        ->action(function (ArtworkMedia $record, array $data): void {
                            app(ArtworkEditorialService::class)->updateAdditionalMediaAltOverride(
                                $this->artwork(),
                                $record,
                                $data['alt_text_override'] ?? null,
                            );
                            Notification::make()->title('Gallery image ALT override updated')->success()->send();
                        }),
                    DetachAction::make()
                        ->label('Detach from artwork')
                        ->requiresConfirmation()
                        ->action(function (ArtworkMedia $record): void {
                            app(ArtworkEditorialService::class)->detachAdditionalMedia($this->artwork(), $record);
                            Notification::make()->title('Image detached; Media asset kept')->success()->send();
                        }),
                ]),
            ])
            ->emptyStateHeading('No additional gallery images')
            ->emptyStateDescription('Attach an existing Media asset or upload another image. The primary artwork image remains separate.');
    }

    private function artwork(): Artwork
    {
        /** @var Artwork $owner */
        $owner = $this->getOwnerRecord();

        return $owner;
    }

    private function asset(ArtworkMedia $usage): ?MediaAsset
    {
        $usage->loadMissing('mediaAsset');
        $asset = $usage->getRelationValue('mediaAsset');

        return $asset instanceof MediaAsset ? $asset : null;
    }

    private function thumbnailUrl(ArtworkMedia $usage): ?string
    {
        $asset = $this->asset($usage);
        if ($asset === null || $asset->getAttribute('state') !== 'available') {
            return null;
        }

        $asset->loadMissing('variants');
        /** @var MediaVariant|null $variant */
        $variant = $asset->getRelationValue('variants')->first(fn (MediaVariant $candidate): bool => $candidate->getAttribute('variant_kind') === 'thumbnail'
            && $candidate->getAttribute('transform_profile') === MediaIngestService::TRANSFORM_PROFILE
            && $candidate->getAttribute('state') === 'available');

        return $variant === null ? null : route('admin.media.variant', $variant);
    }

    private function viewerUrl(ArtworkMedia $usage): string
    {
        $asset = $this->asset($usage);

        return $asset instanceof MediaAsset
            ? MediaAssetResource::getUrl('view', ['record' => $asset->getKey(), 'artwork' => $this->artwork()->getKey()])
            : MediaAssetResource::getUrl('index');
    }
}
