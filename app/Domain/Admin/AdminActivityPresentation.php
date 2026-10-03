<?php

namespace App\Domain\Admin;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

final class AdminActivityPresentation
{
    /** @var array<int|string,string>|null */
    private ?array $artworkLabels = null;

    /** @var array<int|string,string>|null */
    private ?array $artworkMediaLabels = null;

    /** @var array<int|string,string>|null */
    private ?array $blogPostLabels = null;

    /** @var array<int|string,string>|null */
    private ?array $exhibitionLabels = null;

    /** @var array<int|string,string>|null */
    private ?array $siteSectionLabels = null;
    /**
     * @param array{count:int,truncated:bool,items:list<array{field:string,label:string,before:string,after:string}>}|null $summary
     * @param array{scope:string,before_state:list<string>,after_state:list<string>,event_count:int,item_count:int}|null $ordering
     * @return array{change:string,details:?string}
     */
    public function present(
        string $actionKey,
        string $target,
        ?array $summary = null,
        ?array $ordering = null,
    ): array {
        return [
            'change' => $this->change($actionKey),
            'details' => $ordering !== null
                ? $this->orderingDetails($actionKey, $target, $ordering)
                : $this->changeDetails($target, $summary),
        ];
    }

    public function change(string $actionKey): string
    {
        $definition = AdminActionCatalog::definition($actionKey);
        $label = trim((string) $definition['label']);
        $area = trim((string) $definition['area']);

        if ($actionKey === 'artwork_category.gallery_reordered') {
            $label = 'Ordered artworks';
        }

        if ($area !== '' && ! $this->mentionsArea($label, $area)) {
            $label .= ' in '.$area;
        }

        return Str::ucfirst($label);
    }

    /**
     * @param array{count:int,truncated:bool,items:list<array{field:string,label:string,before:string,after:string}>}|null $summary
     */
    private function changeDetails(string $target, ?array $summary): ?string
    {
        if ($summary === null || ($summary['items'] ?? []) === []) {
            return $target === '' ? null : 'Affected “'.$target.'”.';
        }

        $items = collect($summary['items'])
            ->take(3)
            ->map(static fn (array $item): string => $item['label'].' “'.$item['before'].'” → “'.$item['after'].'”')
            ->values();

        if ($items->isEmpty()) {
            return $target === '' ? null : 'Affected “'.$target.'”.';
        }

        $remaining = max(0, (int) $summary['count'] - $items->count());
        $detail = ($target === '' ? '' : '“'.$target.'”: ').$items->implode('; ');

        if ($remaining > 0) {
            $detail .= '; +'.$remaining.' more change'.($remaining === 1 ? '' : 's');
        }

        return $detail.'.';
    }

    /**
     * @param array{scope:string,before_state:list<string>,after_state:list<string>,event_count:int,item_count:int} $ordering
     */
    private function orderingDetails(string $actionKey, string $target, array $ordering): string
    {
        if ($actionKey === 'site_section.reordered') {
            return $this->navigationDetails($target, $ordering);
        }

        $before = AdminOrderingState::denormalize($ordering['before_state']);
        $after = AdminOrderingState::denormalize($ordering['after_state']);
        $eventCount = max(1, (int) $ordering['event_count']);

        if ($before === $after) {
            return '“'.$target.'” returned to its starting order after '.$eventCount.' ordering change'.($eventCount === 1 ? '' : 's').'.';
        }

        $beforePositions = $this->positionMap($before);
        $afterPositions = $this->positionMap($after);

        if (array_keys($beforePositions) !== array_keys($afterPositions)) {
            $beforeKeys = array_keys($beforePositions);
            $afterKeys = array_keys($afterPositions);
            sort($beforeKeys);
            sort($afterKeys);

            if ($beforeKeys !== $afterKeys) {
                return 'The ordering in “'.$target.'” changed across '.max(count($before), count($after)).' items.';
            }
        }

        $moved = [];
        foreach ($beforePositions as $identity => $beforePosition) {
            $afterPosition = $afterPositions[$identity] ?? null;
            if ($afterPosition !== null && $afterPosition !== $beforePosition) {
                $moved[$identity] = [
                    'before' => $beforePosition,
                    'after' => $afterPosition,
                    'value' => $this->valueForIdentity($before, $identity),
                ];
            }
        }

        if ($moved === []) {
            return 'The ordering in “'.$target.'” changed.';
        }

        $labels = $this->orderingLabels(
            $actionKey,
            array_values(array_map(static fn (array $move): int|string => $move['value'], $moved)),
        );

        if (count($moved) === 2) {
            $pairs = array_values($moved);
            if ($pairs[0]['before'] === $pairs[1]['after'] && $pairs[1]['before'] === $pairs[0]['after']) {
                $firstLabel = $labels[$this->identityKey($pairs[0]['value'])] ?? null;
                $secondLabel = $labels[$this->identityKey($pairs[1]['value'])] ?? null;

                if ($firstLabel !== null && $secondLabel !== null) {
                    return '“'.$firstLabel.'” and “'.$secondLabel.'” swapped positions '.$pairs[0]['before'].' and '.$pairs[1]['before'].' in “'.$target.'”.';
                }

                return 'Items at positions '.$pairs[0]['before'].' and '.$pairs[1]['before'].' were swapped in “'.$target.'”.';
            }
        }

        $first = reset($moved);
        $firstLabel = $labels[$this->identityKey($first['value'])] ?? null;
        $otherCount = count($moved) - 1;
        $detail = $firstLabel !== null
            ? '“'.$firstLabel.'” moved from position '.$first['before'].' to '.$first['after'].' in “'.$target.'”'
            : 'An item moved from position '.$first['before'].' to '.$first['after'].' in “'.$target.'”';

        if ($otherCount > 0) {
            $detail .= '; '.$otherCount.' other item'.($otherCount === 1 ? '' : 's').' shifted';
        }

        return $detail.'.';
    }

    /**
     * @param array{scope:string,before_state:list<string>,after_state:list<string>,event_count:int,item_count:int} $ordering
     */
    private function navigationDetails(string $target, array $ordering): string
    {
        $before = $this->navigationState($ordering['before_state']);
        $after = $this->navigationState($ordering['after_state']);
        $eventCount = max(1, (int) $ordering['event_count']);

        if ($before === $after) {
            return '“'.$target.'” returned to its starting arrangement after '.$eventCount.' ordering change'.($eventCount === 1 ? '' : 's').'.';
        }

        $changed = [];
        foreach ($before as $id => $placement) {
            $next = $after[$id] ?? null;
            if ($next !== null && $next !== $placement) {
                $changed[$id] = ['before' => $placement, 'after' => $next];
            }
        }

        if ($changed === []) {
            return 'The navigation arrangement changed in “'.$target.'”.';
        }

        $ids = array_keys($changed);
        foreach ($changed as $placement) {
            foreach ([$placement['before']['parent_id'], $placement['after']['parent_id']] as $parentId) {
                if (is_int($parentId)) {
                    $ids[] = $parentId;
                }
            }
        }
        $ids = array_values(array_unique($ids));
        $allLabels = $this->siteSectionLabels();
        $labels = array_intersect_key($allLabels, array_flip($ids));

        $firstId = array_key_first($changed);
        $first = $changed[$firstId];
        $entry = trim((string) ($labels[$firstId] ?? 'Navigation entry #'.$firstId));
        $detail = '“'.$entry.'” moved from '
            .$this->navigationPlacement($first['before'], $labels)
            .' to '
            .$this->navigationPlacement($first['after'], $labels);

        $otherCount = count($changed) - 1;
        if ($otherCount > 0) {
            $detail .= '; '.$otherCount.' other navigation entr'.($otherCount === 1 ? 'y' : 'ies').' moved';
        }

        return $detail.'.';
    }

    /**
     * @param list<string> $state
     * @return array<int,array{parent_id:?int,position:int}>
     */
    private function navigationState(array $state): array
    {
        $rows = [];

        foreach (AdminOrderingState::denormalize($state) as $identity) {
            if (! is_string($identity) || preg_match('/^(\d+):(root|\d+):(\d+)$/', $identity, $matches) !== 1) {
                continue;
            }

            $rows[(int) $matches[1]] = [
                'parent_id' => $matches[2] === 'root' ? null : (int) $matches[2],
                'position' => (int) $matches[3],
            ];
        }

        ksort($rows);

        return $rows;
    }

    /** @param array{parent_id:?int,position:int} $placement @param array<int|string,mixed> $labels */
    private function navigationPlacement(array $placement, array $labels): string
    {
        $storedPosition = $placement['position'];
        $position = $storedPosition > 0 && $storedPosition % 10 === 0
            ? intdiv($storedPosition, 10)
            : $storedPosition;

        if ($placement['parent_id'] === null) {
            return 'root position '.$position;
        }

        $parent = trim((string) ($labels[$placement['parent_id']] ?? 'page #'.$placement['parent_id']));

        return 'position '.$position.' under “'.$parent.'”';
    }

    /**
     * @param list<int|string> $values
     * @return array<string,string>
     */
    private function orderingLabels(string $actionKey, array $values): array
    {
        $ids = array_values(array_unique(array_filter(
            array_map(static fn (int|string $value): ?int => is_int($value) ? $value : null, $values),
            static fn (?int $id): bool => $id !== null && $id > 0,
        )));

        if ($ids === []) {
            return [];
        }

        $allRows = match ($actionKey) {
            'artwork_category.gallery_reordered', 'site_section.home_artworks_reordered' => $this->artworkLabels(),
            'artwork.additional_media_reordered' => $this->artworkMediaLabels(),
            'blog_post.reordered' => $this->blogPostLabels(),
            'exhibition.reordered' => $this->exhibitionLabels(),
            default => [],
        };
        $rows = array_intersect_key($allRows, array_flip($ids));

        $labels = [];
        foreach ($rows as $id => $label) {
            $label = trim((string) $label);
            if ($label !== '') {
                $labels[$this->identityKey((int) $id)] = $label;
            }
        }

        return $labels;
    }

    /** @return array<int|string,string> */
    private function artworkLabels(): array
    {
        return $this->artworkLabels ??= DB::table('artworks')
            ->pluck('title', 'id')
            ->map(static fn (mixed $label): string => trim((string) $label))
            ->all();
    }

    /** @return array<int|string,string> */
    private function artworkMediaLabels(): array
    {
        return $this->artworkMediaLabels ??= DB::table('artwork_media as usage')
            ->join('media_assets as asset', 'asset.id', '=', 'usage.media_asset_id')
            ->pluck('asset.original_filename', 'usage.id')
            ->map(static fn (mixed $label): string => trim((string) $label))
            ->all();
    }

    /** @return array<int|string,string> */
    private function blogPostLabels(): array
    {
        return $this->blogPostLabels ??= DB::table('blog_posts')
            ->pluck('title', 'id')
            ->map(static fn (mixed $label): string => trim((string) $label))
            ->all();
    }

    /** @return array<int|string,string> */
    private function exhibitionLabels(): array
    {
        return $this->exhibitionLabels ??= DB::table('exhibitions')
            ->pluck('title', 'id')
            ->map(static fn (mixed $label): string => trim((string) $label))
            ->all();
    }

    /** @return array<int|string,string> */
    private function siteSectionLabels(): array
    {
        return $this->siteSectionLabels ??= DB::table('site_sections')
            ->pluck('title', 'id')
            ->map(static fn (mixed $label): string => trim((string) $label))
            ->all();
    }

    /** @param list<int|string> $values @return array<string,int> */
    private function positionMap(array $values): array
    {
        $positions = [];
        foreach ($values as $index => $value) {
            $positions[$this->identityKey($value)] = $index + 1;
        }
        ksort($positions);

        return $positions;
    }

    /** @param list<int|string> $values */
    private function valueForIdentity(array $values, string $identity): int|string
    {
        foreach ($values as $value) {
            if ($this->identityKey($value) === $identity) {
                return $value;
            }
        }

        return $identity;
    }

    private function identityKey(int|string $value): string
    {
        return is_int($value) ? 'i:'.$value : 's:'.$value;
    }

    private function mentionsArea(string $label, string $area): bool
    {
        $label = mb_strtolower($label);
        $area = mb_strtolower($area);
        $singular = mb_strtolower(Str::singular($area));

        return str_contains($label, $area)
            || ($singular !== '' && str_contains($label, $singular));
    }
}
