<?php

namespace App\Domain\Artwork;

use App\Domain\Admin\AdminAuditService;
use App\Models\ArtworkCategory;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

final class ArtworkCategoryOrderService
{
    public function __construct(private readonly AdminAuditService $adminAuditService) {}

    public function canMove(ArtworkCategory $category, string $direction): bool
    {
        $this->validateDirection($direction);

        $ids = ArtworkCategory::query()
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
            /** @var Collection<int, ArtworkCategory> $categories */
            $categories = ArtworkCategory::query()
                ->orderBy('position')
                ->orderBy('id')
                ->lockForUpdate()
                ->get();

            $ordered = $categories->values()->all();
            $index = null;
            foreach ($ordered as $candidateIndex => $candidate) {
                if ((int) $candidate->getKey() === (int) $category->getKey()) {
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

            foreach ($ordered as $position => $candidate) {
                if ((int) $candidate->getAttribute('position') === $position) {
                    continue;
                }

                $candidate->setAttribute('position', $position);
                $candidate->save();
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

    private function validateDirection(string $direction): void
    {
        if (! in_array($direction, ['up', 'down'], true)) {
            throw new InvalidArgumentException('Category order direction must be up or down.');
        }
    }
}
