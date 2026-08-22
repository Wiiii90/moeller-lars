<?php

namespace App\Domain\Artwork;

use App\Domain\Admin\AdminAuditService;
use App\Domain\Content\SiteNodeType;
use App\Domain\Content\SiteSectionPathPolicy;
use App\Models\Artwork;
use App\Models\ArtworkCategory;
use App\Models\Redirect;
use App\Models\SiteSection;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final class GalleryEditorialService
{
    public function __construct(
        private readonly AdminAuditService $adminAuditService,
        private readonly SiteSectionPathPolicy $pathPolicy,
    ) {}

    public function create(array $data): ArtworkCategory
    {
        $actor = $this->adminAuditService->requireActor();
        $validated = $this->validateData($data, false);
        $parentSectionId = $this->validateParentSectionId($data['parent_section_id'] ?? null);

        return DB::transaction(function () use ($validated, $parentSectionId, $actor): ArtworkCategory {
            $gallery = new ArtworkCategory;
            $gallery->fill($validated);
            $gallery->save();

            SiteSection::query()->create([
                'type' => SiteNodeType::Gallery->value,
                'title' => (string) $gallery->getAttribute('name'),
                'navigation_label' => (string) $gallery->getAttribute('name'),
                'slug' => (string) $gallery->getAttribute('slug'),
                'state' => 'hidden',
                'position' => $this->nextSectionPosition($parentSectionId),
                'show_in_navigation' => false,
                'parent_id' => $parentSectionId,
                'artwork_category_id' => (int) $gallery->getKey(),
            ]);

            $this->adminAuditService->record($actor, 'artwork_category.created', 'artwork_category', $gallery->getKey());

            return $gallery;
        });
    }

    public function update(ArtworkCategory $gallery, array $data): ArtworkCategory
    {
        $actor = $this->adminAuditService->requireActor();
        $validated = $this->validateData($data, true);

        return DB::transaction(function () use ($gallery, $validated, $actor): ArtworkCategory {
            /** @var ArtworkCategory $fresh */
            $fresh = ArtworkCategory::query()->whereKey($gallery->getKey())->lockForUpdate()->firstOrFail();
            $fresh->fill($validated);

            if (! $fresh->isDirty()) {
                return $fresh;
            }

            $nameChanged = $fresh->isDirty('name');
            $fresh->save();

            if ($nameChanged) {
                SiteSection::query()
                    ->where('type', SiteNodeType::Gallery->value)
                    ->where('artwork_category_id', $fresh->getKey())
                    ->update([
                        'title' => (string) $fresh->getAttribute('name'),
                        'updated_at' => now(),
                    ]);
            }

            $this->adminAuditService->record($actor, 'artwork_category.updated', 'artwork_category', $fresh->getKey());

            return $fresh;
        });
    }

    public function changeSlug(ArtworkCategory $gallery, string $slug): ArtworkCategory
    {
        $actor = $this->adminAuditService->requireActor();

        return DB::transaction(function () use ($gallery, $slug, $actor): ArtworkCategory {
            /** @var ArtworkCategory $fresh */
            $fresh = ArtworkCategory::query()->whereKey($gallery->getKey())->lockForUpdate()->firstOrFail();
            $oldSlug = (string) $fresh->getAttribute('slug');

            $newSlug = $this->validateSlug($slug, $fresh->getKey());
            if ($newSlug === $oldSlug) {
                return $fresh;
            }

            $oldPath = '/'.$oldSlug;
            $newPath = '/'.$newSlug;
            $ownedReason = SiteSectionPathPolicy::GALLERY_SLUG_REDIRECT_REASON;

            Redirect::query()->where('reason', $ownedReason)->where('target_path', $oldPath)->update(['target_path' => $newPath]);

            /** @var Redirect|null $sourceRedirect */
            $sourceRedirect = Redirect::query()->where('source_path', $oldPath)->lockForUpdate()->first();
            if ($sourceRedirect !== null && $sourceRedirect->getAttribute('reason') !== $ownedReason) {
                throw ValidationException::withMessages(['slug' => 'The Gallery path is already reserved by another redirect.']);
            }

            if ($sourceRedirect === null) {
                $sourceRedirect = new Redirect;
                $sourceRedirect->fill([
                    'source_path' => $oldPath,
                    'target_path' => $newPath,
                    'status_code' => 301,
                    'enabled' => true,
                    'reason' => $ownedReason,
                ]);
                $sourceRedirect->save();
            } else {
                $sourceRedirect->update([
                    'target_path' => $newPath,
                    'status_code' => 301,
                    'enabled' => true,
                ]);
            }

            $fresh->setAttribute('slug', $newSlug);
            $fresh->save();

            SiteSection::query()
                ->where('type', SiteNodeType::Gallery->value)
                ->where('artwork_category_id', $fresh->getKey())
                ->update([
                    'slug' => $newSlug,
                    'updated_at' => now(),
                ]);

            $this->adminAuditService->record($actor, 'artwork_category.slug_changed', 'artwork_category', $fresh->getKey());

            return $fresh;
        });
    }

    public function delete(ArtworkCategory $gallery): void
    {
        $actor = $this->adminAuditService->requireActor();

        DB::transaction(function () use ($gallery, $actor): void {
            /** @var ArtworkCategory $fresh */
            $fresh = ArtworkCategory::query()->whereKey($gallery->getKey())->lockForUpdate()->firstOrFail();
            /** @var SiteSection|null $section */
            $section = SiteSection::query()
                ->where('type', SiteNodeType::Gallery->value)
                ->where('artwork_category_id', $fresh->getKey())
                ->lockForUpdate()
                ->first();

            if (
                $section === null
                || (string) $section->getAttribute('state') !== 'hidden'
                || $fresh->artworks()->exists()
                || SiteSection::query()->where('parent_id', $section->getKey())->exists()
            ) {
                throw ValidationException::withMessages(['gallery' => 'This Gallery cannot be deleted while it is public or owns artwork or submenu Galleries.']);
            }

            $path = '/'.$fresh->getAttribute('slug');
            $this->adminAuditService->record($actor, 'artwork_category.deleted', 'artwork_category', $fresh->getKey());
            Redirect::query()
                ->where('reason', SiteSectionPathPolicy::GALLERY_SLUG_REDIRECT_REASON)
                ->where(fn (Builder $query) => $query->where('source_path', $path)->orWhere('target_path', $path))
                ->delete();
            $section->delete();
            $fresh->delete();
        });
    }

    public function reorderArtworks(ArtworkCategory $gallery, array $artworkIds): ArtworkCategory
    {
        $actor = $this->adminAuditService->requireActor();
        /** @var ArtworkCategory $freshGallery */
        $freshGallery = ArtworkCategory::query()->findOrFail($gallery->getKey());
        /** @var Collection<int, Artwork> $freshArtworks */
        $freshArtworks = $freshGallery->artworks()->get();
        $this->validateArtworkIds($artworkIds, $freshArtworks);

        DB::transaction(function () use ($freshGallery, $artworkIds, $actor): void {
            /** @var ArtworkCategory $lockedGallery */
            $lockedGallery = ArtworkCategory::query()->whereKey($freshGallery->getKey())->lockForUpdate()->firstOrFail();
            /** @var Collection<int, Artwork> $lockedArtworks */
            $lockedArtworks = $lockedGallery->artworks()->lockForUpdate()->get();
            $this->validateArtworkIds($artworkIds, $lockedArtworks);

            $artworksById = $lockedArtworks->keyBy(fn (Artwork $artwork): int => (int) $artwork->getKey());
            $changed = false;
            foreach ($artworkIds as $position => $artworkId) {
                $artwork = $artworksById->get($artworkId);
                if ((int) $artwork->getAttribute('position') !== $position) {
                    $artwork->setAttribute('position', $position);
                    $artwork->save();
                    $changed = true;
                }
            }

            if ($changed) {
                $this->adminAuditService->record($actor, 'artwork_category.gallery_reordered', 'artwork_category', $lockedGallery->getKey());
            }
        });

        return $freshGallery->fresh();
    }

    /** @param Collection<int, Artwork> $artworks */
    private function validateArtworkIds(array $artworkIds, Collection $artworks): void
    {
        if (! array_is_list($artworkIds) || ! collect($artworkIds)->every(static fn (mixed $id): bool => is_int($id) && $id > 0)) {
            throw ValidationException::withMessages(['artworks' => 'The artwork order is invalid.']);
        }

        if (count($artworkIds) !== count(array_unique($artworkIds))) {
            throw ValidationException::withMessages(['artworks' => 'The artwork order is invalid.']);
        }

        $expectedIds = $artworks->modelKeys();
        sort($expectedIds);
        $actualIds = $artworkIds;
        sort($actualIds);
        if ($expectedIds !== $actualIds) {
            throw ValidationException::withMessages(['artworks' => 'The artwork order is invalid.']);
        }
    }

    /** @return array{name:string,slug?:string,description:?string,show_on_home?:bool} */
    private function validateData(array $data, bool $update): array
    {
        $name = trim((string) ($data['name'] ?? ''));
        if ($name === '' || mb_strlen($name) > 160) {
            throw ValidationException::withMessages(['name' => 'The Gallery name is invalid.']);
        }

        $description = $data['description'] ?? null;
        if ($description !== null && (! is_string($description) || mb_strlen($description) > 10000)) {
            throw ValidationException::withMessages(['description' => 'The Gallery description is invalid.']);
        }

        $validated = [
            'name' => $name,
            'description' => $description,
        ];

        if (array_key_exists('show_on_home', $data)) {
            if (! is_bool($data['show_on_home']) && ! in_array($data['show_on_home'], [0, 1, '0', '1'], true)) {
                throw ValidationException::withMessages(['show_on_home' => 'The homepage eligibility setting is invalid.']);
            }
            $validated['show_on_home'] = (bool) $data['show_on_home'];
        } elseif (! $update) {
            $validated['show_on_home'] = false;
        }

        if (! $update) {
            $validated['slug'] = $this->validateSlug($data['slug'] ?? null);
        }

        return $validated;
    }

    private function validateSlug(mixed $slug, ?int $ignoreId = null): string
    {
        if (! is_string($slug)) {
            throw ValidationException::withMessages(['slug' => 'The Gallery slug is invalid.']);
        }

        $slug = trim($slug);
        if ($slug === '' || mb_strlen($slug) > 80 || ! preg_match('/^[a-z0-9]+(?:-[a-z0-9]+)*$/', $slug)) {
            throw ValidationException::withMessages(['slug' => 'The Gallery slug is invalid.']);
        }
        if ($this->pathPolicy->isReserved($slug)) {
            throw ValidationException::withMessages(['slug' => 'The Gallery slug is reserved.']);
        }

        $query = ArtworkCategory::query()->where('slug', $slug);
        if ($ignoreId !== null) {
            $query->where('id', '<>', $ignoreId);
        }
        if ($query->exists()) {
            throw ValidationException::withMessages(['slug' => 'The Gallery slug is already in use.']);
        }

        if (Redirect::query()->where('source_path', '/'.$slug)->where('enabled', true)->exists()) {
            throw ValidationException::withMessages(['slug' => 'The Gallery slug conflicts with an active redirect.']);
        }

        return $slug;
    }

    private function validateParentSectionId(mixed $parentSectionId): ?int
    {
        if ($parentSectionId === null || $parentSectionId === '') {
            return null;
        }
        if (filter_var($parentSectionId, FILTER_VALIDATE_INT) === false || (int) $parentSectionId <= 0) {
            throw ValidationException::withMessages(['parent_section_id' => 'The parent Gallery is invalid.']);
        }

        /** @var SiteSection|null $parent */
        $parent = SiteSection::query()->find((int) $parentSectionId);
        if (
            $parent === null
            || $parent->nodeType() !== SiteNodeType::Gallery
            || $parent->getAttribute('parent_id') !== null
        ) {
            throw ValidationException::withMessages(['parent_section_id' => 'The parent must be a top-level Gallery.']);
        }

        return (int) $parent->getKey();
    }

    private function nextSectionPosition(?int $parentSectionId): int
    {
        /** @var Builder<SiteSection> $query */
        $query = SiteSection::query();
        if ($parentSectionId === null) {
            $query->whereNull('parent_id')->where('type', '<>', SiteNodeType::Home->value);
        } else {
            $query->where('parent_id', $parentSectionId);
        }

        return ((int) ($query->max('position') ?? 0)) + 10;
    }
}
