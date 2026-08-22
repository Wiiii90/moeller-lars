<?php

namespace App\Domain\Artwork;

use App\Domain\Admin\AdminAuditService;
use App\Models\Artwork;
use App\Models\ArtworkCategory;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final class ArtworkDraftService
{
    public function __construct(private readonly AdminAuditService $audit) {}

    /** @param array<string, mixed> $data */
    public function create(array $data): Artwork
    {
        if (! array_key_exists('artwork_category_id', $data) || ! array_key_exists('work_date', $data)) {
            throw ValidationException::withMessages(['artwork' => 'Required artwork form data is missing.']);
        }

        $categoryId = (int) $data['artwork_category_id'];
        if ($categoryId <= 0) {
            throw ValidationException::withMessages(['artwork_category_id' => 'The artwork category is required.']);
        }

        unset($data['primary_media'], $data['position']);
        $data['artwork_category_id'] = $categoryId;
        $data['state'] = 'draft';
        $data['published_at'] = null;
        $data['date_precision'] = filled($data['work_date']) ? 'day' : 'unknown';
        $data['legacy_date_raw'] = null;
        $data['legacy_id'] = null;
        $data['legacy_source'] = null;
        $data['migration_batch_id'] = null;
        $data['migrated_at'] = null;

        $actor = $this->audit->requireActor();

        return DB::transaction(function () use ($categoryId, $data, $actor): Artwork {
            /** @var ArtworkCategory|null $category */
            $category = ArtworkCategory::query()->whereKey($categoryId)->lockForUpdate()->first();
            if (! $category instanceof ArtworkCategory) {
                throw ValidationException::withMessages(['artwork_category_id' => 'The artwork category is invalid.']);
            }

            $payload = $data;
            $maxPosition = $category->artworks()->max('position');
            $payload['position'] = $maxPosition === null ? 0 : ((int) $maxPosition) + 1;

            $artwork = new Artwork;
            $artwork->fill($payload);
            $artwork->save();

            $this->audit->record($actor, 'artwork.created', 'artwork', $artwork->getKey());

            return $artwork;
        });
    }
}
