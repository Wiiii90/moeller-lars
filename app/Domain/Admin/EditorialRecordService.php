<?php

namespace App\Domain\Admin;

use App\Domain\Content\JournalEntryOrderService;
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
        private readonly JournalEntryOrderService $journalOrder,
    ) {}

    public function publish(CvEntry|Exhibition $record): CvEntry|Exhibition
    {
        $actor = $this->audit->requireActor();

        return DB::transaction(function () use ($record, $actor): CvEntry|Exhibition {
            $fresh = $this->locked($record);
            $state = (string) $fresh->getAttribute('state');

            if ($state === 'published') {
                return $fresh;
            }

            if ($state !== 'draft') {
                throw ValidationException::withMessages([
                    'state' => 'Restore this record to draft before publishing it again.',
                ]);
            }

            if ($fresh instanceof Exhibition) {
                $heroCount = $fresh->mediaUsages()->where('role', 'hero')->count();
                if ($heroCount > 1) {
                    throw ValidationException::withMessages([
                        'mediaUsages' => 'Published exhibitions may have at most one hero image.',
                    ]);
                }
            }

            $fresh->setAttribute('state', 'published');
            $fresh->save();
            $this->audit->record($actor, $this->prefix($fresh).'.published', $this->entityType($fresh), $fresh->getKey());

            return $fresh->fresh();
        });
    }

    public function unpublish(CvEntry|Exhibition $record): CvEntry|Exhibition
    {
        return $this->transition($record, 'draft', 'unpublished', onlyFrom: 'published');
    }

    public function archive(CvEntry|Exhibition $record): CvEntry|Exhibition
    {
        return $this->transition($record, 'archived', 'archived');
    }

    public function restoreDraft(CvEntry|Exhibition $record): CvEntry|Exhibition
    {
        return $this->transition($record, 'draft', 'restored_to_draft', onlyFrom: ['archived', 'hidden']);
    }

    public function deleteCv(CvEntry $record): void
    {
        $actor = $this->audit->requireActor();

        DB::transaction(function () use ($record, $actor): void {
            /** @var CvEntry $fresh */
            $fresh = CvEntry::query()->whereKey($record->getKey())->lockForUpdate()->firstOrFail();
            $recordId = (int) $fresh->getKey();
            $mediaAssetId = $fresh->getAttribute('image_media_asset_id');

            $fresh->delete();

            $this->audit->record($actor, 'cv_entry.deleted', 'cv_entry', $recordId, [
                'image_media_asset_id' => is_numeric($mediaAssetId) ? (int) $mediaAssetId : null,
            ]);
        });
    }

    public function deleteExhibition(Exhibition $record): void
    {
        $actor = $this->audit->requireActor();

        DB::transaction(function () use ($record, $actor): void {
            /** @var Exhibition $fresh */
            $fresh = Exhibition::query()->whereKey($record->getKey())->lockForUpdate()->firstOrFail();
            if ((string) $fresh->getAttribute('state') === 'published') {
                throw ValidationException::withMessages([
                    'exhibition' => 'Unpublish this Exhibition before deleting it.',
                ]);
            }

            $recordId = (int) $fresh->getKey();
            $sectionId = (int) $fresh->getAttribute('site_section_id');
            $fresh->delete();
            $this->audit->record($actor, 'exhibition.deleted', 'exhibition', $recordId, [
                'site_section_id' => $sectionId,
            ]);
        });
    }

    public function canMove(CvEntry|Exhibition $record, string $direction): bool
    {
        $this->validateDirection($direction);
        if ($record instanceof Exhibition) {
            return $this->journalOrder->canMove($record, $direction);
        }

        $class = $record::class;
        $ids = $class::query()
            ->orderBy('position')
            ->orderBy('id')
            ->pluck('id')
            ->map(static fn ($id): int => (int) $id)
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
            return $this->journalOrder->move($record, $direction);
        }

        $actor = $this->audit->requireActor();

        return DB::transaction(function () use ($record, $direction, $actor): bool {
            $class = $record::class;
            /** @var Collection<int, CvEntry> $records */
            $records = $class::query()
                ->orderBy('position')
                ->orderBy('id')
                ->lockForUpdate()
                ->get();
            $ordered = $records->values()->all();
            $index = null;

            foreach ($ordered as $candidateIndex => $candidate) {
                if ((int) $candidate->getKey() === (int) $record->getKey()) {
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
            $table = $record->getTable();

            foreach ($changes as $temporaryOffset => [$candidate]) {
                DB::table($table)
                    ->where('id', $candidate->getKey())
                    ->update(['position' => $temporaryBase + $temporaryOffset]);
            }

            foreach ($changes as [$candidate, $position]) {
                DB::table($table)
                    ->where('id', $candidate->getKey())
                    ->update([
                        'position' => $position,
                        'updated_at' => now(),
                    ]);
                $this->audit->record(
                    $actor,
                    $this->prefix($candidate).'.reordered',
                    $this->entityType($candidate),
                    $candidate->getKey(),
                    ['position' => $position],
                );
            }

            return true;
        });
    }

    private function transition(
        CvEntry|Exhibition $record,
        string $state,
        string $action,
        string|array|null $onlyFrom = null,
    ): CvEntry|Exhibition {
        $actor = $this->audit->requireActor();

        return DB::transaction(function () use ($record, $state, $action, $onlyFrom, $actor): CvEntry|Exhibition {
            $fresh = $this->locked($record);
            $current = (string) $fresh->getAttribute('state');
            $allowed = $onlyFrom === null ? null : (array) $onlyFrom;

            if ($current === $state || ($allowed !== null && ! in_array($current, $allowed, true))) {
                return $fresh;
            }

            $fresh->setAttribute('state', $state);
            $fresh->save();
            $this->audit->record($actor, $this->prefix($fresh).'.'.$action, $this->entityType($fresh), $fresh->getKey());

            return $fresh->fresh();
        });
    }

    private function locked(CvEntry|Exhibition $record): CvEntry|Exhibition
    {
        $class = $record::class;
        /** @var CvEntry|Exhibition $fresh */
        $fresh = $class::query()->whereKey($record->getKey())->lockForUpdate()->firstOrFail();

        return $fresh;
    }

    private function prefix(CvEntry|Exhibition $record): string
    {
        return $record instanceof CvEntry ? 'cv_entry' : 'exhibition';
    }

    private function entityType(CvEntry|Exhibition $record): string
    {
        return $this->prefix($record);
    }

    private function validateDirection(string $direction): void
    {
        if (! in_array($direction, ['up', 'down'], true)) {
            throw new InvalidArgumentException('Editorial order direction must be up or down.');
        }
    }
}
