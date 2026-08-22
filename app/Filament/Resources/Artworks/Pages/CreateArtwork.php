<?php

namespace App\Filament\Resources\Artworks\Pages;

use App\Domain\Admin\AdminAuditService;
use App\Domain\Artwork\ArtworkEditorialService;
use App\Filament\Concerns\UsesAdminEditor;
use App\Filament\Resources\Artworks\ArtworkResource;
use App\Models\Artwork;
use App\Models\ArtworkCategory;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;

class CreateArtwork extends CreateRecord
{
    use UsesAdminEditor;

    protected static string $resource = ArtworkResource::class;

    private string $primaryMediaResult = 'missing';

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        if (! array_key_exists('artwork_category_id', $data) || ! array_key_exists('work_date', $data)) {
            throw ValidationException::withMessages(['artwork' => 'Required artwork form data is missing.']);
        }

        unset($data['position']);
        $data['state'] = 'draft';
        $data['published_at'] = null;
        $data['date_precision'] = filled($data['work_date']) ? 'day' : 'unknown';
        $data['legacy_date_raw'] = null;

        return $data;
    }

    protected function handleRecordCreation(array $data): Model
    {
        $actor = app(AdminAuditService::class)->requireActor();
        $primaryMedia = $data['primary_media'] ?? null;
        unset($data['primary_media']);

        if ($primaryMedia !== null && ! $primaryMedia instanceof TemporaryUploadedFile) {
            throw ValidationException::withMessages(['primary_media' => 'A valid uploaded image is required.']);
        }

        /** @var Artwork $artwork */
        $artwork = DB::transaction(function () use ($data, $actor): Artwork {
            if (! array_key_exists('artwork_category_id', $data)) {
                throw ValidationException::withMessages([
                    'artwork_category_id' => 'The artwork category is required.',
                ]);
            }

            $categoryId = $data['artwork_category_id'];
            /** @var ArtworkCategory|null $category */
            $category = ArtworkCategory::query()->whereKey($categoryId)->lockForUpdate()->first();
            if (! $category) {
                throw ValidationException::withMessages([
                    'artwork_category_id' => 'The artwork category is invalid.',
                ]);
            }

            unset($data['position']);
            $maxPosition = $category->artworks()->max('position');
            $data['position'] = $maxPosition === null ? 0 : ((int) $maxPosition) + 1;
            $artwork = new Artwork;
            $artwork->fill($data);
            $artwork->save();
            app(AdminAuditService::class)->record($actor, 'artwork.created', 'artwork', $artwork->getKey());

            return $artwork;
        });

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
