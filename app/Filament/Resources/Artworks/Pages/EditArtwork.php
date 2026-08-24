<?php

namespace App\Filament\Resources\Artworks\Pages;

use App\Domain\Admin\AdminAuditService;
use App\Domain\Artwork\ArtworkGalleryAssignmentService;
use App\Filament\Concerns\UsesAdminEditor;
use App\Filament\Resources\Artworks\ArtworkResource;
use App\Models\Artwork;
use App\Models\ArtworkCategory;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

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

    private function artworkRecord(): Artwork
    {
        /** @var Artwork $record */
        $record = $this->getRecord();

        return $record;
    }
}
