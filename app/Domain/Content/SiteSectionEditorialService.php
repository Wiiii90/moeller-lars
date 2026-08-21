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

    public function createNavigationGroup(string $title): SiteSection
    {
        $title = trim($title);
        if ($title === '' || mb_strlen($title) > 160) {
            throw ValidationException::withMessages(['title' => 'A short navigation group title is required.']);
        }

        $actor = $this->audit->requireActor();

        return DB::transaction(function () use ($title, $actor): SiteSection {
            $section = SiteSection::query()->create([
                'type' => SiteSection::TYPE_NAVIGATION_GROUP,
                'title' => $title,
                'navigation_label' => $title,
                'slug' => null,
                'state' => 'hidden',
                'position' => $this->nextSiblingPosition(null, null),
                'show_in_navigation' => true,
                'parent_id' => null,
                'artwork_category_id' => null,
            ]);

            $this->audit->record($actor, 'site_section.created', 'site_section', (int) $section->getKey());

            return $section;
        });
    }

    public function updateGallery(
        SiteSection $section,
        string $state,
        bool $showInNavigation,
        ?int $parentSectionId,
    ): SiteSection {
        if ((string) $section->getAttribute('type') !== SiteSection::TYPE_GALLERY) {
            throw ValidationException::withMessages(['type' => 'Only Gallery sections support Gallery placement controls.']);
        }

        return $this->updatePlacement($section, $state, $showInNavigation, $parentSectionId);
    }

    public function updatePlacement(
        SiteSection $section,
        string $state,
        bool $showInNavigation,
        ?int $parentSectionId,
    ): SiteSection {
        if (! in_array($state, ['published', 'hidden'], true)) {
            throw ValidationException::withMessages(['state' => 'The section publication state is invalid.']);
        }
        if ((string) $section->getAttribute('type') === SiteSection::TYPE_HOME && $parentSectionId !== null) {
            throw ValidationException::withMessages(['parent_id' => 'Home cannot be nested below another section.']);
        }

        $actor = $this->audit->requireActor();

        return DB::transaction(function () use ($section, $state, $showInNavigation, $parentSectionId, $actor): SiteSection {
            /** @var SiteSection $fresh */
            $fresh = SiteSection::query()->whereKey($section->getKey())->lockForUpdate()->firstOrFail();
            $parent = $this->parentSection($fresh, $parentSectionId);
            $parentId = $parent?->getKey();

            if ($parentId !== null && SiteSection::query()->where('parent_id', $fresh->getKey())->exists()) {
                throw ValidationException::withMessages([
                    'parent_id' => 'A section that already contains submenu entries cannot itself become a submenu item.',
                ]);
            }

            if ($state === 'published' && $showInNavigation && $parent !== null) {
                if (
                    (string) $parent->getAttribute('state') !== 'published'
                    || ! (bool) $parent->getAttribute('show_in_navigation')
                ) {
                    throw ValidationException::withMessages([
                        'parent_id' => 'A section shown in a submenu requires a published parent that is also in navigation.',
                    ]);
                }
            }

            if ($state !== 'published' || ! $showInNavigation) {
                $visibleChildren = SiteSection::query()
                    ->where('parent_id', $fresh->getKey())
                    ->where('state', 'published')
                    ->where('show_in_navigation', true)
                    ->exists();
                if ($visibleChildren) {
                    throw ValidationException::withMessages([
                        'show_in_navigation' => 'Hide or unpublish visible submenu entries before hiding their parent section.',
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

            $this->audit->record($actor, 'site_section.updated', 'site_section', (int) $fresh->getKey());

            return $fresh;
        });
    }

    private function parentSection(SiteSection $section, ?int $parentSectionId): ?SiteSection
    {
        if ($parentSectionId === null) {
            return null;
        }
        if ((string) $section->getAttribute('type') === SiteSection::TYPE_NAVIGATION_GROUP) {
            throw ValidationException::withMessages(['parent_id' => 'Navigation groups must remain top-level submenu parents.']);
        }
        if ($parentSectionId === (int) $section->getKey()) {
            throw ValidationException::withMessages(['parent_id' => 'A section cannot be its own parent.']);
        }

        /** @var SiteSection|null $parent */
        $parent = SiteSection::query()->whereKey($parentSectionId)->lockForUpdate()->first();
        if ($parent === null || ! $parent->canContainChildren() || $parent->getAttribute('parent_id') !== null) {
            throw ValidationException::withMessages(['parent_id' => 'The parent must be a top-level section that supports submenu entries.']);
        }

        $childType = (string) $section->getAttribute('type');
        $parentType = (string) $parent->getAttribute('type');
        if ($parentType === SiteSection::TYPE_GALLERY && $childType !== SiteSection::TYPE_GALLERY) {
            throw ValidationException::withMessages(['parent_id' => 'Gallery parents may only contain Gallery sections.']);
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

    private function nextSiblingPosition(?SiteSection $section, ?int $parentId): int
    {
        /** @var Builder<SiteSection> $query */
        $query = SiteSection::query();
        if ($section !== null) {
            $query->whereKeyNot($section->getKey());
        }
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
