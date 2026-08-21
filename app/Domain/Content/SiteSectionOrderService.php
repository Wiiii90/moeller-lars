<?php

namespace App\Domain\Content;

use App\Domain\Admin\AdminAuditService;
use App\Models\SiteSection;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use LogicException;

final class SiteSectionOrderService
{
    private const REQUEST_CACHE_ATTRIBUTE = 'site-section-order:sibling-ids';

    public function __construct(
        private readonly AdminAuditService $audit,
        private readonly Request $request,
    ) {}

    public function canMove(SiteSection $section, string $direction): bool
    {
        $this->validateDirection($direction);

        $ids = $this->orderedSiblingIds($section);
        $index = array_search((int) $section->getKey(), $ids, true);

        if ($index === false) {
            return false;
        }

        return $direction === 'up' ? $index > 0 : $index < count($ids) - 1;
    }

    public function move(SiteSection $section, string $direction): bool
    {
        $this->validateDirection($direction);
        $actor = $this->audit->requireActor();

        $moved = DB::transaction(function () use ($section, $direction, $actor): bool {
            /** @var SiteSection $fresh */
            $fresh = SiteSection::query()->whereKey($section->getKey())->lockForUpdate()->firstOrFail();
            /** @var Collection<int, SiteSection> $siblings */
            $siblings = $this->siblings($fresh)
                ->orderBy('position')
                ->orderBy('id')
                ->lockForUpdate()
                ->get();

            $ordered = $siblings->values()->all();
            $slots = $siblings
                ->map(static fn (SiteSection $candidate): int => (int) $candidate->getAttribute('position'))
                ->values()
                ->all();

            if (count($slots) !== count(array_unique($slots))) {
                throw new LogicException('Sibling site-section positions must be unique before reordering.');
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
                $position = $slots[$slotIndex];
                if ((int) $candidate->getAttribute('position') !== $position) {
                    $changes[] = [$candidate, $position];
                }
            }

            $temporaryBase = max($slots) + count($siblings) + 100;
            foreach ($changes as $offset => [$candidate]) {
                DB::table('site_sections')->where('id', $candidate->getKey())->update([
                    'position' => $temporaryBase + $offset,
                ]);
            }

            foreach ($changes as [$candidate, $position]) {
                DB::table('site_sections')->where('id', $candidate->getKey())->update([
                    'position' => $position,
                    'updated_at' => now(),
                ]);
            }

            $this->audit->record(
                $actor,
                'site_section.reordered',
                'site_section',
                (int) $fresh->getKey(),
                ['direction' => $direction],
            );

            return true;
        });

        if ($moved) {
            $this->forgetOrderedSiblingIds($section);
        }

        return $moved;
    }

    /** @return list<int> */
    private function orderedSiblingIds(SiteSection $section): array
    {
        $cache = $this->request->attributes->get(self::REQUEST_CACHE_ATTRIBUTE, []);
        if (! is_array($cache)) {
            $cache = [];
        }

        $key = $this->siblingCacheKey($section);
        $cached = $cache[$key] ?? null;
        if (is_array($cached)) {
            return array_values(array_map(static fn (mixed $id): int => (int) $id, $cached));
        }

        $ids = $this->siblings($section)
            ->orderBy('position')
            ->orderBy('id')
            ->pluck('id')
            ->map(static fn ($id): int => (int) $id)
            ->all();

        $cache[$key] = $ids;
        $this->request->attributes->set(self::REQUEST_CACHE_ATTRIBUTE, $cache);

        return $ids;
    }

    private function forgetOrderedSiblingIds(SiteSection $section): void
    {
        $cache = $this->request->attributes->get(self::REQUEST_CACHE_ATTRIBUTE, []);
        if (! is_array($cache)) {
            return;
        }

        unset($cache[$this->siblingCacheKey($section)]);
        $this->request->attributes->set(self::REQUEST_CACHE_ATTRIBUTE, $cache);
    }

    private function siblingCacheKey(SiteSection $section): string
    {
        $parentId = $section->getAttribute('parent_id');
        if ($parentId !== null) {
            return 'parent:'.(int) $parentId;
        }

        return (string) $section->getAttribute('type') === SiteSection::TYPE_HOME
            ? 'root:home'
            : 'root:navigation';
    }

    /** @return Builder<SiteSection> */
    private function siblings(SiteSection $section): Builder
    {
        /** @var Builder<SiteSection> $query */
        $query = SiteSection::query();
        $parentId = $section->getAttribute('parent_id');

        if ($parentId !== null) {
            return $query->where('parent_id', $parentId);
        }

        $query->whereNull('parent_id');
        if ((string) $section->getAttribute('type') === SiteSection::TYPE_HOME) {
            $query->where('type', SiteSection::TYPE_HOME);
        } else {
            $query->where('type', '<>', SiteSection::TYPE_HOME);
        }

        return $query;
    }

    private function validateDirection(string $direction): void
    {
        if (in_array($direction, ['up', 'down'], true) === false) {
            throw new InvalidArgumentException('Site-section order direction must be up or down.');
        }
    }
}
