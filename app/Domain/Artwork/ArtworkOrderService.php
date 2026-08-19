<?php

namespace App\Domain\Artwork;

use App\Domain\Admin\AdminAuditService;
use App\Models\Artwork;
use App\Models\ArtworkCategory;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

final class ArtworkOrderService
{
    public function __construct(private readonly AdminAuditService $audit) {}

    public function canMove(Artwork $artwork, string $direction): bool
    {
        $this->validateDirection($direction);

        $ids = Artwork::query()
            ->where('artwork_category_id', $artwork->getAttribute('artwork_category_id'))
            ->orderBy('position')
            ->orderBy('id')
            ->pluck('id')
            ->map(static fn ($id): int => (int) $id)
            ->all();
        $index = array_search((int) $artwork->getKey(), $ids, true);

        if ($index === false) {
            return false;
        }

        return $direction === 'up' ? $index > 0 : $index < count($ids) - 1;
    }

    public function move(Artwork $artwork, string $direction): bool
    {
        $this->validateDirection($direction);
        $actor = $this->audit->requireActor();

        return DB::transaction(function () use ($artwork, $direction, $actor): bool {
            /** @var Artwork $fresh */
            $fresh = Artwork::query()->whereKey($artwork->getKey())->lockForUpdate()->firstOrFail();
            $categoryId = (int) $fresh->getAttribute('artwork_category_id');

            ArtworkCategory::query()->whereKey($categoryId)->lockForUpdate()->firstOrFail();

            /** @var Collection<int, Artwork> $siblings */
            $siblings = Artwork::query()
                ->where('artwork_category_id', $categoryId)
                ->orderBy('position')
                ->orderBy('id')
                ->lockForUpdate()
                ->get();

            $ordered = $siblings->values()->all();
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

            $slots = $siblings
                ->map(static fn (Artwork $candidate): int => (int) $candidate->getAttribute('position'))
                ->values()
                ->all();
            if (count($slots) !== count(array_unique($slots))) {
                $slots = range(0, count($ordered) - 1);
            }

            $changes = [];
            foreach ($ordered as $slotIndex => $candidate) {
                $position = $slots[$slotIndex];
                if ((int) $candidate->getAttribute('position') !== $position) {
                    $changes[] = [$candidate, $position];
                }
            }

            $currentMaximum = (int) $siblings->max('position');
            $slotMaximum = max($slots);
            $temporaryBase = max($currentMaximum, $slotMaximum) + count($siblings) + 100;

            foreach ($changes as $offset => [$candidate]) {
                DB::table('artworks')
                    ->where('id', $candidate->getKey())
                    ->update(['position' => $temporaryBase + $offset]);
            }

            foreach ($changes as [$candidate, $position]) {
                DB::table('artworks')
                    ->where('id', $candidate->getKey())
                    ->update([
                        'position' => $position,
                        'updated_at' => now(),
                    ]);
            }

            $this->audit->record(
                $actor,
                'artwork.reordered',
                'artwork',
                (int) $fresh->getKey(),
                ['direction' => $direction],
            );

            return true;
        });
    }

    private function validateDirection(string $direction): void
    {
        if (! in_array($direction, ['up', 'down'], true)) {
            throw new InvalidArgumentException('Artwork order direction must be up or down.');
        }
    }
}
