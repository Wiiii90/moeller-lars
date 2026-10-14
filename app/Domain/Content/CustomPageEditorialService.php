<?php

namespace App\Domain\Content;

use App\Domain\Admin\AdminAuditService;
use App\Domain\Admin\AdminOrderingState;
use App\Models\CustomPageSetting;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use InvalidArgumentException;

final class CustomPageEditorialService
{
    public function __construct(private readonly AdminAuditService $audit) {}

    /** @param array<string, mixed> $block */
    public function addBlock(CustomPageSetting $settings, array $block): bool
    {
        return DB::transaction(function () use ($settings, $block): bool {
            $fresh = $this->locked($settings);
            $blocks = $fresh->components();
            $type = $block['type'] ?? null;
            $this->assertComponentType(is_string($type) ? $type : '');
            if ($type === 'legal_disclaimer' && $this->containsType($blocks, 'legal_disclaimer')) {
                throw ValidationException::withMessages(['component' => 'This page already contains a Rechtliches component.']);
            }
            $blocks[] = $block;

            return $this->persist($fresh, $blocks);
        });
    }

    /** @param array<string, mixed> $block */
    public function updateBlock(CustomPageSetting $settings, int $index, string $expectedType, array $block): bool
    {
        return DB::transaction(function () use ($settings, $index, $expectedType, $block): bool {
            $fresh = $this->locked($settings);
            $blocks = $fresh->components();
            $this->assertTarget($blocks, $index, $expectedType);

            if (($block['type'] ?? null) !== $expectedType) {
                throw ValidationException::withMessages(['component' => 'The component type changed while it was being edited.']);
            }

            $blocks[$index] = $block;

            return $this->persist($fresh, $blocks);
        });
    }

    /**
     * Move an existing prose component under a decorative Image without
     * copying, dropping, or reauthoring its content. The source disappears
     * from the top-level sequence in the same audited transaction.
     *
     * @param array<string,mixed> $image
     */
    public function updateImageAndNest(
        CustomPageSetting $settings, int $imageIndex, array $image, int $sourceIndex,
    ): bool {
        return DB::transaction(function () use ($settings, $imageIndex, $image, $sourceIndex): bool {
            $fresh = $this->locked($settings);
            $blocks = $fresh->components();
            $this->assertTarget($blocks, $imageIndex, 'image');
            if (($image['type'] ?? null) !== 'image' || ! ($image['image_decorative'] ?? false)) {
                throw ValidationException::withMessages(['image_children' => 'Only a decorative image can own text.']);
            }
            $source = $blocks[$sourceIndex] ?? null;
            $type = is_array($source) ? ($source['type'] ?? null) : null;
            if ($sourceIndex === $imageIndex || ! in_array($type, CustomPageSetting::IMAGE_CHILD_TYPES, true)) {
                throw ValidationException::withMessages(['image_children' => 'Choose a text-capable page component.']);
            }
            if ($type === 'list' && ($source['media_asset_id'] ?? null) !== null) {
                throw ValidationException::withMessages(['image_children' => 'A List with media cannot move under an Image.']);
            }
            if ($type === 'contact') {
                foreach ($source['children'] ?? [] as $child) {
                    if (is_array($child) && ($child['type'] ?? null) === 'contact_form'
                        && CustomPageSetting::contactChildPublished($child)) {
                        throw ValidationException::withMessages(['image_children' => 'Move the contact form to a separate component first.']);
                    }
                }
            }
            $children = $image['children'] ?? [];
            if (! is_array($children) || ! array_is_list($children)) {
                throw ValidationException::withMessages(['image_children' => 'Image children must be ordered.']);
            }
            $image['children'] = [...$children, $source];
            $blocks[$imageIndex] = $image;
            array_splice($blocks, $sourceIndex, 1);

            return $this->persist($fresh, $blocks);
        });
    }

    /** @param array<string, mixed> $item */
    public function addListItem(CustomPageSetting $settings, int $index, string $expectedType, array $item): bool
    {
        return $this->mutateListItems($settings, $index, $expectedType, static function (array $items) use ($item): array {
            $items[] = $item;

            return $items;
        });
    }

    /** @param array<string, mixed> $item */
    public function updateListItem(CustomPageSetting $settings, int $index, string $expectedType, int $itemIndex, array $item): bool
    {
        return $this->mutateListItems($settings, $index, $expectedType, function (array $items) use ($itemIndex, $item): array {
            $this->assertListItem($items, $itemIndex);
            $items[$itemIndex] = $item;

            return $items;
        });
    }

    public function setListItemPublished(CustomPageSetting $settings, int $index, string $expectedType, int $itemIndex, bool $published): bool
    {
        return $this->setListItemsPublished($settings, $index, $expectedType, [$itemIndex], $published);
    }

    /** @param list<int> $itemIndices */
    public function setListItemsPublished(CustomPageSetting $settings, int $index, string $expectedType, array $itemIndices, bool $published): bool
    {
        return $this->mutateListItems($settings, $index, $expectedType, function (array $items) use ($itemIndices, $published): array {
            foreach (array_values(array_unique($itemIndices)) as $itemIndex) {
                $this->assertListItem($items, $itemIndex);
                $items[$itemIndex]['published'] = $published;
            }

            return $items;
        });
    }

    public function moveListItem(CustomPageSetting $settings, int $index, string $expectedType, int $itemIndex, string $direction): bool
    {
        $this->assertDirection($direction);

        return $this->mutateListItems($settings, $index, $expectedType, function (array $items) use ($itemIndex, $direction): array {
            $this->assertListItem($items, $itemIndex);
            $target = $direction === 'up' ? $itemIndex - 1 : $itemIndex + 1;
            if (! array_key_exists($target, $items)) {
                return $items;
            }
            [$items[$itemIndex], $items[$target]] = [$items[$target], $items[$itemIndex]];

            return $items;
        }, ordering: true);
    }

    public function sortListItem(CustomPageSetting $settings, int $index, string $expectedType, int $itemIndex, int $position): bool
    {
        return $this->mutateListItems($settings, $index, $expectedType, function (array $items) use ($itemIndex, $position): array {
            $this->assertListItem($items, $itemIndex);
            $moved = $items[$itemIndex];
            array_splice($items, $itemIndex, 1);
            $position = max(0, min($position, count($items)));
            array_splice($items, $position, 0, [$moved]);

            return $items;
        }, ordering: true);
    }

    public function deleteListItem(CustomPageSetting $settings, int $index, string $expectedType, int $itemIndex): bool
    {
        return $this->deleteListItems($settings, $index, $expectedType, [$itemIndex]);
    }

    /** @param list<int> $itemIndices */
    public function deleteListItems(CustomPageSetting $settings, int $index, string $expectedType, array $itemIndices): bool
    {
        return $this->mutateListItems($settings, $index, $expectedType, function (array $items) use ($itemIndices): array {
            $indices = array_values(array_unique($itemIndices));
            rsort($indices);
            foreach ($indices as $itemIndex) {
                $this->assertListItem($items, $itemIndex);
                unset($items[$itemIndex]);
            }

            return array_values($items);
        });
    }

    public function convertBlock(CustomPageSetting $settings, int $index, string $expectedType, string $targetType): bool
    {
        $this->assertComponentType($targetType);

        return DB::transaction(function () use ($settings, $index, $expectedType, $targetType): bool {
            $fresh = $this->locked($settings);
            $blocks = $fresh->components();
            $this->assertTarget($blocks, $index, $expectedType);
            if ($expectedType === $targetType) {
                return false;
            }
            if ($targetType === 'legal_disclaimer' && $this->containsType($blocks, 'legal_disclaimer', exceptIndex: $index)) {
                throw ValidationException::withMessages(['component' => 'This page already contains a Rechtliches component.']);
            }
            $blocks[$index] = $this->convertedBlock($blocks[$index], $targetType);

            return $this->persist($fresh, $blocks);
        });
    }

    /** @param array<string, mixed> $block */
    public function conversionLosesContent(array $block, string $targetType): bool
    {
        $this->assertComponentType($targetType);
        $currentType = is_string($block['type'] ?? null) ? $block['type'] : '';
        if ($currentType === $targetType) {
            return false;
        }

        $next = $this->convertedBlock($block, $targetType);
        foreach ($block as $key => $value) {
            if (in_array($key, ['type', 'published'], true)) {
                continue;
            }
            if (array_key_exists($key, $next) && $next[$key] === $value) {
                continue;
            }
            if ($this->meaningfulValue($value)) {
                return true;
            }
        }

        return false;
    }

    public function moveBlock(CustomPageSetting $settings, int $index, string $expectedType, string $direction): bool
    {
        $this->assertDirection($direction);

        return DB::transaction(function () use ($settings, $index, $expectedType, $direction): bool {
            $fresh = $this->locked($settings);
            $blocks = $fresh->components();
            $this->assertTarget($blocks, $index, $expectedType);
            $target = $direction === 'up' ? $index - 1 : $index + 1;
            if (! array_key_exists($target, $blocks)) {
                return false;
            }
            $beforeOrder = AdminOrderingState::fingerprints($blocks);
            [$blocks[$index], $blocks[$target]] = [$blocks[$target], $blocks[$index]];

            return $this->persist($fresh, $blocks, [
                'action' => 'site_section.page_components_reordered',
                'scope' => 'page-components:'.(int) $fresh->getAttribute('site_section_id'),
                'before' => $beforeOrder,
                'after' => AdminOrderingState::fingerprints($blocks),
            ]);
        });
    }

    /** @param list<array{index:int,type:string}> $targets */
    public function reorderBlocks(CustomPageSetting $settings, array $targets): bool
    {
        return DB::transaction(function () use ($settings, $targets): bool {
            $fresh = $this->locked($settings);
            $blocks = $fresh->components();
            if (count($targets) !== count($blocks)) {
                throw ValidationException::withMessages(['component' => 'The component sequence changed. Reload the workspace and try again.']);
            }
            $indices = $this->validatedIndices($blocks, $targets);
            if (count($indices) !== count($blocks)) {
                throw ValidationException::withMessages(['component' => 'The component sequence is incomplete.']);
            }
            $next = array_map(static fn (array $target): array => $blocks[$target['index']], $targets);

            return $this->persist($fresh, $next, [
                'action' => 'site_section.page_components_reordered',
                'scope' => 'page-components:'.(int) $fresh->getAttribute('site_section_id'),
                'before' => AdminOrderingState::fingerprints($blocks),
                'after' => AdminOrderingState::fingerprints($next),
            ]);
        });
    }

    public function deleteBlock(CustomPageSetting $settings, int $index, string $expectedType): bool
    {
        return $this->deleteBlocks($settings, [['index' => $index, 'type' => $expectedType]]);
    }

    /** @param list<array{index:int,type:string}> $targets */
    public function deleteBlocks(CustomPageSetting $settings, array $targets): bool
    {
        return DB::transaction(function () use ($settings, $targets): bool {
            $fresh = $this->locked($settings);
            $blocks = $fresh->components();
            $indices = $this->validatedIndices($blocks, $targets);
            if ($indices === []) {
                return false;
            }
            rsort($indices);
            foreach ($indices as $index) {
                unset($blocks[$index]);
            }

            return $this->persist($fresh, array_values($blocks));
        });
    }

    /** @param list<array{index:int,type:string}> $targets */
    public function moveSelectedBlocks(CustomPageSetting $settings, array $targets, string $direction): bool
    {
        $this->assertDirection($direction);

        return DB::transaction(function () use ($settings, $targets, $direction): bool {
            $fresh = $this->locked($settings);
            $blocks = $fresh->components();
            $indices = $this->validatedIndices($blocks, $targets);
            if ($indices === []) {
                return false;
            }

            $selected = array_fill_keys($indices, true);
            $sequence = [];
            foreach ($blocks as $index => $block) {
                $sequence[] = ['block' => $block, 'selected' => isset($selected[$index])];
            }

            if ($direction === 'up') {
                for ($index = 1, $count = count($sequence); $index < $count; $index++) {
                    if ($sequence[$index]['selected'] && ! $sequence[$index - 1]['selected']) {
                        [$sequence[$index - 1], $sequence[$index]] = [$sequence[$index], $sequence[$index - 1]];
                    }
                }
            } else {
                for ($index = count($sequence) - 2; $index >= 0; $index--) {
                    if ($sequence[$index]['selected'] && ! $sequence[$index + 1]['selected']) {
                        [$sequence[$index], $sequence[$index + 1]] = [$sequence[$index + 1], $sequence[$index]];
                    }
                }
            }

            $next = array_map(static fn (array $item): array => $item['block'], $sequence);

            return $this->persist($fresh, $next, [
                'action' => 'site_section.page_components_reordered',
                'scope' => 'page-components:'.(int) $fresh->getAttribute('site_section_id'),
                'before' => AdminOrderingState::fingerprints($blocks),
                'after' => AdminOrderingState::fingerprints($next),
            ]);
        });
    }

    /** @param callable(list<array<string,mixed>>): list<array<string,mixed>> $mutator */
    private function mutateListItems(
        CustomPageSetting $settings,
        int $index,
        string $expectedType,
        callable $mutator,
        bool $ordering = false,
    ): bool {
        return DB::transaction(function () use ($settings, $index, $expectedType, $mutator, $ordering): bool {
            $fresh = $this->locked($settings);
            $blocks = $fresh->components();
            $this->assertListTarget($blocks, $index, $expectedType);
            $beforeItems = $this->listItems($blocks[$index]);
            $afterItems = $mutator($beforeItems);
            $blocks[$index]['items'] = $afterItems;

            return $this->persist(
                $fresh,
                $blocks,
                $ordering ? [
                    'action' => 'site_section.list_entries_reordered',
                    'scope' => 'page-list:'.(int) $fresh->getAttribute('site_section_id').':'.$index,
                    'before' => AdminOrderingState::fingerprints($beforeItems),
                    'after' => AdminOrderingState::fingerprints($afterItems),
                ] : null,
            );
        });
    }

    private function locked(CustomPageSetting $settings): CustomPageSetting
    {
        /** @var CustomPageSetting $fresh */
        $fresh = CustomPageSetting::query()->whereKey($settings->getKey())->lockForUpdate()->firstOrFail();

        return $fresh;
    }

    /**
     * @param  list<array<string,mixed>>  $blocks
     * @param  list<array{index:int,type:string}>  $targets
     * @return list<int>
     */
    private function validatedIndices(array $blocks, array $targets): array
    {
        $indices = [];
        foreach ($targets as $target) {
            $index = $target['index'];
            $type = $target['type'];
            $this->assertTarget($blocks, $index, $type);
            $indices[] = $index;
        }
        $unique = array_values(array_unique($indices));
        if (count($unique) !== count($indices)) {
            throw ValidationException::withMessages(['component' => 'The component sequence contains duplicates.']);
        }
        sort($unique);

        return $unique;
    }

    /** @param list<array<string,mixed>> $blocks */
    private function assertTarget(array $blocks, int $index, string $expectedType): void
    {
        $this->assertComponentType($expectedType);
        $block = $blocks[$index] ?? null;
        if (! is_array($block) || ($block['type'] ?? null) !== $expectedType) {
            throw ValidationException::withMessages(['component' => 'This component changed. Reload the workspace and try again.']);
        }
    }

    /** @param list<array<string,mixed>> $blocks */
    private function assertListTarget(array $blocks, int $index, string $expectedType): void
    {
        $this->assertTarget($blocks, $index, $expectedType);
        if ($expectedType !== 'list') {
            throw ValidationException::withMessages(['component' => 'Only List components contain list entries.']);
        }
    }

    /** @param array<string,mixed> $block
     * @return list<array<string,mixed>>
     */
    private function listItems(array $block): array
    {
        return is_array($block['items'] ?? null) ? array_values($block['items']) : [];
    }

    /** @param list<array<string,mixed>> $items */
    private function assertListItem(array $items, int $itemIndex): void
    {
        if ($itemIndex < 0 || ! array_key_exists($itemIndex, $items)) {
            throw ValidationException::withMessages(['component' => 'This list entry changed. Reload the workspace and try again.']);
        }
    }

    /** @param array<string,mixed> $block */
    private function convertedBlock(array $block, string $targetType): array
    {
        $title = in_array($block['type'] ?? null, ['text', 'list', 'contact'], true) && is_string($block['title'] ?? null)
            ? $block['title']
            : null;
        $published = CustomPageSetting::componentPublished($block);

        return match ($targetType) {
            'image' => ['type' => 'image', 'published' => $published, 'media_asset_id' => null, 'image_decorative' => false],
            'text' => ['type' => 'text', 'published' => $published, 'title' => $title, 'body' => null],
            'list' => ['type' => 'list', 'published' => $published, 'title' => $title, 'media_asset_id' => null, 'items' => []],
            'divider' => ['type' => 'divider', 'published' => $published, 'variant' => 'thin'],
            'contact' => [
                'type' => 'contact',
                'published' => $published,
                'title' => $title,
                'children' => [
                    ['type' => 'public_email', 'published' => true],
                    ['type' => 'social_links', 'published' => true, 'social_platforms' => array_keys(SocialLinks::options())],
                    ['type' => 'contact_form', 'published' => true, 'form_state' => 'enabled', 'status_text' => null],
                ],
            ],
            'legal_disclaimer' => ['type' => 'legal_disclaimer', 'published' => $published],
            default => throw new InvalidArgumentException('Unsupported component type.'),
        };
    }

    /** @param list<array<string,mixed>> $blocks */
    private function containsType(array $blocks, string $type, ?int $exceptIndex = null): bool
    {
        foreach ($blocks as $index => $block) {
            if ($exceptIndex !== null && $index === $exceptIndex) {
                continue;
            }
            if (($block['type'] ?? null) === $type) {
                return true;
            }
        }

        return false;
    }

    private function meaningfulValue(mixed $value): bool
    {
        if ($value === null || $value === false || $value === '' || $value === []) {
            return false;
        }
        if (is_string($value)) {
            return trim($value) !== '';
        }
        if (is_array($value)) {
            foreach ($value as $nested) {
                if ($this->meaningfulValue($nested)) {
                    return true;
                }
            }

            return false;
        }

        return true;
    }

    private function assertComponentType(string $type): void
    {
        if (! in_array($type, CustomPageSetting::COMPONENT_TYPES, true)) {
            throw ValidationException::withMessages(['component' => 'Choose a supported component type.']);
        }
    }

    /**
     * @param list<array<string,mixed>> $blocks
     * @param array{action:string,scope:string,before:list<string>,after:list<string>}|null $orderingState
     */
    private function persist(CustomPageSetting $settings, array $blocks, ?array $orderingState = null): bool
    {
        $settings->fill(['blocks' => $blocks]);
        if (! $settings->isDirty()) {
            return false;
        }

        $actor = $this->audit->requireActor();
        $settings->save();
        $sectionId = (int) $settings->getAttribute('site_section_id');
        if ($orderingState !== null) {
            $this->audit->recordOrdering(
                $actor,
                $orderingState['action'],
                'site_section',
                $sectionId,
                $orderingState['scope'],
                $orderingState['before'],
                $orderingState['after'],
            );
        } else {
            $this->audit->record(
                $actor,
                'site_section.updated',
                'site_section',
                $sectionId,
            );
        }

        return true;
    }

    private function assertDirection(string $direction): void
    {
        if (! in_array($direction, ['up', 'down'], true)) {
            throw new InvalidArgumentException('Editorial order direction must be up or down.');
        }
    }
}
