<?php

namespace App\Domain\Artwork;

use App\Domain\Admin\AdminAuditService;
use App\Domain\Content\GalleryTemplate;
use App\Domain\Content\GalleryHeroConfigurationService;
use App\Domain\Content\SiteSectionPathPolicy;
use App\Domain\Content\SiteSectionType;
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
                'type' => SiteSectionType::Gallery->value,
                'template' => GalleryTemplate::Standard->value,
                'title' => (string) $gallery->getAttribute('name'),
                'navigation_label' => (string) $gallery->getAttribute('name'),
                'slug' => (string) $gallery->getAttribute('slug'),
                'state' => 'hidden',
                'position' => $this->nextSectionPosition($parentSectionId),
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
            $oldName = (string) $fresh->getAttribute('name');
            $oldMode = GalleryPresentation::sortMode($fresh);
            $beforeOrder = ($validated['sort_mode'] ?? $oldMode) !== $oldMode
                ? GalleryPresentation::orderArtworks($fresh->artworks()->getQuery(), $fresh)
                    ->pluck('id')->map(static fn (mixed $id): int => (int) $id)->all()
                : null;
            $fresh->fill($validated);

            if (! $fresh->isDirty()) {
                return $fresh;
            }

            $nameChanged = $fresh->isDirty('name');
            $fresh->save();

            if ($beforeOrder !== null) {
                // Reify the selected visual ranking into the canonical positions.
                // Returning to Manual keeps the existing sorted result, not an
                // unrelated older hidden ranking.
                $newOrder = GalleryPresentation::sortMode($fresh) === GalleryPresentation::SORT_MANUAL
                    ? $beforeOrder
                    : GalleryPresentation::orderArtworks($fresh->artworks()->getQuery(), $fresh)
                        ->pluck('id')->map(static fn (mixed $id): int => (int) $id)->all();
                $this->writePositions($fresh, $newOrder, $beforeOrder, $actor);
            }

            if ($nameChanged) {
                /** @var SiteSection|null $section */
                $section = SiteSection::query()
                    ->where('type', SiteSectionType::Gallery->value)
                    ->where('artwork_category_id', $fresh->getKey())
                    ->lockForUpdate()
                    ->first();

                if ($section instanceof SiteSection) {
                    $section->setAttribute('title', (string) $fresh->getAttribute('name'));
                    $navigationLabel = $section->getAttribute('navigation_label');
                    if ($navigationLabel === null || $navigationLabel === $oldName) {
                        $section->setAttribute('navigation_label', (string) $fresh->getAttribute('name'));
                    }
                    if ($section->isDirty()) {
                        $section->save();
                    }
                }
            }

            $this->adminAuditService->record($actor, 'artwork_category.updated', 'artwork_category', $fresh->getKey());

            return $fresh;
        });
    }

    /**
     * Show exactly which visible Artwork ranks a sort-mode transition would
     * alter. No changes or shadow ordering are stored for this preview.
     *
     * @return array{from:string,to:string,changes:list<array{title:string,from:int,to:int}>,signature:string}
     */
    public function previewSortChange(ArtworkCategory $gallery, string $targetMode): array
    {
        if (! array_key_exists($targetMode, GalleryPresentation::SORT_OPTIONS)) {
            throw ValidationException::withMessages(['sort_mode' => 'Choose a supported Gallery order.']);
        }

        $currentMode = GalleryPresentation::sortMode($gallery);
        $artworks = $gallery->artworks();
        $before = GalleryPresentation::orderArtworks($artworks->getQuery(), $gallery)
            ->pluck('artworks.id')->map(static fn (mixed $id): int => (int) $id)->all();

        if ($currentMode === $targetMode || $targetMode === GalleryPresentation::SORT_MANUAL) {
            // Manual inherits the visible order; it never restores an obsolete
            // hidden ranking from positions written by an earlier mode.
            $after = $before;
        } else {
            $proposed = clone $gallery;
            $proposed->setAttribute('sort_mode', $targetMode);
            $after = GalleryPresentation::orderArtworks($proposed->artworks()->getQuery(), $proposed)
                ->pluck('artworks.id')->map(static fn (mixed $id): int => (int) $id)->all();
        }

        $beforeIds = $before;
        $afterIds = $after;
        sort($beforeIds);
        sort($afterIds);
        if ($beforeIds !== $afterIds) {
            // A concurrent add/move/delete changed the membership between
            // the two read queries. Never show a misleading position diff.
            throw ValidationException::withMessages([
                'sort_mode' => 'Gallery artworks changed while calculating the order. Reopen Settings and try again.',
            ]);
        }

        $priorPositions = array_flip($before);
        $titles = $gallery->artworks()->pluck('title', 'id');
        $changes = [];
        foreach ($after as $index => $id) {
            $previous = $priorPositions[$id] + 1;
            $next = $index + 1;
            if ($previous === $next) {
                continue;
            }

            $changes[] = [
                'title' => (string) ($titles[$id] ?? 'Artwork #'.$id),
                'from' => $previous,
                'to' => $next,
            ];
        }

        return [
            'from' => $currentMode,
            'to' => $targetMode,
            'changes' => $changes,
            'signature' => hash('sha256', json_encode(
                [$gallery->getKey(), $currentMode, $targetMode, $before, $after],
                JSON_THROW_ON_ERROR,
            )),
        ];
    }

    /**
     * The approval covers one exact order snapshot; a concurrent edit,
     * insertion or mode change invalidates the confirmation.
     */
    public function confirmSortChange(ArtworkCategory $gallery, string $targetMode, string $signature): bool
    {
        return DB::transaction(function () use ($gallery, $targetMode, $signature): bool {
            /** @var ArtworkCategory $fresh */
            $fresh = ArtworkCategory::query()->whereKey($gallery->getKey())->lockForUpdate()->firstOrFail();
            $preview = $this->previewSortChange($fresh, $targetMode);

            if (! hash_equals($preview['signature'], $signature)) {
                throw ValidationException::withMessages([
                    'sort_mode' => 'The Gallery order changed while the dialog was open. Review the new order before applying.',
                ]);
            }

            if ($preview['from'] === $targetMode) {
                return false;
            }

            return $this->update($fresh, [
                'name' => (string) $fresh->getAttribute('name'),
                'sort_mode' => $targetMode,
            ])->wasChanged();
        });
    }

    public function changeSlug(ArtworkCategory $gallery, string $slug): ArtworkCategory
    {
        $actor = $this->adminAuditService->requireActor();

        return DB::transaction(function () use ($gallery, $slug, $actor): ArtworkCategory {
            /** @var ArtworkCategory $fresh */
            $fresh = ArtworkCategory::query()->whereKey($gallery->getKey())->lockForUpdate()->firstOrFail();
            $oldSlug = (string) $fresh->getAttribute('slug');

            // A Standard at / is an artist-owned collection behind the
            // protected Home identity. Its public path is not the category slug.
            if (SiteSection::query()->where('artwork_category_id', $fresh->getKey())
                ->where('is_home', true)->exists()) {
                throw ValidationException::withMessages([
                    'slug' => 'The Home Standard has a fixed public path (/).',
                ]);
            }

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
                ->where('type', SiteSectionType::Gallery->value)
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
                ->where('type', SiteSectionType::Gallery->value)
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
            app(GalleryHeroConfigurationService::class)->disableGalleryAcrossHeroes((int) $fresh->getKey());
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
            $lockedArtworks = $lockedGallery->artworks()->orderBy('id')->lockForUpdate()->get();
            $this->validateArtworkIds($artworkIds, $lockedArtworks);
            $beforeOrder = GalleryPresentation::orderArtworks($lockedGallery->artworks()->getQuery(), $lockedGallery)
                ->pluck('id')->map(static fn (mixed $id): int => (int) $id)->all();

            if (GalleryPresentation::sortMode($lockedGallery) !== GalleryPresentation::SORT_MANUAL) {
                // An explicit drag, move or Position edit is the user's decision
                // to take ownership of the current sorted sequence.
                $lockedGallery->setAttribute('sort_mode', GalleryPresentation::SORT_MANUAL);
                $lockedGallery->save();
                $this->adminAuditService->record($actor, 'artwork_category.updated', 'artwork_category', $lockedGallery->getKey());
            }

            $this->writePositions($lockedGallery, $artworkIds, $beforeOrder, $actor, $lockedArtworks);
        });

        return $freshGallery->fresh();
    }

    /**
     * @param list<int> $orderedIds
     * @param list<int> $beforeOrder
     * @param Collection<int, Artwork>|null $lockedArtworks
     */
    private function writePositions(ArtworkCategory $gallery, array $orderedIds, array $beforeOrder, $actor, ?Collection $lockedArtworks = null): void
    {
        $lockedArtworks ??= $gallery->artworks()->orderBy('id')->lockForUpdate()->get();
        $this->validateArtworkIds($orderedIds, $lockedArtworks);
        $artworksById = $lockedArtworks->keyBy(fn (Artwork $artwork): int => (int) $artwork->getKey());

        foreach ($orderedIds as $position => $artworkId) {
            $artwork = $artworksById->get($artworkId);
            if ((int) $artwork->getAttribute('position') === $position) {
                continue;
            }
            $artwork->setAttribute('position', $position);
            $artwork->save();
        }

        if ($beforeOrder !== $orderedIds) {
            $this->adminAuditService->recordOrdering(
                $actor,
                'artwork_category.gallery_reordered',
                'artwork_category',
                (int) $gallery->getKey(),
                'gallery-artworks:'.(int) $gallery->getKey(),
                $beforeOrder,
                $orderedIds,
            );
        }
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

    /** @return array<string,mixed> */
    private function validateData(array $data, bool $update): array
    {
        // Create requires a name. Updates are partial mutations, including
        // individual Stage controls; omitting the name must never be treated
        // as an attempt to replace it with an empty string.
        $validated = GalleryPresentation::validateSettings($data);
        if (! $update || array_key_exists('name', $data)) {
            $name = trim((string) ($data['name'] ?? ''));
            if ($name === '' || mb_strlen($name) > 160) {
                throw ValidationException::withMessages(['name' => 'The Gallery name is invalid.']);
            }
            $validated['name'] = $name;
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
            || $parent->nodeType() !== SiteSectionType::Gallery
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
            $query->whereNull('parent_id')->where('is_home', false);
        } else {
            $query->where('parent_id', $parentSectionId);
        }

        return ((int) ($query->max('position') ?? 0)) + 10;
    }
}
