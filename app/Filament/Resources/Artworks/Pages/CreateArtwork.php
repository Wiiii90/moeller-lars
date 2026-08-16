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
        unset($data['position']);
        $data['state'] = 'draft';
        $data['published_at'] = null;
        $data['date_precision'] = filled($data['work_date'] ?? null) ? 'day' : 'unknown';
        $data['legacy_date_raw'] = null;

        return $data;
    }

    protected function handleRecordCreation(array $data): Model
    {
        $actor = app(AdminAuditService::class)->requireActor();

        return DB::transaction(function () use ($data, $actor): Model {
            $categoryId = $data['artwork_category_id'] ?? null;
            /** @var ArtworkCategory|null $category */
            $category = ArtworkCategory::query()->whereKey($categoryId)->lockForUpdate()->first();
            if (! $category) {
                throw ValidationException::withMessages([
                    'artwork_category_id' => 'The artwork category is invalid.',
                ]);
            }

            unset($data['position']);
            $data['position'] = ((int) ($category->artworks()->max('position') ?? -1)) + 1;
            $artwork = new Artwork;
            $artwork->fill($data);
            $artwork->save();
            app(AdminAuditService::class)->record($actor, 'artwork.created', 'artwork', $artwork->getKey());

            return $artwork;
        });
    }
}
