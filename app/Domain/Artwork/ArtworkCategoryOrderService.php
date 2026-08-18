<?php

namespace App\Domain\Artwork;

use App\Domain\Admin\AdminAuditService;
use App\Models\ArtworkCategory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use LogicException;

final class ArtworkCategoryOrderService
{
    public function __construct(private readonly AdminAuditService $adminAuditService) {}

    public function canMove(ArtworkCategory $category, string $direction): bool
    {
        $this->validateDirection($direction);

        $ids = $this->siblings($category)
            ->orderBy('position')
            ->orderBy('id')
            ->pluck('id')
            ->map(static fn ($id): int => (int) $id)
            ->all();
        $index = array_search((int) $category->getKey(), $ids, true);

        if ($index === false) {
            return false;
        }

        return $direction === 'up' ? $index > 0 : $index < count($ids) - 1;
    }

    public function move(ArtworkCategory $category, string $direction): bool
    {
        $this->validateDirection($direction);
        $actor = $this->adminAuditService->requireActor();

        return DB::transaction(function () use ($category, $direction, $actor): bool {
            /** @var ArtworkCategory $fresh */
            $fresh = ArtworkCategory::query()->whereKey($category->getKey())->lockForUpdate()->firstOrFail();
            /** @var Collection<int, ArtworkCategory> $categories */
            $categories = $this->siblings($fresh)
                ->orderBy('position')
                ->orderBy('id')
                ->lockForUpdate()
                ->get();

            $ordered = $categories->values()->all();
            $positionSlots = $categories
                ->map(static fn (ArtworkCategory $candidate): int => (int) $candidate->getAttribute('position'))
                ->values()
                ->all();

            if (count($positionSlots) !== count(array_unique($positionSlots))) {
                throw new LogicException('Sibling artwork category positions must be unique before reordering.');
            }

            $index = null;
            foreach ($ordered as $candidateIndex => $candidate) {
                if ((int) $candidate->getKey() === (int) $fresh->getKey()) {
                    $index = $candidateIndex;
                    break;
                }
            }

            if ($index === null) {
                return false;
            }

            $targetIndex = $direction === 'up' ? $index - 1 : $index + 1;
            if (! array_key_exists($targetIndex, $ordered)) {
                return false;
            }

            [$ordered[$index], $ordered[$targetIndex]] = [$ordered[$targetIndex], $ordered[$index]];

            $changes = [];
            foreach ($ordered as $slotIndex => $candidate) {
                $position = $positionSlots[$slotIndex];
                if ((int) $candidate->getAttribute('position') !== $position) {
                    $changes[] = [$candidate, $position];
                }
            }

            $maxPosition = max($positionSlots);
            $temporaryBase = $maxPosition + count($categories) + 1;

            foreach ($changes as $temporaryOffset => [$candidate]) {
                DB::table($candidate->getTable())
                    ->where('id', $candidate->getKey())
                    ->update(['position' => $temporaryBase + $temporaryOffset]);
            }

            foreach ($changes as [$candidate, $position]) {
                DB::table($candidate->getTable())
                    ->where('id', $candidate->getKey())
                    ->update([
                        'position' => $position,
                        'updated_at' => now(),
                    ]);
                $this->adminAuditService->record(
                    $actor,
                    'artwork_category.updated',
                    'artwork_category',
                    (int) $candidate->getKey(),
                );
            }

            return true;
        });
    }

    private function siblings(ArtworkCategory $category): Builder
    {
        $parentId = $category->getAttribute('parent_id');

        return ArtworkCategory::query()->when(
            $parentId === null,
            static fn (Builder $query): Builder => $query->whereNull('parent_id'),
            static fn (Builder $query): Builder => $query->where('parent_id', $parentId),
        );
    }

    private function validateDirection(string $direction): void
    {
        if (! in_array($direction, ['up', 'down'], true)) {
            throw new InvalidArgumentException('Category order direction must be up or down.');
        }
    }
}
