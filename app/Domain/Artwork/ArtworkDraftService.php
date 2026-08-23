<?php

namespace App\Domain\Artwork;

use App\Domain\Admin\AdminAuditService;
use App\Models\Artwork;
use App\Models\ArtworkCategory;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
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

        $galleryId = (int) $data['artwork_category_id'];
        if ($galleryId <= 0) {
            throw ValidationException::withMessages(['artwork_category_id' => 'The Gallery is required.']);
        }

        $data = $this->validatedEditorialData($data);
        unset($data['position']);
        $data['artwork_category_id'] = $galleryId;
        $data['state'] = 'draft';
        $data['published_at'] = null;
        $data['date_precision'] = filled($data['work_date']) ? 'day' : 'unknown';
        $data['legacy_date_raw'] = null;
        $data['legacy_id'] = null;
        $data['legacy_source'] = null;
        $data['migration_batch_id'] = null;
        $data['migrated_at'] = null;

        $actor = $this->audit->requireActor();

        return DB::transaction(function () use ($galleryId, $data, $actor): Artwork {
            /** @var ArtworkCategory|null $gallery */
            $gallery = ArtworkCategory::query()->whereKey($galleryId)->lockForUpdate()->first();
            if (! $gallery instanceof ArtworkCategory) {
                throw ValidationException::withMessages(['artwork_category_id' => 'The Gallery is invalid.']);
            }

            $payload = $data;
            $maxPosition = $gallery->artworks()->max('position');
            $payload['position'] = $maxPosition === null ? 0 : ((int) $maxPosition) + 1;

            $artwork = new Artwork;
            $artwork->fill($payload);
            $artwork->save();

            $this->audit->record($actor, 'artwork.created', 'artwork', $artwork->getKey());

            return $artwork;
        });
    }

    /** @param array<string, mixed> $data */
    public function update(Artwork $artwork, array $data): Artwork
    {
        $actor = $this->audit->requireActor();

        return DB::transaction(function () use ($artwork, $data, $actor): Artwork {
            /** @var Artwork $fresh */
            $fresh = Artwork::query()->whereKey($artwork->getKey())->lockForUpdate()->firstOrFail();
            $payload = $this->validatedEditorialData($data, $fresh);

            if (
                $fresh->getAttribute('published_at') !== null
                && array_key_exists('slug', $payload)
                && (string) $payload['slug'] !== (string) $fresh->getAttribute('slug')
            ) {
                throw ValidationException::withMessages([
                    'slug' => 'The public URL slug is locked after first publication.',
                ]);
            }

            $payload['date_precision'] = filled($payload['work_date'] ?? null) ? 'day' : 'unknown';
            $fresh->fill($payload);

            if ($fresh->isDirty()) {
                $fresh->save();
                $this->audit->record($actor, 'artwork.updated', 'artwork', $fresh->getKey());
            }

            return $fresh->fresh(['category', 'artworkMedia.mediaAsset']);
        });
    }

    public function delete(Artwork $artwork): void
    {
        $actor = $this->audit->requireActor();

        DB::transaction(function () use ($artwork, $actor): void {
            /** @var Artwork $fresh */
            $fresh = Artwork::query()->whereKey($artwork->getKey())->lockForUpdate()->firstOrFail();
            if ((string) $fresh->getAttribute('state') !== 'draft') {
                throw ValidationException::withMessages([
                    'artwork' => 'Only an unpublished Artwork draft can be deleted.',
                ]);
            }

            $artworkId = (int) $fresh->getKey();
            $fresh->artworkMedia()->delete();
            $fresh->delete();
            $this->audit->record($actor, 'artwork.deleted', 'artwork', $artworkId);
        });
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function validatedEditorialData(array $data, ?Artwork $artwork = null): array
    {
        $allowed = [
            'artwork_category_id',
            'slug',
            'title',
            'medium',
            'dimensions',
            'description',
            'work_date',
            'work_year',
            'featured_on_home',
        ];
        $payload = array_intersect_key($data, array_flip($allowed));

        $validator = Validator::make($payload, [
            'artwork_category_id' => ['required', 'integer', 'min:1'],
            'slug' => [
                'required',
                'string',
                'max:180',
                'regex:/^[a-z0-9]+(?:-[a-z0-9]+)*$/',
                Rule::unique('artworks', 'slug')->ignore($artwork?->getKey()),
            ],
            'title' => ['required', 'string', 'max:240'],
            'medium' => ['nullable', 'string', 'max:240'],
            'dimensions' => ['nullable', 'string', 'max:240'],
            'description' => ['nullable', 'string', 'max:10000'],
            'work_date' => ['nullable', 'date'],
            'work_year' => ['nullable', 'integer', 'min:1000', 'max:9999'],
            'featured_on_home' => ['boolean'],
        ]);
        $validator->validate();

        return $payload;
    }
}
