<?php

namespace App\Filament\Pages\Concerns;

use App\Models\CustomPageSetting;
use App\Models\CvEntry;

trait CustomPageWorkspaceChildProjection
{
    /**
     * @param list<CvEntry> $cvRecords
     * @return list<array<string, mixed>>
     */
    private function componentChildren(
        CustomPageSetting $settings,
        array $block,
        array $cvRecords,
        int $componentIndex,
        bool $parentPublished,
        bool $reorderEnabled,
    ): array {
        $type = $block['type'] ?? null;

        if ($type === 'cv_list') {
            $count = count($cvRecords);

            return array_values(array_map(function (CvEntry $entry, int $index) use ($count, $parentPublished, $reorderEnabled): array {
                $meta = array_values(array_filter([
                    $entry->getAttribute('organisation'),
                    $entry->getAttribute('location'),
                ], static fn (mixed $value): bool => is_string($value) && trim($value) !== ''));
                $state = (string) $entry->getAttribute('state');
                $status = $state === 'published' ? 'Published' : 'Unpublished';

                return [
                    'kind' => 'cv',
                    'key' => 'cv-'.(int) $entry->getKey(),
                    'target' => 'cv:'.(int) $entry->getKey(),
                    'position' => $index + 1,
                    'date' => (string) ($entry->getAttribute('year_text') ?? ''),
                    'entry' => (string) $entry->getAttribute('title'),
                    'detail' => implode(' · ', $meta),
                    'status' => $status,
                    'state' => $state,
                    'published' => $state === 'published',
                    'parent_published' => $parentPublished,
                    'entry_id' => (int) $entry->getKey(),
                    'can_move_up' => $reorderEnabled && $index > 0,
                    'can_move_down' => $reorderEnabled && $index < $count - 1,
                    'search_text' => implode(' ', array_filter([
                        $entry->getAttribute('section'),
                        $entry->getAttribute('year_text'),
                        $entry->getAttribute('title'),
                        $entry->getAttribute('organisation'),
                        $entry->getAttribute('location'),
                        $entry->getAttribute('external_url'),
                        $status,
                    ], static fn (mixed $value): bool => is_string($value) && trim($value) !== '')),
                ];
            }, $cvRecords, array_keys($cvRecords)));
        }

        if ($type === 'list') {
            $items = is_array($block['items'] ?? null) ? array_values($block['items']) : [];
            $children = [];
            $count = count($items);
            foreach ($items as $itemIndex => $item) {
                if (! is_array($item)) {
                    continue;
                }
                $published = CustomPageSetting::listItemPublished($item);
                $detail = implode(' · ', array_values(array_filter([
                    is_string($item['meta'] ?? null) ? trim($item['meta']) : '',
                    is_string($item['location'] ?? null) ? trim($item['location']) : '',
                    $this->contentExcerpt($item['body'] ?? null, 110),
                ], static fn (string $value): bool => $value !== '')));

                $children[] = [
                    'kind' => 'list',
                    'key' => 'list-'.$itemIndex,
                    'target' => 'list:'.$componentIndex.':'.$itemIndex,
                    'position' => $itemIndex + 1,
                    'date' => is_string($item['date'] ?? null) ? $item['date'] : '',
                    'entry' => is_string($item['title'] ?? null) ? $item['title'] : '',
                    'detail' => $detail,
                    'status' => $published ? 'Published' : 'Unpublished',
                    'published' => $published,
                    'parent_published' => $parentPublished,
                    'item_index' => $itemIndex,
                    'can_move_up' => $reorderEnabled && $itemIndex > 0,
                    'can_move_down' => $reorderEnabled && $itemIndex < $count - 1,
                    'search_text' => implode(' ', array_filter([
                        $item['date'] ?? null,
                        $item['title'] ?? null,
                        $item['meta'] ?? null,
                        $item['location'] ?? null,
                        $item['body'] ?? null,
                        $item['url'] ?? null,
                        $published ? 'Published' : 'Unpublished',
                    ], static fn (mixed $value): bool => is_string($value) && trim($value) !== '')),
                ];
            }

            return $children;
        }

        if ($type === 'contact') {
            $children = [];
            $contactChildren = $settings->contactChildren($block);
            $count = count($contactChildren);
            foreach ($contactChildren as $childIndex => $child) {
                if (! is_array($child)) {
                    continue;
                }
                $childType = is_string($child['type'] ?? null) ? $child['type'] : '';
                if (! array_key_exists($childType, self::CONTACT_CHILD_LABELS)) {
                    continue;
                }
                $published = CustomPageSetting::contactChildPublished($child);
                $detail = match ($childType) {
                    'public_email' => 'Canonical email from General',
                    'social_links' => $this->socialChildDetail($child),
                    'contact_form' => ($child['form_state'] ?? 'enabled') === 'under_construction'
                        ? 'Under construction'
                        : 'Enabled',
                    default => '',
                };
                $children[] = [
                    'kind' => 'contact',
                    'key' => 'contact-'.$childType,
                    'target' => 'contact:'.$componentIndex.':'.$childType,
                    'position' => $childIndex + 1,
                    'child_type' => $childType,
                    'date' => '',
                    'entry' => self::CONTACT_CHILD_LABELS[$childType],
                    'detail' => $detail,
                    'status' => $published ? 'Published' : 'Unpublished',
                    'published' => $published,
                    'parent_published' => $parentPublished,
                    'can_move_up' => $reorderEnabled && $childIndex > 0,
                    'can_move_down' => $reorderEnabled && $childIndex < $count - 1,
                    'search_text' => self::CONTACT_CHILD_LABELS[$childType].' '.$detail.' '.($published ? 'Published' : 'Unpublished'),
                ];
            }

            return $children;
        }

        return [];
    }

    private function componentReorderEnabled(): bool
    {
        return trim($this->componentSearch) === ''
            && $this->componentType === 'any'
            && $this->page === 1
            && $this->total <= $this->pageSize;
    }
}
