<?php

namespace App\Filament\Pages\Concerns;

use App\Domain\Admin\EditorialRecordService;
use App\Domain\Content\CustomPageEditorialService;
use App\Models\CustomPageSetting;
use App\Models\CvEntry;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

trait CustomPageWorkspaceChildOrdering
{
    public function sortChild(string $target, int $position): void
    {
        if (! $this->componentReorderEnabled()) {
            return;
        }

        $parts = explode(':', $target);
        $kind = $parts[0] ?? null;
        if ($kind === 'cv' && isset($parts[1]) && ctype_digit($parts[1])) {
            /** @var CvEntry $entry */
            $entry = CvEntry::query()->findOrFail((int) $parts[1]);
            app(EditorialRecordService::class)->sortCv($entry, $position);
            $this->clearSelections();
            $this->loadComponentProjection(refreshCvCount: true);

            return;
        }

        if ($kind === 'list' && isset($parts[1], $parts[2]) && ctype_digit($parts[1]) && ctype_digit($parts[2])) {
            app(CustomPageEditorialService::class)->sortListItem(
                $this->settings(),
                (int) $parts[1],
                'list',
                (int) $parts[2],
                $position,
            );
            $this->clearSelections();
            $this->loadComponentProjection(refreshCvCount: false);

            return;
        }

        if ($kind === 'contact' && isset($parts[1], $parts[2]) && ctype_digit($parts[1]) && array_key_exists($parts[2], self::CONTACT_CHILD_LABELS)) {
            app(CustomPageEditorialService::class)->sortContactChild(
                $this->settings(),
                (int) $parts[1],
                'contact',
                $parts[2],
                $position,
            );
            $this->clearSelections();
            $this->loadComponentProjection(refreshCvCount: false);

            return;
        }

        throw ValidationException::withMessages(['component' => 'The child sequence is invalid.']);
    }

    public function publishSelectedChildren(): void
    {
        $this->publishSelected();
    }

    public function unpublishSelectedChildren(): void
    {
        $this->unpublishSelected();
    }

    public function deleteSelectedChildrenAction(): Action
    {
        return $this->deleteSelectedAction();
    }

    private function setSelectedPublished(bool $published): void
    {
        $parents = $this->selectedComponentTargetData();
        $children = $this->selectedChildTargetData();
        if ($parents === [] && $children === []) {
            return;
        }

        DB::transaction(function () use ($parents, $children, $published): void {
            $this->applySelectedChildPublication($children, $published);

            foreach ($parents as $target) {
                $block = $this->componentAt($target['index'], $target['type']);
                if (CustomPageSetting::componentPublished($block) === $published) {
                    continue;
                }
                $block['published'] = $published;
                app(CustomPageEditorialService::class)->updateBlock(
                    $this->settings(),
                    $target['index'],
                    $target['type'],
                    $block,
                );
            }
        });

        $count = count($parents) + count($children);
        $this->clearSelections();
        $this->reloadWorkspace();
        Notification::make()
            ->title($published ? 'Selection published' : 'Selection unpublished')
            ->body($count.' selected '.($count === 1 ? 'item' : 'items').' updated on their own hierarchy levels.')
            ->success()
            ->send();
    }

    /** @param list<array<string,mixed>> $targets */
    private function applySelectedChildPublication(array $targets, bool $published): void
    {
        $listGroups = [];
        $contactGroups = [];
        $cvIds = [];

        foreach ($targets as $target) {
            if ($target['kind'] === 'list') {
                $listGroups[$target['component_index']][] = $target['item_index'];
            } elseif ($target['kind'] === 'contact') {
                $contactGroups[$target['component_index']][] = $target['child_type'];
            } elseif ($target['kind'] === 'cv') {
                $cvIds[] = $target['entry_id'];
            }
        }

        foreach ($listGroups as $componentIndex => $indices) {
            app(CustomPageEditorialService::class)->setListItemsPublished($this->settings(), (int) $componentIndex, 'list', $indices, $published);
        }
        foreach ($contactGroups as $componentIndex => $types) {
            app(CustomPageEditorialService::class)->setContactChildrenPublished($this->settings(), (int) $componentIndex, 'contact', $types, $published);
        }

        $records = app(EditorialRecordService::class);
        foreach (array_values(array_unique($cvIds)) as $entryId) {
            /** @var CvEntry|null $entry */
            $entry = CvEntry::query()->find($entryId);
            if (! $entry instanceof CvEntry) {
                continue;
            }
            $state = (string) $entry->getAttribute('state');
            if ($published && in_array($state, ['archived', 'hidden'], true)) {
                /** @var CvEntry $entry */
                $entry = $records->restoreDraft($entry);
                $state = 'draft';
            }
            if ($published && $state === 'draft') {
                $records->publish($entry);
            } elseif (! $published && $state === 'published') {
                $records->unpublish($entry);
            }
        }
    }

    /** @param list<array<string,mixed>> $targets */
    private function moveSelectedChildTargets(array $targets, string $direction): bool
    {
        $changed = false;
        $listGroups = [];
        $contactGroups = [];
        $cvIds = [];

        foreach ($targets as $target) {
            if ($target['kind'] === 'list') {
                $listGroups[$target['component_index']][] = $target['item_index'];
            } elseif ($target['kind'] === 'contact') {
                $contactGroups[$target['component_index']][] = $target['child_type'];
            } elseif ($target['kind'] === 'cv') {
                $cvIds[] = $target['entry_id'];
            }
        }

        foreach ($listGroups as $componentIndex => $indices) {
            $indices = array_values(array_unique(array_map('intval', $indices)));
            $direction === 'up' ? sort($indices) : rsort($indices);
            foreach ($indices as $itemIndex) {
                $changed = app(CustomPageEditorialService::class)->moveListItem(
                    $this->settings(),
                    (int) $componentIndex,
                    'list',
                    $itemIndex,
                    $direction,
                ) || $changed;
            }
        }

        foreach ($contactGroups as $componentIndex => $types) {
            $block = $this->componentAt((int) $componentIndex, 'contact');
            $order = collect($this->settings()->contactChildren($block))
                ->pluck('type')
                ->filter(static fn (mixed $type): bool => is_string($type))
                ->values()
                ->all();
            $types = array_values(array_unique(array_filter($types, static fn (mixed $type): bool => is_string($type))));
            usort($types, static function (string $left, string $right) use ($order, $direction): int {
                $leftIndex = array_search($left, $order, true);
                $rightIndex = array_search($right, $order, true);
                $comparison = (int) $leftIndex <=> (int) $rightIndex;

                return $direction === 'up' ? $comparison : -$comparison;
            });
            foreach ($types as $childType) {
                $changed = app(CustomPageEditorialService::class)->moveContactChild(
                    $this->settings(),
                    (int) $componentIndex,
                    'contact',
                    $childType,
                    $direction,
                ) || $changed;
            }
        }

        if ($cvIds !== []) {
            $entries = CvEntry::query()
                ->whereIn('id', array_values(array_unique($cvIds)))
                ->orderBy('position')
                ->orderBy('id')
                ->get()
                ->all();
            if ($direction === 'down') {
                $entries = array_reverse($entries);
            }
            foreach ($entries as $entry) {
                if ($entry instanceof CvEntry) {
                    $changed = app(EditorialRecordService::class)->move($entry, $direction) || $changed;
                }
            }
        }

        return $changed;
    }

    /**
     * @param list<array<string,mixed>> $children
     * @param list<array{index:int,type:string}> $parents
     */
    private function deleteSelectedChildTargets(array $children, array $parents): void
    {
        $parentKeys = [];
        foreach ($parents as $parent) {
            $parentKeys[$parent['index'].':'.$parent['type']] = true;
        }

        $listGroups = [];
        $contactGroups = [];
        $cvIds = [];
        foreach ($children as $target) {
            if ($target['kind'] === 'list') {
                if (! isset($parentKeys[$target['component_index'].':list'])) {
                    $listGroups[$target['component_index']][] = $target['item_index'];
                }
            } elseif ($target['kind'] === 'contact') {
                if (! isset($parentKeys[$target['component_index'].':contact'])) {
                    $contactGroups[$target['component_index']][] = $target['child_type'];
                }
            } elseif ($target['kind'] === 'cv') {
                $cvIds[] = $target['entry_id'];
            }
        }

        foreach ($listGroups as $componentIndex => $indices) {
            app(CustomPageEditorialService::class)->deleteListItems($this->settings(), (int) $componentIndex, 'list', $indices);
        }
        foreach ($contactGroups as $componentIndex => $types) {
            app(CustomPageEditorialService::class)->deleteContactChildren($this->settings(), (int) $componentIndex, 'contact', $types);
        }
        foreach (array_values(array_unique($cvIds)) as $entryId) {
            /** @var CvEntry|null $entry */
            $entry = CvEntry::query()->find($entryId);
            if ($entry instanceof CvEntry) {
                app(EditorialRecordService::class)->deleteCv($entry);
            }
        }
    }
}
