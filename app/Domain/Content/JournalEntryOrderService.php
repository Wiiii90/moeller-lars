<?php

namespace App\Domain\Content;

use App\Domain\Admin\AdminAuditService;
use App\Models\BlogPost;
use App\Models\Exhibition;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

final class JournalEntryOrderService
{
    public function __construct(private readonly AdminAuditService $audit) {}

    public function nextPosition(Model $model, int $siteSectionId): int
    {
        $maximum = $model->newQuery()
            ->where('site_section_id', $siteSectionId)
            ->lockForUpdate()
            ->max('position');

        return $maximum === null ? 0 : ((int) $maximum) + 1;
    }

    public function canMove(Model $record, string $direction): bool
    {
        $this->assertSupported($record);
        $this->assertDirection($direction);
        $sectionId = $this->sectionId($record);

        $ids = $record->newQuery()
            ->where('site_section_id', $sectionId)
            ->orderBy('position')
            ->orderBy('id')
            ->pluck('id')
            ->map(static fn ($id): int => (int) $id)
            ->all();
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
            /** @var Collection<int, Model> $records */
            $records = $record->newQuery()
                ->where('site_section_id', $sectionId)
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

            $maximum = (int) ($records->max('position') ?? 0);
            $temporaryBase = $maximum + count($records) + 1;
            $changes = [];
            foreach ($ordered as $position => $candidate) {
                if ((int) $candidate->getAttribute('position') !== $position) {
                    $changes[] = [$candidate, $position];
                }
            }

            foreach ($changes as $offset => [$candidate]) {
                DB::table($record->getTable())
                    ->where('id', $candidate->getKey())
                    ->update(['position' => $temporaryBase + $offset]);
            }

            foreach ($changes as [$candidate, $position]) {
                DB::table($record->getTable())
                    ->where('id', $candidate->getKey())
                    ->update(['position' => $position, 'updated_at' => now()]);
                $this->audit->record(
                    $actor,
                    $candidate instanceof BlogPost ? 'blog_post.reordered' : 'exhibition.reordered',
                    $candidate instanceof BlogPost ? 'blog_post' : 'exhibition',
                    $candidate->getKey(),
                    ['position' => $position, 'site_section_id' => $sectionId],
                );
            }

            return true;
        });
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
