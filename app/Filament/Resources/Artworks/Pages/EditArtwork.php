<?php

namespace App\Filament\Resources\Artworks\Pages;

use App\Domain\Admin\AdminAuditService;
use App\Domain\Artwork\ArtworkDraftService;
use App\Domain\Artwork\ArtworkGalleryAssignmentService;
use App\Domain\Artwork\ArtworkPrimaryMediaService;
use App\Domain\Artwork\ArtworkPublicationService;
use App\Domain\Media\MediaAssetEditorialService;
use App\Domain\Media\MediaTypePolicy;
use App\Filament\Concerns\UsesAdminEditor;
use App\Filament\Resources\Artworks\ArtworkResource;
use App\Models\Artwork;
use App\Models\ArtworkCategory;
use App\Models\ArtworkMedia;
use App\Models\MediaAsset;
use Filament\Actions\Action;
use Filament\Actions\ViewAction;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;
use LogicException;

class EditArtwork extends EditRecord
{
    use UsesAdminEditor;

    protected static string $resource = ArtworkResource::class;

    public bool $returnToGallery = false;

    public function mount(int|string $record): void
    {
        parent::mount($record);
        $this->returnToGallery = request()->integer('gallery') > 0;
    }

    protected function mutateFormDataBeforeSave(array $data): array
    {
        if (! array_key_exists('artwork_category_id', $data) || ! array_key_exists('work_date', $data)) {
            throw ValidationException::withMessages(['artwork' => 'Required artwork form data is missing.']);
        }

        /** @var Artwork $record */
        $record = $this->getRecord();
        $categoryId = filled($data['artwork_category_id'] ?? null) ? (int) $data['artwork_category_id'] : null;
        $category = $categoryId === null ? null : ArtworkCategory::query()->find($categoryId);
        if ($categoryId !== null && ! $category instanceof ArtworkCategory) {
            throw ValidationException::withMessages([
                'artwork_category_id' => 'The artwork category is invalid.',
            ]);
        }
        if ($record->getAttribute('state') === 'published') {
            if (! $category instanceof ArtworkCategory || ! $category->siteSection()->where('state', 'published')->exists()) {
                throw ValidationException::withMessages([
                    'artwork_category_id' => 'Published artwork requires a published Gallery.',
                ]);
            }
        }

        $data['artwork_category_id'] = $categoryId;
        $data['date_precision'] = filled($data['work_date']) ? 'day' : 'unknown';

        foreach (['state', 'published_at', 'legacy_id', 'legacy_source', 'legacy_date_raw', 'migration_batch_id', 'migrated_at'] as $field) {
            unset($data[$field]);
        }
        unset($data['position']);

        return $data;
    }

    protected function handleRecordUpdate(Model $record, array $data): Model
    {
        $actor = app(AdminAuditService::class)->requireActor();

        return DB::transaction(function () use ($record, $data, $actor): Model {
            if (! array_key_exists('artwork_category_id', $data)) {
                throw ValidationException::withMessages([
                    'artwork_category_id' => 'Gallery form data is missing.',
                ]);
            }

            /** @var Artwork $artwork */
            $artwork = $record;
            unset($data['position']);
            $originalCategoryId = $artwork->getRawOriginal('artwork_category_id');
            $originalCategoryId = $originalCategoryId === null ? null : (int) $originalCategoryId;
            $targetCategoryId = $data['artwork_category_id'] === null ? null : (int) $data['artwork_category_id'];

            if ($targetCategoryId !== $originalCategoryId) {
                if ($targetCategoryId === null) {
                    app(ArtworkGalleryAssignmentService::class)->detach($artwork);
                } else {
                    /** @var ArtworkCategory|null $destination */
                    $destination = ArtworkCategory::query()->find($targetCategoryId);
                    if (! $destination) {
                        throw ValidationException::withMessages(['artwork_category_id' => 'The artwork category is invalid.']);
                    }
                    app(ArtworkGalleryAssignmentService::class)->reassign($artwork, $destination);
                }
                $artwork->refresh();
            }

            unset($data['artwork_category_id']);
            $artwork->fill($data);

            if ($artwork->getAttribute('state') === 'published'
                && ! $artwork->category()->whereHas('siteSection', static fn ($query) => $query->where('state', 'published'))->exists()) {
                throw ValidationException::withMessages([
                    'artwork_category_id' => 'Published artwork requires a published Gallery.',
                ]);
            }

            if ($artwork->isDirty()) {
                $artwork->save();
                app(AdminAuditService::class)->record($actor, 'artwork.updated', 'artwork', $artwork->getKey());
            }

            return $artwork;
        });
    }

    protected function getRedirectUrl(): string
    {
        $galleryId = $this->artworkRecord()->getAttribute('artwork_category_id');
        if ($this->returnToGallery && $galleryId !== null) {
            return ArtworkResource::getUrl('gallery', ['gallery' => (int) $galleryId]);
        }

        return $this->editorReturnUrl(ArtworkResource::getUrl('index'));
    }

    protected function getHeaderActions(): array
    {
        return [
            ViewAction::make(),
            Action::make('uploadPrimaryMedia')
                ->label('Upload primary media')
                ->visible(fn (): bool => ! $this->artworkRecord()->artworkMedia()->where('role', 'primary')->exists())
                ->schema([
                    FileUpload::make('media')
                        ->required()
                        ->storeFiles(false)
                        ->acceptedFileTypes([...MediaTypePolicy::IMAGE_MIME_TYPES, ...MediaTypePolicy::VIDEO_MIME_TYPES])
                        ->maxSize((int) ceil(MediaTypePolicy::maxUploadBytes() / 1024)),
                ])
                ->action(function (array $data): void {
                    if (! array_key_exists('media', $data) || ! $data['media'] instanceof TemporaryUploadedFile) {
                        throw ValidationException::withMessages(['media' => 'A valid uploaded image or video is required.']);
                    }
                    try {
                        app(ArtworkPrimaryMediaService::class)->attachUpload($this->artworkRecord(), $data['media']);
                    } catch (ValidationException) {
                        Notification::make()->title('Primary media upload failed')->danger()->send();

                        return;
                    }

                    $this->artworkRecord()->refresh();
                    Notification::make()->title('Primary media uploaded')->body('Add canonical ALT text before publishing.')->success()->send();
                }),
            Action::make('replacePrimaryMedia')
                ->label('Replace primary media')
                ->visible(fn (): bool => $this->primaryArtworkMedia() !== null)
                ->requiresConfirmation()
                ->schema([
                    FileUpload::make('media')
                        ->required()
                        ->storeFiles(false)
                        ->acceptedFileTypes([...MediaTypePolicy::IMAGE_MIME_TYPES, ...MediaTypePolicy::VIDEO_MIME_TYPES])
                        ->maxSize((int) ceil(MediaTypePolicy::maxUploadBytes() / 1024)),
                ])
                ->action(function (array $data): void {
                    if (! array_key_exists('media', $data) || ! $data['media'] instanceof TemporaryUploadedFile) {
                        throw ValidationException::withMessages(['media' => 'A valid uploaded image or video is required.']);
                    }
                    try {
                        app(ArtworkPrimaryMediaService::class)->replaceUpload($this->artworkRecord(), $data['media']);
                    } catch (ValidationException) {
                        Notification::make()->title('Primary media could not be replaced')->danger()->send();

                        return;
                    }

                    $this->artworkRecord()->refresh();
                    Notification::make()->title('Primary media replaced')->body('Add canonical ALT text for the new media before publishing.')->success()->send();
                }),
            Action::make('publish')
                ->label('Publish')
                ->visible(fn (): bool => $this->artworkRecord()->getAttribute('state') !== 'published')
                ->action(function (): void {
                    try {
                        app(ArtworkPublicationService::class)->publish($this->artworkRecord());
                    } catch (ValidationException) {
                        Notification::make()->title('Artwork cannot be published')->danger()->send();

                        return;
                    }

                    $this->artworkRecord()->refresh();
                    Notification::make()->title('Artwork published')->success()->send();
                }),
            Action::make('unpublish')
                ->label('Unpublish')
                ->visible(fn (): bool => $this->artworkRecord()->getAttribute('state') === 'published')
                ->requiresConfirmation()
                ->action(function (): void {
                    app(ArtworkPublicationService::class)->unpublish($this->artworkRecord());
                    $this->artworkRecord()->refresh();
                    Notification::make()->title('Artwork unpublished')->success()->send();
                }),
            Action::make('delete')
                ->label('Delete')
                ->color('danger')
                ->visible(fn (): bool => $this->artworkRecord()->getAttribute('state') === 'draft')
                ->requiresConfirmation()
                ->modalDescription('The Artwork and its media usages will be removed. Referenced media assets remain in Media.')
                ->action(function (): void {
                    $galleryId = $this->artworkRecord()->getAttribute('artwork_category_id');
                    app(ArtworkDraftService::class)->delete($this->artworkRecord());
                    Notification::make()->title('Artwork deleted')->success()->send();
                    $this->redirect($galleryId === null
                        ? ArtworkResource::getUrl('index')
                        : ArtworkResource::getUrl('gallery', ['gallery' => (int) $galleryId]));
                }),
            Action::make('editPrimaryAlt')
                ->label('Edit primary media ALT text')
                ->visible(fn (): bool => $this->primaryArtworkMedia() !== null)
                ->schema([
                    TextInput::make('alt_text')
                        ->label('Canonical media ALT text')
                        ->helperText('Required for publication. This description follows the media asset wherever it is reused.')
                        ->required()
                        ->maxLength(500)
                        ->default(fn (): ?string => $this->primaryMediaAsset()->getAttribute('alt_text')),
                    TextInput::make('alt_text_override')
                        ->label('Artwork-specific ALT override')
                        ->helperText('Optional. Use only when this artwork needs a more specific description than the canonical media ALT text.')
                        ->maxLength(500)
                        ->nullable()
                        ->default(function (): ?string {
                            $primary = $this->primaryArtworkMedia();
                            if (! $primary instanceof ArtworkMedia) {
                                throw new LogicException('Artwork has no primary media usage.');
                            }

                            return $primary->getAttribute('alt_text_override');
                        }),
                ])
                ->action(function (array $data): void {
                    try {
                        if (! array_key_exists('alt_text', $data) || ! array_key_exists('alt_text_override', $data)) {
                            throw ValidationException::withMessages(['alt_text' => 'ALT text form data is incomplete.']);
                        }

                        $service = app(MediaAssetEditorialService::class);
                        $service->updateMetadata($this->primaryMediaAsset(), ['alt_text' => $data['alt_text']]);
                        $service->updatePrimaryAltOverride($this->artworkRecord(), $data['alt_text_override']);
                    } catch (ValidationException) {
                        Notification::make()->title('Primary media ALT text could not be updated')->danger()->send();

                        return;
                    }

                    $this->artworkRecord()->refresh();
                    Notification::make()->title('Primary media ALT text updated')->success()->send();
                }),
        ];
    }

    private function artworkRecord(): Artwork
    {
        /** @var Artwork $record */
        $record = $this->getRecord();

        return $record;
    }

    private function primaryArtworkMedia(): ?ArtworkMedia
    {
        $media = $this->artworkRecord()->artworkMedia()->where('role', 'primary')->get();
        if ($media->count() > 1) {
            throw new LogicException('Artwork has multiple primary media usages.');
        }

        /** @var ArtworkMedia|null $primary */
        $primary = $media->first();

        return $primary;
    }

    private function primaryMediaAsset(): MediaAsset
    {
        $primary = $this->primaryArtworkMedia();
        if (! $primary instanceof ArtworkMedia) {
            throw new LogicException('Artwork has no primary media usage.');
        }

        $asset = $primary->mediaAsset()->first();
        if (! $asset instanceof MediaAsset) {
            throw new LogicException('Artwork primary media asset is unavailable.');
        }

        return $asset;
    }
}
