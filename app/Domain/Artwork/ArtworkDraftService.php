<?php

namespace App\Domain\Artwork;

use App\Domain\Admin\AdminAuditService;
use App\Domain\Admin\EditorialRichTextValidator;
use App\Models\Artwork;
use App\Models\ArtworkCategory;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

final class ArtworkDraftService
{
    public function __construct(
        private readonly AdminAuditService $audit,
        private readonly ArtworkGalleryAssignmentService $assignments,
    ) {}

    /** @param array<string, mixed> $data */
    public function create(array $data): Artwork
    {
        if (! array_key_exists('artwork_category_id', $data)) {
            throw ValidationException::withMessages(['artwork' => 'Required artwork form data is missing.']);
        }

        $galleryId = (int) $data['artwork_category_id'];
        if ($galleryId <= 0) {
            throw ValidationException::withMessages(['artwork_category_id' => 'The Gallery is required.']);
        }

        // A submitted Position is an explicit override. The ordinary Create
        // form drops its live suggestion before calling this service, so
        // source-driven creation never accidentally switches to Manual.
        $requestedPosition = array_key_exists('position', $data)
            ? filter_var($data['position'], FILTER_VALIDATE_INT)
            : null;
        if ($requestedPosition === false || ($requestedPosition !== null && $requestedPosition < 1)) {
            throw ValidationException::withMessages(['position' => 'Choose a positive Artwork position.']);
        }

        // Drafts may be created without any editorial input. Preserve the
        // database's stable public identity requirements without imposing
        // publishing requirements on unfinished Artworks.
        $data['title'] = trim((string) ($data['title'] ?? '')) ?: 'Untitled';
        if (blank($data['slug'] ?? null)) {
            $base = mb_substr(Str::slug($data['title']), 0, 155);
            $base = $base !== '' ? $base : 'artwork';
            $slug = $base;
            $suffix = 2;
            while (Artwork::query()->where('slug', $slug)->exists()) {
                $slug = $base.'-'.$suffix++;
            }
            $data['slug'] = $slug;
        }

        $data = $this->validatedEditorialData($data);
        unset($data['position']);
        $data['artwork_category_id'] = $galleryId;
        $data['state'] = 'draft';
        $data['published_at'] = null;

        $actor = $this->audit->requireActor();

        return DB::transaction(function () use ($galleryId, $data, $actor, $requestedPosition): Artwork {
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

            if ($requestedPosition !== null) {
                $ids = GalleryPresentation::orderArtworks($gallery->artworks()->getQuery(), $gallery)
                    ->pluck('id')->map(static fn (mixed $id): int => (int) $id)->all();
                if ($requestedPosition > count($ids)) {
                    throw ValidationException::withMessages(['position' => 'Choose a position within the Gallery.']);
                }
                $currentIndex = array_search((int) $artwork->getKey(), $ids, true);
                array_splice($ids, $currentIndex, 1);
                array_splice($ids, $requestedPosition - 1, 0, [(int) $artwork->getKey()]);
                // An intentional Position override is the Manual handoff;
                // GalleryEditorialService owns both ranking and its audit.
                app(GalleryEditorialService::class)->reorderArtworks($gallery, $ids);
            } elseif (GalleryPresentation::sortMode($gallery) === GalleryPresentation::SORT_MANUAL && $maxPosition !== null) {
                // Manual Create inserts first. Do not duplicate ranking logic.
                $ids = $gallery->artworks()
                    ->whereKeyNot($artwork->getKey())
                    ->orderBy('position')
                    ->orderBy('id')
                    ->pluck('id')->map(static fn (mixed $id): int => (int) $id)->all();
                app(GalleryEditorialService::class)->reorderArtworks($gallery, [(int) $artwork->getKey(), ...$ids]);
            }

            return $artwork->fresh();
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
            $galleryId = $fresh->getAttribute('artwork_category_id');
            $fresh->artworkMedia()->delete();
            $fresh->delete();
            if (is_numeric($galleryId)) {
                // The existing Gallery assignment service owns canonical
                // position compaction for detach, move AND draft deletion.
                $this->assignments->normalizeGalleryPositions((int) $galleryId);
            }
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
            'year',
            'month',
            'day',
        ];
        $payload = array_intersect_key($data, array_flip($allowed));

        $validator = Validator::make($payload, [
            'artwork_category_id' => [$artwork === null ? 'required' : 'nullable', 'integer', 'min:1'],
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
            'year' => ['nullable', 'integer', 'min:1000', 'max:9999'],
            'month' => ['nullable', 'integer', 'between:1,12'],
            'day' => ['nullable', 'integer', 'between:1,31'],
        ]);
        $validator->validate();
        ArtworkDate::validateParts(array_merge([
            'year' => $artwork?->getAttribute('year'),
            'month' => $artwork?->getAttribute('month'),
            'day' => $artwork?->getAttribute('day'),
        ], array_intersect_key($payload, array_flip(['year', 'month', 'day']))));
        if (array_key_exists('description', $payload)) {
            app(EditorialRichTextValidator::class)->validate(
                $payload['description'],
                'description',
                allowEmbeddedMedia: true,
            );
        }

        return $payload;
    }
}
