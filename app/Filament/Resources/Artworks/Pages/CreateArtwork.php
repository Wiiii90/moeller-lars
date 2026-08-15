<?php

namespace App\Filament\Resources\Artworks\Pages;

use App\Filament\Resources\Artworks\ArtworkResource;
use Filament\Resources\Pages\CreateRecord;

class CreateArtwork extends CreateRecord
{
    protected static string $resource = ArtworkResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $data['state'] = 'draft';
        $data['published_at'] = null;
        $data['date_precision'] = filled($data['work_date'] ?? null) ? 'day' : 'unknown';
        $data['legacy_date_raw'] = null;

        return $data;
    }
}
