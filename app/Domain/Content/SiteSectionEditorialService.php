<?php

namespace App\Domain\Content;

use App\Domain\Admin\AdminAuditService;
use App\Models\SiteSection;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final class SiteSectionEditorialService
{
    public function __construct(private readonly AdminAuditService $audit) {}

    public function updateGallery(
        SiteSection $section,
        string $state,
        bool $showInNavigation,
        ?int $parentSectionId,
    ): SiteSection {
        if (! in_array($state, ['published', 'hidden'], true)) {
            throw ValidationException::withMessages(['state' => 'The Gallery publication state is invalid.']);
        }

        $actor = $this->audit->requireActor();

        return DB::transaction(function () use ($section, $state, $showInNavigation, $parentSectionId, $actor): SiteSection {
            /** @var SiteSection $fresh */
            $fresh = SiteSection::query()->whereKey($section->getKey())->lockForUpdate()->firstOrFail();
            if ((string) $fresh->getAttribute('type') !== SiteSection::TYPE_GALLERY) {
                throw ValidationException::withMessages(['type' => 'Only Gallery sections support Gallery placement controls.']);
            }

            $categoryId = (int) $fresh->getAttribute('artwork_category_id');
            DB::table('artwork_categories')->where('id', $categoryId)->lockForUpdate()->first();

            $parent = $this->parentSection($fresh, $parentSectionId);
            $parentId = $parent?->getKey();
            $parentCategoryId = $parent?->getAttribute('artwork_category_id');

            if ($parentId !== null && SiteSection::query()->where('parent_id', $fresh->getKey())->exists()) {
                throw ValidationException::withMessages([
                    'parent_id' => 'A Gallery that already contains submenu Galleries cannot itself become a submenu item.',
                ]);
            }

            if ($state === 'published' && $showInNavigation && $parent !== null) {
                if (
                    (string) $parent->getAttribute('state') !== 'published'
                    || ! (bool) $parent->getAttribute('show_in_navigation')
                ) {
                    throw ValidationException::withMessages([
                        'parent_id' => 'A Gallery shown in a submenu requires a published parent that is also in navigation.',
                    ]);
                }
            }

            if (($state !== 'published' || ! $showInNavigation) && $parentId === null) {
                $visibleChildren = SiteSection::query()
                    ->where('parent_id', $fresh->getKey())
                    ->where('state', 'published')
                    ->where('show_in_navigation', true)
                    ->exists();
                if ($visibleChildren) {
                    throw ValidationException::withMessages([
                        'show_in_navigation' => 'Hide submenu Galleries from navigation before hiding their parent Gallery.',
                    ]);
                }
            }

            $position = (int) $fresh->getAttribute('position');
            $parentChanged = $fresh->getAttribute('parent_id') !== $parentId;
            if ($parentChanged || $this->visiblePositionConflict($fresh, $state, $showInNavigation, $parentId, $position)) {
                $position = $this->nextSiblingPosition($fresh, $parentId);
            }

            $fresh->fill([
                'state' => $state,
                'show_in_navigation' => $showInNavigation,
                'parent_id' => $parentId,
                'position' => $position,
            ]);
            $fresh->save();

            DB::table('artwork_categories')->where('id', $categoryId)->update([
                'state' => $state,
                'show_in_navigation' => $showInNavigation,
                'parent_id' => $parentCategoryId,
                'position' => $position,
                'updated_at' => now(),
            ]);

            $this->audit->record($actor, 'site_section.updated', 'site_section', (int) $fresh->getKey());

            return $fresh;
        });
    }

    private function parentSection(SiteSection $section, ?int $parentSectionId): ?SiteSection
    {
        if ($parentSectionId === null) {
            return null;
        }

        if ($parentSectionId === (int) $section->getKey()) {
            throw ValidationException::withMessages(['parent_id' => 'A Gallery cannot be its own parent.']);
        }

        /** @var SiteSection|null $parent */
        $parent = SiteSection::query()->whereKey($parentSectionId)->lockForUpdate()->first();
        if (
            $parent === null
            || (string) $parent->getAttribute('type') !== SiteSection::TYPE_GALLERY
            || $parent->getAttribute('parent_id') !== null
        ) {
            throw ValidationException::withMessages(['parent_id' => 'The parent must be a top-level Gallery.']);
        }

        return $parent;
    }

    private function visiblePositionConflict(
        SiteSection $section,
        string $state,
        bool $showInNavigation,
        ?int $parentId,
        int $position,
    ): bool {
        if ($state !== 'published' || ! $showInNavigation) {
            return false;
        }

        /** @var Builder<SiteSection> $query */
        $query = SiteSection::query();
        $query->whereKeyNot($section->getKey())
            ->where('state', 'published')
            ->where('show_in_navigation', true)
            ->where('position', $position);
        $parentId === null ? $query->whereNull('parent_id') : $query->where('parent_id', $parentId);

        return $query->exists();
    }

    private function nextSiblingPosition(SiteSection $section, ?int $parentId): int
    {
        /** @var Builder<SiteSection> $query */
        $query = SiteSection::query();
        $query->whereKeyNot($section->getKey());
        $parentId === null ? $query->whereNull('parent_id') : $query->where('parent_id', $parentId);
        if ($parentId === null) {
            $query->where('type', '<>', SiteSection::TYPE_HOME);
        }

        /** @var Collection<int, SiteSection> $siblings */
        $siblings = $query->lockForUpdate()->get(['id', 'position']);
        $maximum = $siblings->max('position');

        return $maximum === null ? 10 : ((int) $maximum) + 10;
    }
}
