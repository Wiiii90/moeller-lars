<?php

namespace App\Filament\Resources\Artworks\Pages;

use App\Domain\Admin\AdminAuditService;
use App\Filament\Resources\Artworks\ArtworkResource;
use App\Models\Artwork;
use App\Models\ArtworkCategory;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class CreateArtwork extends CreateRecord
{
    protected static string $resource = ArtworkResource::class;

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

        return DB::transaction(function () use ($data, $actor): Model {
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
    }
}
