<?php

namespace App\Domain\Artwork;

use App\Domain\Admin\AdminAuditService;
use App\Models\Artwork;
use App\Models\ArtworkCategory;
use App\Models\Redirect;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ArtworkCategoryEditorialService
{
    public function __construct(
        private readonly AdminAuditService $adminAuditService,
        private readonly ArtworkCategoryPathPolicy $pathPolicy,
    ) {}

    public function create(array $data): ArtworkCategory
    {
        $actor = $this->adminAuditService->requireActor();
        $validated = $this->validateData($data, false);
        $validated['parent_id'] = $this->validateParentId($data['parent_id'] ?? null);

        return DB::transaction(function () use ($validated, $actor): ArtworkCategory {
            $category = new ArtworkCategory;
            $category->fill([
                ...$validated,
                'state' => 'hidden',
                'legacy_id' => null,
                'legacy_source' => null,
                'migration_batch_id' => null,
                'migrated_at' => null,
            ]);
            $category->save();
            $this->adminAuditService->record($actor, 'artwork_category.created', 'artwork_category', $category->getKey());

            return $category;
        });
    }

    public function update(ArtworkCategory $category, array $data): ArtworkCategory
    {
        $actor = $this->adminAuditService->requireActor();
        $validated = $this->validateData($data, true);
        $requestedParentId = array_key_exists('parent_id', $data)
            ? $this->validateParentId($data['parent_id'], $category)
            : $category->getAttribute('parent_id');

        return DB::transaction(function () use ($category, $validated, $requestedParentId, $actor): ArtworkCategory {
            /** @var ArtworkCategory $fresh */
            $fresh = ArtworkCategory::query()->whereKey($category->getKey())->lockForUpdate()->firstOrFail();
            $currentParentId = $fresh->getAttribute('parent_id');

            $fresh->fill($validated);
            if ($requestedParentId !== $currentParentId) {
                $this->assertCanReparent($fresh, $requestedParentId);
                $fresh->setAttribute('parent_id', $requestedParentId);
                $fresh->setAttribute('position', $this->nextSiblingPosition($requestedParentId, (int) $fresh->getKey()));
            }

            if ($fresh->isDirty()) {
                $this->validateVisibleChildren($fresh);
                $this->validateNavigationPosition($fresh);
                $fresh->save();
                $this->adminAuditService->record($actor, 'artwork_category.updated', 'artwork_category', $fresh->getKey());
            }

            return $fresh;
        });
    }

    public function publish(ArtworkCategory $category): ArtworkCategory
    {
        $actor = $this->adminAuditService->requireActor();

        return DB::transaction(function () use ($category, $actor): ArtworkCategory {
            $category->refresh();
            if ((string) $category->getAttribute('state') === 'published') {
                return $category;
            }

            $category->setAttribute('state', 'published');
            $this->validateNavigationPosition($category);
            $category->save();
            $this->adminAuditService->record($actor, 'artwork_category.published', 'artwork_category', $category->getKey());

            return $category;
        });
    }

    public function hide(ArtworkCategory $category): ArtworkCategory
    {
        $actor = $this->adminAuditService->requireActor();

        return DB::transaction(function () use ($category, $actor): ArtworkCategory {
            $category->refresh();
            if ((string) $category->getAttribute('state') === 'hidden') {
                return $category;
            }

            if ($category->artworks()->where('state', 'published')->exists()) {
                throw ValidationException::withMessages(['state' => 'A category with published artwork cannot be hidden.']);
            }
            if ($category->children()->where('state', 'published')->exists()) {
                throw ValidationException::withMessages(['state' => 'A parent category with published child categories cannot be hidden.']);
            }

            $category->setAttribute('state', 'hidden');
            $category->save();
            $this->adminAuditService->record($actor, 'artwork_category.hidden', 'artwork_category', $category->getKey());

            return $category;
        });
    }

    public function changeSlug(ArtworkCategory $category, string $slug): ArtworkCategory
    {
        $actor = $this->adminAuditService->requireActor();

        return DB::transaction(function () use ($category, $slug, $actor): ArtworkCategory {
            $category->refresh();
            $oldSlug = (string) $category->getAttribute('slug');

            $newSlug = $this->validateSlug($slug, $category->getKey());
            if ($newSlug === $oldSlug) {
                return $category;
            }

            $oldPath = '/'.$oldSlug;
            $newPath = '/'.$newSlug;
            $ownedReason = ArtworkCategoryPathPolicy::CATEGORY_SLUG_REDIRECT_REASON;

            Redirect::query()->where('reason', $ownedReason)->where('target_path', $oldPath)->update(['target_path' => $newPath]);

            /** @var Redirect|null $sourceRedirect */
            $sourceRedirect = Redirect::query()->where('source_path', $oldPath)->lockForUpdate()->first();
            if ($sourceRedirect !== null && $sourceRedirect->getAttribute('reason') !== $ownedReason) {
                throw ValidationException::withMessages(['slug' => 'The category path is already reserved by another redirect.']);
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

            $category->setAttribute('slug', $newSlug);
            $category->save();
            $this->adminAuditService->record($actor, 'artwork_category.slug_changed', 'artwork_category', $category->getKey());

            return $category;
        });
    }

    public function delete(ArtworkCategory $category): void
    {
        $actor = $this->adminAuditService->requireActor();

        DB::transaction(function () use ($category, $actor): void {
            $category->refresh();
            if (
                (string) $category->getAttribute('state') !== 'hidden'
                || $category->artworks()->exists()
                || $category->children()->exists()
            ) {
                throw ValidationException::withMessages(['category' => 'This category cannot be deleted while it owns artwork or child categories.']);
            }

            $path = '/'.$category->getAttribute('slug');
            $this->adminAuditService->record($actor, 'artwork_category.deleted', 'artwork_category', $category->getKey());
            Redirect::query()
                ->where('reason', ArtworkCategoryPathPolicy::CATEGORY_SLUG_REDIRECT_REASON)
                ->where(fn (Builder $query) => $query->where('source_path', $path)->orWhere('target_path', $path))
                ->delete();
            $category->delete();
        });
    }

    public function reorderArtworks(ArtworkCategory $category, array $artworkIds): ArtworkCategory
    {
        $actor = $this->adminAuditService->requireActor();
        /** @var ArtworkCategory $freshCategory */
        $freshCategory = ArtworkCategory::query()->findOrFail($category->getKey());
        /** @var Collection<int, Artwork> $freshArtworks */
        $freshArtworks = $freshCategory->artworks()->get();
        $this->validateArtworkIds($artworkIds, $freshArtworks);

        DB::transaction(function () use ($freshCategory, $artworkIds, $actor): void {
            /** @var ArtworkCategory $lockedCategory */
            $lockedCategory = ArtworkCategory::query()->whereKey($freshCategory->getKey())->lockForUpdate()->firstOrFail();
            /** @var Collection<int, Artwork> $lockedArtworks */
            $lockedArtworks = $lockedCategory->artworks()->lockForUpdate()->get();
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
                $this->adminAuditService->record($actor, 'artwork_category.gallery_reordered', 'artwork_category', $lockedCategory->getKey());
            }
        });

        return $freshCategory->fresh();
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

    /** @return array{name:string,slug?:string,position:int,description:?string,show_in_navigation?:bool,show_on_home?:bool} */
    private function validateData(array $data, bool $update): array
    {
        $name = trim((string) ($data['name'] ?? ''));
        if ($name === '' || mb_strlen($name) > 160) {
            throw ValidationException::withMessages(['name' => 'The category name is invalid.']);
        }

        $position = $data['position'] ?? null;
        if (filter_var($position, FILTER_VALIDATE_INT) === false || (int) $position < 0) {
            throw ValidationException::withMessages(['position' => 'The category position is invalid.']);
        }

        $description = $data['description'] ?? null;
        if ($description !== null && (! is_string($description) || mb_strlen($description) > 10000)) {
            throw ValidationException::withMessages(['description' => 'The category description is invalid.']);
        }

        $validated = [
            'name' => $name,
            'position' => (int) $position,
            'description' => $description,
        ];

        foreach (['show_in_navigation', 'show_on_home'] as $field) {
            if (! array_key_exists($field, $data)) {
                if (! $update) {
                    $validated[$field] = false;
                }

                continue;
            }

            if (! is_bool($data[$field]) && ! in_array($data[$field], [0, 1, '0', '1'], true)) {
                throw ValidationException::withMessages([$field => 'The category presentation setting is invalid.']);
            }

            $validated[$field] = (bool) $data[$field];
        }

        if (! $update) {
            $validated['slug'] = $this->validateSlug($data['slug'] ?? null);
        }

        return $validated;
    }

    private function validateSlug(mixed $slug, ?int $ignoreId = null): string
    {
        if (! is_string($slug)) {
            throw ValidationException::withMessages(['slug' => 'The category slug is invalid.']);
        }

        $slug = trim($slug);
        if ($slug === '' || mb_strlen($slug) > 80 || ! preg_match('/^[a-z0-9]+(?:-[a-z0-9]+)*$/', $slug)) {
            throw ValidationException::withMessages(['slug' => 'The category slug is invalid.']);
        }
        if ($this->pathPolicy->isReserved($slug)) {
            throw ValidationException::withMessages(['slug' => 'The category slug is reserved.']);
        }

        $query = ArtworkCategory::query()->where('slug', $slug);
        if ($ignoreId !== null) {
            $query->where('id', '<>', $ignoreId);
        }
        if ($query->exists()) {
            throw ValidationException::withMessages(['slug' => 'The category slug is already in use.']);
        }

        if (Redirect::query()->where('source_path', '/'.$slug)->where('enabled', true)->exists()) {
            throw ValidationException::withMessages(['slug' => 'The category slug conflicts with an active redirect.']);
        }

        return $slug;
    }

    private function validateParentId(mixed $parentId, ?ArtworkCategory $category = null): ?int
    {
        if ($parentId === null || $parentId === '') {
            return null;
        }
        if (filter_var($parentId, FILTER_VALIDATE_INT) === false || (int) $parentId <= 0) {
            throw ValidationException::withMessages(['parent_id' => 'The parent category is invalid.']);
        }

        $parentId = (int) $parentId;
        if ($category !== null && $parentId === (int) $category->getKey()) {
            throw ValidationException::withMessages(['parent_id' => 'A category cannot be its own parent.']);
        }

        /** @var ArtworkCategory|null $parent */
        $parent = ArtworkCategory::query()->find($parentId);
        if ($parent === null) {
            throw ValidationException::withMessages(['parent_id' => 'The parent category no longer exists.']);
        }
        if ($parent->getAttribute('parent_id') !== null) {
            throw ValidationException::withMessages(['parent_id' => 'Only one level of child categories is supported. Choose a top-level category.']);
        }

        return $parentId;
    }

    private function assertCanReparent(ArtworkCategory $category, ?int $parentId): void
    {
        if ($parentId !== null && $category->children()->exists()) {
            throw ValidationException::withMessages(['parent_id' => 'A category that already has child categories must remain top-level.']);
        }
    }

    private function nextSiblingPosition(?int $parentId, int $ignoreId): int
    {
        $query = ArtworkCategory::query()->whereKeyNot($ignoreId);
        $parentId === null ? $query->whereNull('parent_id') : $query->where('parent_id', $parentId);

        return ((int) ($query->max('position') ?? -1)) + 1;
    }

    private function validateVisibleChildren(ArtworkCategory $category): void
    {
        if (
            (string) $category->getAttribute('state') !== 'published'
            || (bool) $category->getAttribute('show_in_navigation')
        ) {
            return;
        }

        if ($category->children()->where('state', 'published')->where('show_in_navigation', true)->exists()) {
            throw ValidationException::withMessages([
                'show_in_navigation' => 'Keep this parent in public navigation while it has visible child categories.',
            ]);
        }
    }

    private function validateNavigationPosition(ArtworkCategory $category): void
    {
        if (
            (string) $category->getAttribute('state') !== 'published'
            || ! (bool) $category->getAttribute('show_in_navigation')
        ) {
            return;
        }

        $parentId = $category->getAttribute('parent_id');
        if ($parentId !== null) {
            /** @var ArtworkCategory|null $parent */
            $parent = ArtworkCategory::query()->find($parentId);
            if (
                $parent === null
                || (string) $parent->getAttribute('state') !== 'published'
                || ! (bool) $parent->getAttribute('show_in_navigation')
            ) {
                throw ValidationException::withMessages([
                    'parent_id' => 'A visible child category requires a published parent that is also shown in public navigation.',
                ]);
            }
        }

        /** @var Builder<ArtworkCategory> $conflictQuery */
        $conflictQuery = ArtworkCategory::query();
        $conflictQuery->where('state', 'published');
        $conflictQuery->where('show_in_navigation', true);
        $conflictQuery->where('position', $category->getAttribute('position'));
        $conflictQuery->whereKeyNot($category->getKey());
        if ($parentId === null) {
            $conflictQuery->whereNull('parent_id');
        } else {
            $conflictQuery->where('parent_id', $parentId);
        }

        if ($conflictQuery->exists()) {
            throw ValidationException::withMessages([
                'position' => $parentId === null
                    ? 'A visible top-level navigation category already uses this position.'
                    : 'A visible child category under this parent already uses this position.',
            ]);
        }
    }
}
