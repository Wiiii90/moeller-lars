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
                throw ValidationException::withMessages(['component' => 'This page already contains a Legal Disclaimer component.']);
            }
            $blocks[] = $block;

            return $this->persist($fresh, $blocks);
        });
    }

    /** @param array<string, mixed> $block */
    public function updateBlock(CustomPageSetting $settings, int $index, string $expectedType, array $block, ?array $expectedBlock = null): bool
    {
        return DB::transaction(function () use ($settings, $index, $expectedType, $block, $expectedBlock): bool {
            $fresh = $this->locked($settings);
            $blocks = $fresh->components();
            $this->assertTarget($blocks, $index, $expectedType);
            $this->assertUnchangedBlock($blocks[$index], $expectedBlock);

            if (($block['type'] ?? null) !== $expectedType) {
                throw ValidationException::withMessages(['component' => 'The component type changed while it was being edited.']);
            }

            $blocks[$index] = $block;

            return $this->persist($fresh, $blocks);
        });
    }

    private function assertUnchangedBlock(array $block, ?array $expectedBlock): void
    {
        if ($expectedBlock !== null && AdminOrderingState::fingerprints([$block]) !== AdminOrderingState::fingerprints([$expectedBlock])) {
            throw ValidationException::withMessages(['type' => 'This component changed since the editor was opened. Reopen the editor.']);
        }
    }

    /**
     * Move an existing prose component under a decorative Image without
     * copying, dropping, or reauthoring its content. The source disappears
     * from the top-level sequence in the same audited transaction.
     *
     * @param  array<string,mixed>  $image
     */
    public function updateImageAndNest(
        CustomPageSetting $settings, int $imageIndex, array $image, int $sourceIndex, ?array $expectedBlock = null,
    ): bool {
        return DB::transaction(function () use ($settings, $imageIndex, $image, $sourceIndex, $expectedBlock): bool {
            $fresh = $this->locked($settings);
            $blocks = $fresh->components();
            $this->assertMediaParent($blocks, $imageIndex);
            $this->assertUnchangedBlock($blocks[$imageIndex], $expectedBlock);
            if (! in_array($image['type'] ?? null, CustomPageSetting::MEDIA_COMPONENT_TYPES, true) || ! ($image['image_decorative'] ?? false)) {
                throw ValidationException::withMessages(['image_children' => 'Only a decorative image can own text.']);
            }
            $source = $blocks[$sourceIndex] ?? null;
            $type = is_array($source) ? ($source['type'] ?? null) : null;
            if ($sourceIndex === $imageIndex || ! in_array($type, CustomPageSetting::IMAGE_CHILD_TYPES, true)) {
                throw ValidationException::withMessages(['image_children' => 'Choose a text-capable page component.']);
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

    /** Persist edited component values together with a Parent change. */
    public function updateAndNestBlock(
        CustomPageSetting $settings, int $sourceIndex, string $type, array $updated, int $imageIndex, ?array $expectedBlock = null,
    ): bool {
        return DB::transaction(function () use ($settings, $sourceIndex, $type, $updated, $imageIndex, $expectedBlock): bool {
            $saved = $this->updateBlock($settings, $sourceIndex, $type, $updated, $expectedBlock);
            $nested = $this->nestBlock($settings, $sourceIndex, $type, $imageIndex);

            return $saved || $nested;
        });
    }

    /** Move an existing top-level prose block into a decorative Image.
     * No copy/delete intermediate state; sibling order is rewritten once. */
    public function nestBlock(CustomPageSetting $settings, int $sourceIndex, string $type, int $imageIndex): bool
    {
        return DB::transaction(function () use ($settings, $sourceIndex, $type, $imageIndex): bool {
            $fresh = $this->locked($settings);
            $blocks = $fresh->components();
            $this->assertTarget($blocks, $sourceIndex, $type);
            $this->assertMediaParent($blocks, $imageIndex);

            return $this->nestExisting($fresh, $blocks, $sourceIndex, $imageIndex);
        });
    }

    /** @param list<array<string,mixed>> $blocks */
    private function nestExisting(CustomPageSetting $settings, array $blocks, int $sourceIndex, int $imageIndex): bool
    {
        $source = $blocks[$sourceIndex];
        $image = $blocks[$imageIndex];
        if (! ($image['image_decorative'] ?? false)
            || $sourceIndex === $imageIndex
            || ! in_array($source['type'] ?? null, CustomPageSetting::IMAGE_CHILD_TYPES, true)) {
            throw ValidationException::withMessages(['component' => 'Only a text-capable component can be moved into a decorative image.']);
        }
        if ($source['type'] === 'contact') {
            foreach ($source['children'] ?? [] as $child) {
                if (is_array($child) && ($child['type'] ?? null) === 'contact_form'
                    && CustomPageSetting::contactChildPublished($child)) {
                    throw ValidationException::withMessages(['component' => 'Contact Form must remain a full-width component.']);
                }
            }
        }
        $children = $image['children'] ?? [];
        $image['children'] = [...$children, $source];
        $blocks[$imageIndex] = $image;
        array_splice($blocks, $sourceIndex, 1);

        return $this->persist($settings, $blocks);
    }

    /** Move an Image child back to the main page immediately after its Image. */
    public function unnestImageChild(CustomPageSetting $settings, int $imageIndex, int $childIndex): bool
    {
        return DB::transaction(function () use ($settings, $imageIndex, $childIndex): bool {
            $fresh = $this->locked($settings);
            $blocks = $fresh->components();
            $this->assertMediaParent($blocks, $imageIndex);
            $children = $blocks[$imageIndex]['children'] ?? [];
            if (! is_array($children) || ! array_key_exists($childIndex, $children)) {
                throw ValidationException::withMessages(['component' => 'Image child changed; reload the page.']);
            }
            $child = $children[$childIndex];
            array_splice($children, $childIndex, 1);
            if ($children === []) {
                unset($blocks[$imageIndex]['children']);
            } else {
                $blocks[$imageIndex]['children'] = $children;
            }
            array_splice($blocks, $imageIndex + 1, 0, [$child]);

            return $this->persist($fresh, $blocks);
        });
    }

    /** Explicitly reparent an existing Image child. Native sorting never
     * performs this operation, and both parents are persisted atomically. */
    public function moveImageChild(CustomPageSetting $settings, int $fromImage, int $childIndex, int $toImage): bool
    {
        if ($fromImage === $toImage) {
            return false;
        }

        return DB::transaction(function () use ($settings, $fromImage, $childIndex, $toImage): bool {
            $fresh = $this->locked($settings);
            $blocks = $fresh->components();
            $this->assertMediaParent($blocks, $fromImage);
            $this->assertMediaParent($blocks, $toImage);
            if (! ($blocks[$toImage]['image_decorative'] ?? false)) {
                throw ValidationException::withMessages(['component' => 'Choose a decorative image parent.']);
            }
            $children = $blocks[$fromImage]['children'] ?? [];
            if (! is_array($children) || ! array_key_exists($childIndex, $children)) {
                throw ValidationException::withMessages(['component' => 'The Image child no longer exists.']);
            }
            $child = $children[$childIndex];
            array_splice($children, $childIndex, 1);
            if ($children === []) {
                unset($blocks[$fromImage]['children']);
            } else {
                $blocks[$fromImage]['children'] = $children;
            }
            $blocks[$toImage]['children'] = [...($blocks[$toImage]['children'] ?? []), $child];

            return $this->persist($fresh, $blocks);
        });
    }

    /** Mutate one nested prose component without ever moving it into the Image
     * editor. The child remains an independently authored page component. */
    public function updateImageChild(CustomPageSetting $settings, int $imageIndex, int $childIndex, string $type, array $updated, ?array $expectedBlock = null): bool
    {
        return DB::transaction(function () use ($settings, $imageIndex, $childIndex, $type, $updated, $expectedBlock): bool {
            $fresh = $this->locked($settings);
            $blocks = $fresh->components();
            $this->assertMediaParent($blocks, $imageIndex);
            $children = $blocks[$imageIndex]['children'] ?? [];
            if (($children[$childIndex]['type'] ?? null) !== $type || ($updated['type'] ?? null) !== $type) {
                throw ValidationException::withMessages(['component' => 'The child component changed. Reload and try again.']);
            }
            $this->assertUnchangedBlock($children[$childIndex], $expectedBlock);
            $children[$childIndex] = $updated;
            $blocks[$imageIndex]['children'] = $children;

            return $this->persist($fresh, $blocks);
        });
    }

    public function deleteImageChild(CustomPageSetting $settings, int $imageIndex, int $childIndex, string $type): bool
    {
        return DB::transaction(function () use ($settings, $imageIndex, $childIndex, $type): bool {
            $fresh = $this->locked($settings);
            $blocks = $fresh->components();
            $this->assertMediaParent($blocks, $imageIndex);
            $children = $blocks[$imageIndex]['children'] ?? [];
            if (($children[$childIndex]['type'] ?? null) !== $type) {
                throw ValidationException::withMessages(['component' => 'The child component changed. Reload and try again.']);
            }
            CustomPageSetting::assertComponentDeletable($children[$childIndex]);
            array_splice($children, $childIndex, 1);
            if ($children === []) {
                unset($blocks[$imageIndex]['children']);
            } else {
                $blocks[$imageIndex]['children'] = $children;
            }

            return $this->persist($fresh, $blocks);
        });
    }

    public function moveImageChildByDirection(CustomPageSetting $settings, int $imageIndex, int $childIndex, string $type, string $direction): bool
    {
        $this->assertDirection($direction);

        return DB::transaction(function () use ($settings, $imageIndex, $childIndex, $type, $direction): bool {
            $fresh = $this->locked($settings);
            $blocks = $fresh->components();
            $this->assertMediaParent($blocks, $imageIndex);
            $children = $blocks[$imageIndex]['children'] ?? [];
            if (($children[$childIndex]['type'] ?? null) !== $type) {
                throw ValidationException::withMessages(['component' => 'The child component changed. Reload and try again.']);
            }
            $other = $childIndex + ($direction === 'up' ? -1 : 1);
            if (! array_key_exists($other, $children)) {
                return false;
            }
            [$children[$childIndex], $children[$other]] = [$children[$other], $children[$childIndex]];
            $blocks[$imageIndex]['children'] = $children;

            return $this->persist($fresh, $blocks);
        });
    }

    public function convertImageChild(CustomPageSetting $settings, int $imageIndex, int $childIndex, string $type, string $targetType): bool
    {
        if (! in_array($targetType, CustomPageSetting::IMAGE_CHILD_TYPES, true)) {
            throw ValidationException::withMessages(['component' => 'Choose a text-capable component.']);
        }

        return DB::transaction(function () use ($settings, $imageIndex, $childIndex, $type, $targetType): bool {
            $fresh = $this->locked($settings);
            $blocks = $fresh->components();
            $this->assertMediaParent($blocks, $imageIndex);
            $children = $blocks[$imageIndex]['children'] ?? [];
            if (($children[$childIndex]['type'] ?? null) !== $type) {
                throw ValidationException::withMessages(['component' => 'The child component changed. Reload and try again.']);
            }
            if ($targetType === $type) {
                return false;
            }
            // The canonical converter owns nested Contact's no-Form rule.
            $converted = EditorialComponentConversion::convert($children[$childIndex], $targetType, insideImage: true);
            $children[$childIndex] = $converted;
            $blocks[$imageIndex]['children'] = $children;

            return $this->persist($fresh, $blocks);
        });
    }

    /**
     * One native Sortable placement contract for both hierarchy levels.
     * Root and Image children are one Livewire sort group; the receiving
     * group's ID is the desired parent. No second JavaScript drag owner.
     * Mutation is locked, validated and persisted atomically.
     */
    public function placeComponent(CustomPageSetting $settings, string $locator, int $position, int|string|null $groupId): bool
    {
        if (! preg_match('/^(\\d+)(?:\\.(\\d+))?:([a-z_]+)$/', $locator, $match)) {
            throw ValidationException::withMessages(['component' => 'Invalid component drag target.']);
        }
        $sourceIndex = (int) $match[1];
        $sourceChild = $match[2] !== '' ? (int) $match[2] : null;
        $expectedType = $match[3];
        $parent = $groupId === null || $groupId === 'root' ? null
            : filter_var($groupId, FILTER_VALIDATE_INT, ['options' => ['min_range' => 0]]);
        if ($parent === false) {
            throw ValidationException::withMessages(['component' => 'Invalid parent for component.']);
        }

        return DB::transaction(function () use ($settings, $sourceIndex, $sourceChild, $expectedType, $parent, $position): bool {
            $fresh = $this->locked($settings);
            $blocks = $fresh->components();
            $beforeOrder = AdminOrderingState::fingerprints($blocks);
            if ($sourceChild === null) {
                $this->assertTarget($blocks, $sourceIndex, $expectedType);
                if ($parent === $sourceIndex) {
                    throw ValidationException::withMessages(['component' => 'A component cannot be its own parent.']);
                }
                $item = $blocks[$sourceIndex];
                array_splice($blocks, $sourceIndex, 1);
                // The target Image moves one index to the left when a
                // preceding top-level sibling is taken out of the array.
                if ($parent !== null && $sourceIndex < $parent) {
                    $parent--;
                }
            } else {
                $this->assertMediaParent($blocks, $sourceIndex);
                $children = $blocks[$sourceIndex]['children'] ?? [];
                if (($children[$sourceChild]['type'] ?? null) !== $expectedType) {
                    throw ValidationException::withMessages(['component' => 'Child component changed during dragging.']);
                }
                $item = $children[$sourceChild];
                array_splice($children, $sourceChild, 1);
                if ($children === []) {
                    unset($blocks[$sourceIndex]['children']);
                } else {
                    $blocks[$sourceIndex]['children'] = $children;
                }
            }

            if ($parent === null) {
                array_splice($blocks, max(0, min($position, count($blocks))), 0, [$item]);
            } else {
                $this->assertMediaParent($blocks, $parent);
                if (! ($blocks[$parent]['image_decorative'] ?? false)
                    || ! in_array($expectedType, CustomPageSetting::IMAGE_CHILD_TYPES, true)
                    ) {
                    throw ValidationException::withMessages(['component' => 'Only supported components can be children of a decorative Image.']);
                }
                if ($expectedType === 'contact') {
                    foreach ($item['children'] ?? [] as $child) {
                        if (is_array($child) && ($child['type'] ?? null) === 'contact_form'
                            && CustomPageSetting::contactChildPublished($child)) {
                            throw ValidationException::withMessages(['component' => 'A Contact Form cannot wrap around an Image.']);
                        }
                    }
                }
                $children = $blocks[$parent]['children'] ?? [];
                array_splice($children, max(0, min($position, count($children))), 0, [$item]);
                $blocks[$parent]['children'] = $children;
            }

            return $this->persist($fresh, $blocks, $sourceChild === null && $parent === null ? [
                'action' => 'site_section.page_components_reordered',
                'scope' => 'page-components:'.(int) $fresh->getAttribute('site_section_id'),
                'before' => $beforeOrder,
                'after' => AdminOrderingState::fingerprints($blocks),
            ] : null);
        });
    }

    /** Reorder children within one Image; never change their parent by sorting. */
    public function sortImageChild(CustomPageSetting $settings, int $imageIndex, int $childIndex, int $position): bool
    {
        return DB::transaction(function () use ($settings, $imageIndex, $childIndex, $position): bool {
            $fresh = $this->locked($settings);
            $blocks = $fresh->components();
            $this->assertMediaParent($blocks, $imageIndex);
            $children = $blocks[$imageIndex]['children'] ?? [];
            if (! array_key_exists($childIndex, $children)) {
                throw ValidationException::withMessages(['component' => 'Image child changed; reload the page.']);
            }
            $item = $children[$childIndex];
            array_splice($children, $childIndex, 1);
            array_splice($children, max(0, min($position, count($children))), 0, [$item]);
            $blocks[$imageIndex]['children'] = $children;

            return $this->persist($fresh, $blocks);
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
                throw ValidationException::withMessages(['component' => 'This page already contains a Legal Disclaimer component.']);
            }
            $blocks[$index] = EditorialComponentConversion::convert($blocks[$index], $targetType);

            return $this->persist($fresh, $blocks);
        });
    }

    /** @param array<string,mixed> $block */
    public function conversionLosesContent(array $block, string $targetType): bool
    {
        return EditorialComponentConversion::losesContent($block, $targetType);
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
            foreach ($indices as $index) {
                CustomPageSetting::assertComponentDeletable($blocks[$index]);
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
    private function assertMediaParent(array $blocks, int $index): void
    {
        $block = $blocks[$index] ?? null;
        if (! is_array($block) || ! in_array($block['type'] ?? null, CustomPageSetting::MEDIA_COMPONENT_TYPES, true)) {
            throw ValidationException::withMessages(['component' => 'This media parent changed. Reload the workspace and try again.']);
        }
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

    private function assertComponentType(string $type): void
    {
        if (! in_array($type, CustomPageSetting::COMPONENT_TYPES, true)) {
            throw ValidationException::withMessages(['component' => 'Choose a supported component type.']);
        }
    }

    /**
     * @param  list<array<string,mixed>>  $blocks
     * @param  array{action:string,scope:string,before:list<string>,after:list<string>}|null  $orderingState
     */
    private function persist(CustomPageSetting $settings, array $blocks, ?array $orderingState = null): bool
    {
        $before = AdminOrderingState::fingerprints($settings->components());
        $settings->fill(['blocks' => $blocks]);
        $settings->setAttribute('blocks', $settings->normalizedBlocks());
        $settings->assertEditableComponents();
        if ($before === AdminOrderingState::fingerprints($settings->components())) {
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
