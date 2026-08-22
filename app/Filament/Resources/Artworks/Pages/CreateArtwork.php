<?php

namespace App\Filament\Resources\Artworks\Pages;

use App\Domain\Artwork\ArtworkDraftService;
use App\Domain\Artwork\ArtworkEditorialService;
use App\Filament\Concerns\UsesEditorOverlay;
use App\Filament\Resources\Artworks\ArtworkResource;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Validation\ValidationException;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;

class CreateArtwork extends CreateRecord
{
    use UsesEditorOverlay;

    protected static string $resource = ArtworkResource::class;

    private string $primaryMediaResult = 'missing';

    protected function handleRecordCreation(array $data): Model
    {
        $primaryMedia = $data['primary_media'] ?? null;
        if ($primaryMedia !== null && ! $primaryMedia instanceof TemporaryUploadedFile) {
            throw ValidationException::withMessages(['primary_media' => 'A valid uploaded image is required.']);
        }

        $artwork = app(ArtworkDraftService::class)->create($data);

        if ($primaryMedia instanceof TemporaryUploadedFile) {
            try {
                app(ArtworkEditorialService::class)->attachPrimaryMedia($artwork, $primaryMedia);
                $this->primaryMediaResult = 'attached';
            } catch (ValidationException) {
                $this->primaryMediaResult = 'failed';
            }
        }

        return $artwork;
    }

    protected function getRedirectUrl(): string
    {
        $galleryId = (int) $this->getRecord()->getAttribute('artwork_category_id');

        return ArtworkResource::getUrl('gallery', ['gallery' => $galleryId]);
    }

    protected function getCreatedNotification(): Notification
    {
        return match ($this->primaryMediaResult) {
            'attached' => Notification::make()
                ->success()
                ->title('Artwork draft created')
                ->body('The primary image was attached. Add or confirm ALT text before publication.'),
            'failed' => Notification::make()
                ->warning()
                ->title('Artwork draft created; image needs attention')
                ->body('The draft is saved, but the primary image could not be attached. Add it from the artwork edit page.'),
            default => Notification::make()
                ->success()
                ->title('Artwork draft created')
                ->body('No primary image was attached yet. Add one before publication.'),
        };
    }
}
