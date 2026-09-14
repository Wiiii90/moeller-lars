<?php

namespace App\Domain\Content;

use App\Domain\Admin\AdminAuditService;
use App\Models\SiteSection;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use InvalidArgumentException;

final class SiteSectionOrderService
{
    public function __construct(private readonly AdminAuditService $audit) {}

    public function canMove(SiteSection $section, string $direction): bool
    {
        $this->validateDirection($direction);

        if ($section->nodeType() === SiteNodeType::Home) {
            return false;
        }

        $ids = $this->orderedSiblingIds($section->getAttribute('parent_id'));
        $index = array_search((int) $section->getKey(), $ids, true);
        if ($index === false) {
            return false;
        }

        if ($direction === 'up') {
            if ($index <= 0) {
                return false;
            }

            $targetId = $ids[$index - 1] ?? null;
            if ($section->getAttribute('parent_id') === null && $targetId !== null && $this->isHomeId($targetId)) {
                return false;
            }

            return true;
        }

        return $index < count($ids) - 1;
    }

    public function move(SiteSection $section, string $direction): bool
    {
        $this->validateDirection($direction);

        if (! $this->canMove($section, $direction)) {
            return false;
        }

        $ids = $this->orderedSiblingIds($section->getAttribute('parent_id'));
        $index = array_search((int) $section->getKey(), $ids, true);
        if ($index === false) {
            return false;
        }

        $targetIndex = $direction === 'up' ? $index - 1 : $index + 1;

        return $this->moveTo(
            $section,
            $section->getAttribute('parent_id') === null ? null : (int) $section->getAttribute('parent_id'),
            $targetIndex,
        );
    }

    /**
     * Move a page to a zero-based position in a sibling group. The target parent is
     * deliberately type-agnostic: only the two-level hierarchy contract matters.
     * Home is the sole placement exception: it is always the first root node.
     */
    public function moveTo(SiteSection $section, ?int $parentSectionId, int $position): bool
    {
        if ($position < 0) {
            throw new InvalidArgumentException('Site-section position must be zero or greater.');
        }

        $actor = $this->audit->requireActor();

        return DB::transaction(function () use ($section, $parentSectionId, $position, $actor): bool {
            /** @var SiteSection $fresh */
            $fresh = SiteSection::query()->whereKey($section->getKey())->lockForUpdate()->firstOrFail();

            if ($fresh->nodeType() === SiteNodeType::Home) {
                if ($parentSectionId !== null || $position !== 0) {
                    throw ValidationException::withMessages([
                        'position' => 'Home is always the first top-level page.',
                    ]);
                }

                return false;
            }

            $targetParent = $this->targetParent($fresh, $parentSectionId);
            $targetParentId = $targetParent?->getKey();
            $targetParentId = $targetParentId === null ? null : (int) $targetParentId;
            $sourceParentId = $fresh->getAttribute('parent_id');
            $sourceParentId = $sourceParentId === null ? null : (int) $sourceParentId;

            if ($targetParentId !== null && SiteSection::query()->where('parent_id', $fresh->getKey())->exists()) {
                throw ValidationException::withMessages([
                    'parent_id' => 'A page that already has child pages cannot itself become a child page.',
                ]);
            }

            /** @var Collection<int, SiteSection> $sourceSiblings */
            $sourceSiblings = $this->siblings($sourceParentId)
                ->orderBy('position')
                ->orderBy('id')
                ->lockForUpdate()
                ->get();

            if ($sourceParentId === $targetParentId) {
                $targetSiblings = $sourceSiblings;
            } else {
                /** @var Collection<int, SiteSection> $targetSiblings */
                $targetSiblings = $this->siblings($targetParentId)
                    ->orderBy('position')
                    ->orderBy('id')
                    ->lockForUpdate()
                    ->get();
            }

            $sourceIds = $sourceSiblings
                ->pluck('id')
                ->map(static fn ($id): int => (int) $id)
                ->reject(static fn (int $id): bool => $id === (int) $fresh->getKey())
                ->values()
                ->all();

            if ($sourceParentId === $targetParentId) {
                $targetIds = $sourceIds;
            } else {
                $targetIds = $targetSiblings
                    ->pluck('id')
                    ->map(static fn ($id): int => (int) $id)
                    ->values()
                    ->all();
            }

            $targetIndex = min($position, count($targetIds));
            if ($targetParentId === null && $targetIds !== [] && $this->isHomeId($targetIds[0])) {
                $targetIndex = max(1, $targetIndex);
            }
            array_splice($targetIds, $targetIndex, 0, [(int) $fresh->getKey()]);

            if ($sourceParentId === $targetParentId) {
                $currentIds = $sourceSiblings
                    ->pluck('id')
                    ->map(static fn ($id): int => (int) $id)
                    ->values()
                    ->all();
                if ($currentIds === $targetIds) {
                    return false;
                }

                $this->rewriteGroups([[$targetParentId, $targetIds]]);
            } else {
                $this->rewriteGroups([
                    [$sourceParentId, $sourceIds],
                    [$targetParentId, $targetIds],
                ]);
            }

            $metadata = ['position' => $targetIndex + 1];
            if ($targetParentId !== null) {
                $metadata['site_section_id'] = $targetParentId;
            }

            $this->audit->record(
                $actor,
                'site_section.reordered',
                'site_section',
                (int) $fresh->getKey(),
                $metadata,
            );

            return true;
        });
    }

    /** @return list<int> */
    private function orderedSiblingIds(mixed $parentId): array
    {
        $normalizedParentId = $parentId === null ? null : (int) $parentId;

        return $this->siblings($normalizedParentId)
            ->orderBy('position')
            ->orderBy('id')
            ->pluck('id')
            ->map(static fn ($id): int => (int) $id)
            ->all();
    }

    /** @return Builder<SiteSection> */
    private function siblings(?int $parentId): Builder
    {
        /** @var Builder<SiteSection> $query */
        $query = SiteSection::query();

        return $parentId === null
            ? $query->whereNull('parent_id')
            : $query->where('parent_id', $parentId);
    }

    private function targetParent(SiteSection $section, ?int $parentSectionId): ?SiteSection
    {
        if ($parentSectionId === null) {
            return null;
        }
        if ($parentSectionId === (int) $section->getKey()) {
            throw ValidationException::withMessages(['parent_id' => 'A page cannot be its own parent.']);
        }

        /** @var SiteSection|null $parent */
        $parent = SiteSection::query()->whereKey($parentSectionId)->lockForUpdate()->first();
        if (! $parent instanceof SiteSection || $parent->getAttribute('parent_id') !== null) {
            throw ValidationException::withMessages(['parent_id' => 'The parent must be a top-level page.']);
        }

        return $parent;
    }

    /**
     * @param list<array{0:?int,1:list<int>}> $groups
     */
    private function rewriteGroups(array $groups): void
    {
        $temporaryBase = ((int) (SiteSection::query()->max('position') ?? 0)) + 1000;
        $temporaryOffset = 0;

        foreach ($groups as [$parentId, $ids]) {
            foreach ($ids as $index => $id) {
                DB::table('site_sections')->where('id', $id)->update([
                    'parent_id' => $parentId,
                    'position' => $temporaryBase + $temporaryOffset + $index + 1,
                ]);
            }
            $temporaryOffset += count($ids) + 10;
        }

        foreach ($groups as [$parentId, $ids]) {
            foreach ($ids as $index => $id) {
                DB::table('site_sections')->where('id', $id)->update([
                    'parent_id' => $parentId,
                    'position' => ($index + 1) * 10,
                    'updated_at' => now(),
                ]);
            }
        }
    }

    private function isHomeId(int $id): bool
    {
        return SiteSection::query()
            ->whereKey($id)
            ->where('type', SiteNodeType::Home->value)
            ->exists();
    }

    private function validateDirection(string $direction): void
    {
        if (! in_array($direction, ['up', 'down'], true)) {
            throw new InvalidArgumentException('Site-section order direction must be up or down.');
        }
    }
}
