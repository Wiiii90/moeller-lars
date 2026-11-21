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
     * Native sortable root sequence. Area placement is stored on each
     * component and edited through the same canonical component action.
     */
    public function placeComponent(CustomPageSetting $settings, string $locator, int $position, int|string|null $groupId): bool
    {
        if (! preg_match('/^(\\d+):([a-z_]+)$/', $locator, $matches)
            || ($groupId !== null && $groupId !== 'root')) {
            throw ValidationException::withMessages(['component' => 'Invalid component drag target.']);
        }

        return DB::transaction(function () use ($settings, $matches, $position): bool {
            $fresh = $this->locked($settings);
            $blocks = $fresh->components();
            $index = (int) $matches[1];
            $this->assertTarget($blocks, $index, $matches[2]);
            $before = AdminOrderingState::fingerprints($blocks);
            $component = $blocks[$index];
            array_splice($blocks, $index, 1);
            array_splice($blocks, max(0, min($position, count($blocks))), 0, [$component]);

            return $this->persist($fresh, $blocks, [
                'action' => 'site_section.page_components_reordered',
                'scope' => 'page-components:'.(int) $fresh->getAttribute('site_section_id'),
                'before' => $before,
                'after' => AdminOrderingState::fingerprints($blocks),
            ]);
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
