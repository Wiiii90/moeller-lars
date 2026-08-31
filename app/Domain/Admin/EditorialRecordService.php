<?php

namespace App\Domain\Admin;

use App\Domain\Content\ExhibitionEditorialService;
use App\Models\CvEntry;
use App\Models\Exhibition;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use InvalidArgumentException;

final class EditorialRecordService
{
    public function __construct(
        private readonly AdminAuditService $audit,
        private readonly ExhibitionEditorialService $exhibitions,
    ) {}

    public function publish(CvEntry|Exhibition $record): CvEntry|Exhibition
    {
        if ($record instanceof Exhibition) {
            return $this->exhibitions->publish($record);
        }

        $actor = $this->audit->requireActor();

        return DB::transaction(function () use ($record, $actor): CvEntry {
            $fresh = $this->lockedCv($record);
            $state = (string) $fresh->getAttribute('state');

            if ($state === 'published') {
                return $fresh;
            }
            if ($state !== 'draft') {
                throw ValidationException::withMessages([
                    'state' => 'Restore this record to draft before publishing it again.',
                ]);
            }

            $fresh->setAttribute('state', 'published');
            $fresh->save();
            $this->audit->record($actor, 'cv_entry.published', 'cv_entry', $fresh->getKey());

            return $fresh->fresh();
        });
    }

    public function unpublish(CvEntry|Exhibition $record): CvEntry|Exhibition
    {
        if ($record instanceof Exhibition) {
            return $this->exhibitions->unpublish($record);
        }

        return $this->transitionCv($record, 'draft', 'unpublished', onlyFrom: 'published');
    }

    public function archive(CvEntry|Exhibition $record): CvEntry|Exhibition
    {
        if ($record instanceof Exhibition) {
            return $this->exhibitions->archive($record);
        }

        return $this->transitionCv($record, 'archived', 'archived');
    }

    public function restoreDraft(CvEntry|Exhibition $record): CvEntry|Exhibition
    {
        if ($record instanceof Exhibition) {
            return $this->exhibitions->restoreDraft($record);
        }

        return $this->transitionCv($record, 'draft', 'restored_to_draft', onlyFrom: ['archived', 'hidden']);
    }

    public function deleteCv(CvEntry $record): void
    {
        $actor = $this->audit->requireActor();

        DB::transaction(function () use ($record, $actor): void {
            $fresh = $this->lockedCv($record);
            $recordId = (int) $fresh->getKey();
            $mediaAssetId = $fresh->getAttribute('image_media_asset_id');
            $fresh->delete();

            $metadata = is_numeric($mediaAssetId)
                ? ['media_asset_id' => (int) $mediaAssetId]
                : null;
            $this->audit->record($actor, 'cv_entry.deleted', 'cv_entry', $recordId, $metadata);
        });
    }

    public function deleteExhibition(Exhibition $record): void
    {
        $this->exhibitions->delete($record);
    }

    public function canMove(CvEntry|Exhibition $record, string $direction): bool
    {
        $this->validateDirection($direction);
        if ($record instanceof Exhibition) {
            return $this->exhibitions->canMove($record, $direction);
        }

        $ids = CvEntry::query()
            ->orderBy('position')
            ->orderBy('id')
            ->pluck('id')
            ->map(static fn (mixed $id): int => (int) $id)
            ->all();
        $index = array_search((int) $record->getKey(), $ids, true);

        if ($index === false) {
            return false;
        }

        return $direction === 'up' ? $index > 0 : $index < count($ids) - 1;
    }

    public function move(CvEntry|Exhibition $record, string $direction): bool
    {
        $this->validateDirection($direction);
        if ($record instanceof Exhibition) {
            return $this->exhibitions->move($record, $direction);
        }

        $actor = $this->audit->requireActor();

        return DB::transaction(function () use ($record, $direction, $actor): bool {
            /** @var Collection<int, CvEntry> $records */
            $records = CvEntry::query()
                ->orderBy('position')
                ->orderBy('id')
                ->lockForUpdate()
                ->get();
            $ordered = $records->values()->all();
            $index = $this->cvIndex($ordered, (int) $record->getKey());
            if ($index === null) {
                return false;
            }

            $targetIndex = $direction === 'up' ? $index - 1 : $index + 1;
            if (! array_key_exists($targetIndex, $ordered)) {
                return false;
            }

            [$ordered[$index], $ordered[$targetIndex]] = [$ordered[$targetIndex], $ordered[$index]];

            return $this->persistCvOrder($records, $ordered, $actor);
        });
    }

    public function sortCv(CvEntry $record, int $position): bool
    {
        $actor = $this->audit->requireActor();

        return DB::transaction(function () use ($record, $position, $actor): bool {
            /** @var Collection<int, CvEntry> $records */
            $records = CvEntry::query()
                ->orderBy('position')
                ->orderBy('id')
                ->lockForUpdate()
                ->get();
            $ordered = $records->values()->all();
            $index = $this->cvIndex($ordered, (int) $record->getKey());
            if ($index === null) {
                return false;
            }

            $moved = $ordered[$index];
            array_splice($ordered, $index, 1);
            $position = max(0, min($position, count($ordered)));
            array_splice($ordered, $position, 0, [$moved]);

            return $this->persistCvOrder($records, $ordered, $actor);
        });
    }

    private function transitionCv(
        CvEntry $record,
        string $state,
        string $action,
        string|array|null $onlyFrom = null,
    ): CvEntry {
        $actor = $this->audit->requireActor();

        return DB::transaction(function () use ($record, $state, $action, $onlyFrom, $actor): CvEntry {
            $fresh = $this->lockedCv($record);
            $current = (string) $fresh->getAttribute('state');
            $allowed = $onlyFrom === null ? null : (array) $onlyFrom;

            if ($current === $state || ($allowed !== null && ! in_array($current, $allowed, true))) {
                return $fresh;
            }

            $fresh->setAttribute('state', $state);
            $fresh->save();
            $this->audit->record($actor, 'cv_entry.'.$action, 'cv_entry', $fresh->getKey());

            return $fresh->fresh();
        });
    }

    private function lockedCv(CvEntry $record): CvEntry
    {
        /** @var CvEntry $fresh */
        $fresh = CvEntry::query()
            ->whereKey($record->getKey())
            ->lockForUpdate()
            ->firstOrFail();

        return $fresh;
    }

    /** @param list<CvEntry> $ordered */
    private function cvIndex(array $ordered, int $recordId): ?int
    {
        foreach ($ordered as $index => $candidate) {
            if ((int) $candidate->getKey() === $recordId) {
                return $index;
            }
        }

        return null;
    }

    /** @param list<CvEntry> $ordered */
    private function persistCvOrder(Collection $records, array $ordered, mixed $actor): bool
    {
        $changes = [];
        foreach ($ordered as $position => $candidate) {
            if ((int) $candidate->getAttribute('position') !== $position) {
                $changes[] = [$candidate, $position];
            }
        }
        if ($changes === []) {
            return false;
        }

        $maxPosition = (int) ($records->max('position') ?? 0);
        $temporaryBase = $maxPosition + count($records) + 1;
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
            $this->audit->record(
                $actor,
                'cv_entry.reordered',
                'cv_entry',
                $candidate->getKey(),
                ['position' => $position],
            );
        }

        return true;
    }

    private function validateDirection(string $direction): void
    {
        if (! in_array($direction, ['up', 'down'], true)) {
            throw new InvalidArgumentException('Editorial order direction must be up or down.');
        }
    }
}
