<?php

namespace App\Domain\Content;

use App\Domain\Admin\AdminAuditService;
use App\Models\BlogPost;
use App\Models\Exhibition;
use App\Models\SiteSection;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

final class JournalEntryOrderService
{
    public function __construct(private readonly AdminAuditService $audit) {}

    public function nextPosition(Model $model, int $siteSectionId): int
    {
        SiteSection::query()->whereKey($siteSectionId)->lockForUpdate()->firstOrFail();
        $maximum = $model->newQuery()->where('site_section_id', $siteSectionId)->max('position');

        return $maximum === null ? 0 : ((int) $maximum) + 1;
    }

    public function canMove(Model $record, string $direction): bool
    {
        $this->assertSupported($record);
        $this->assertDirection($direction);
        $ids = $record->newQuery()
            ->where('site_section_id', $this->sectionId($record))
            ->orderBy('position')->orderBy('id')
            ->pluck('id')->map(static fn ($id): int => (int) $id)->all();
        $index = array_search((int) $record->getKey(), $ids, true);

        return $index !== false && ($direction === 'up' ? $index > 0 : $index < count($ids) - 1);
    }

    public function move(Model $record, string $direction): bool
    {
        $this->assertSupported($record);
        $this->assertDirection($direction);
        $sectionId = $this->sectionId($record);
        $actor = $this->audit->requireActor();

        return DB::transaction(function () use ($record, $direction, $sectionId, $actor): bool {
            $records = $this->lockedRecords($record, $sectionId);
            $ordered = $records->values()->all();
            $index = $this->indexOf($ordered, (int) $record->getKey());
            if ($index === null) {
                return false;
            }
            $targetIndex = $direction === 'up' ? $index - 1 : $index + 1;
            if (! array_key_exists($targetIndex, $ordered)) {
                return false;
            }
            [$ordered[$index], $ordered[$targetIndex]] = [$ordered[$targetIndex], $ordered[$index]];

            return $this->persistOrder($record, $records, $ordered, $sectionId, $actor);
        });
    }

    public function moveToPosition(Model $record, int $position): bool
    {
        $this->assertSupported($record);
        $sectionId = $this->sectionId($record);
        $actor = $this->audit->requireActor();

        return DB::transaction(function () use ($record, $position, $sectionId, $actor): bool {
            $records = $this->lockedRecords($record, $sectionId);
            $ordered = $records->values()->all();
            $from = $this->indexOf($ordered, (int) $record->getKey());
            if ($from === null || $ordered === []) {
                return false;
            }
            $to = min(max(0, $position), count($ordered) - 1);
            if ($from === $to) {
                return false;
            }

            $moving = $ordered[$from];
            array_splice($ordered, $from, 1);
            array_splice($ordered, $to, 0, [$moving]);

            return $this->persistOrder($record, $records, $ordered, $sectionId, $actor);
        });
    }

    /** @return Collection<int, Model> */
    private function lockedRecords(Model $record, int $sectionId): Collection
    {
        return $record->newQuery()
            ->where('site_section_id', $sectionId)
            ->orderBy('position')->orderBy('id')
            ->lockForUpdate()->get();
    }

    /** @param list<Model> $ordered */
    private function indexOf(array $ordered, int $id): ?int
    {
        foreach ($ordered as $index => $candidate) {
            if ((int) $candidate->getKey() === $id) {
                return $index;
            }
        }
        return null;
    }

    /** @param list<Model> $ordered */
    private function persistOrder(Model $record, Collection $records, array $ordered, int $sectionId, $actor): bool
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

        $maximum = (int) ($records->max('position') ?? 0);
        $temporaryBase = $maximum + count($records) + 1;
        foreach ($changes as $offset => [$candidate]) {
            DB::table($record->getTable())->where('id', $candidate->getKey())->update(['position' => $temporaryBase + $offset]);
        }
        foreach ($changes as [$candidate, $position]) {
            DB::table($record->getTable())->where('id', $candidate->getKey())->update(['position' => $position, 'updated_at' => now()]);
            $this->audit->record(
                $actor,
                $candidate instanceof BlogPost ? 'blog_post.reordered' : 'exhibition.reordered',
                $candidate instanceof BlogPost ? 'blog_post' : 'exhibition',
                $candidate->getKey(),
                ['position' => $position, 'site_section_id' => $sectionId],
            );
        }

        return true;
    }

    private function assertSupported(Model $record): void
    {
        if (! $record instanceof BlogPost && ! $record instanceof Exhibition) {
            throw new InvalidArgumentException('Journal ordering supports BlogPost and Exhibition records only.');
        }
    }

    private function assertDirection(string $direction): void
    {
        if (! in_array($direction, ['up', 'down'], true)) {
            throw new InvalidArgumentException('Journal ordering direction must be up or down.');
        }
    }

    private function sectionId(Model $record): int
    {
        $sectionId = $record->getAttribute('site_section_id');
        if (! is_numeric($sectionId) || (int) $sectionId <= 0) {
            throw new InvalidArgumentException('Journal entries must belong to a SiteSection.');
        }
        return (int) $sectionId;
    }
}
