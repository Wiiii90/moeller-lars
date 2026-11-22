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

            if ($expectedType === 'layout') {
                $blocks = $this->reshapeLayout($blocks, $index, $block);
            } else {
                $blocks[$index] = $block;
            }

            return $this->persist($fresh, $blocks);
        });
    }

    /**
     * Reposition existing areas by their row/column coordinates rather than
     * their flat indices when a layout is resized. Every content reference
     * remains valid, including when several former cells merge.
     *
     * @param list<array<string,mixed>> $blocks
     * @param array<string,mixed> $requested
     * @return list<array<string,mixed>>
     */
    private function reshapeLayout(array $blocks, int $index, array $requested): array
    {
        $original = $blocks[$index];
        $oldColumns = (int) $original['columns'];
        $oldAreas = $original['areas'];
        $newColumns = (int) ($requested['columns'] ?? $oldColumns);
        $newRows = (int) ($requested['rows'] ?? $original['rows']);
        $replacement = CustomPageSetting::layoutBlock(
            CustomPageSetting::componentPublished($requested),
            $newColumns,
            $newRows,
            (string) ($requested['gap'] ?? $original['gap']),
            null,
            (string) ($requested['column_layout'] ?? $original['column_layout'] ?? 'equal'),
            (string) ($requested['layout_mode'] ?? $original['layout_mode'] ?? 'rows'),
        );
        $replacement['id'] = $original['id'];
        $updated = array_replace($requested, $replacement);
        $newAreas = $replacement['areas'];
        $oldRows = (int) $original['rows'];
        $submittedAreas = is_array($requested['areas'] ?? null) ? $requested['areas'] : [];

        // Preserve stable IDs and per-area styling at matching coordinates.
        for ($row = 0; $row < $newRows; $row++) {
            for ($column = 0; $column < $newColumns; $column++) {
                if ($row >= $oldRows || $column >= $oldColumns) {
                    continue;
                }
                $oldIndex = ($row * $oldColumns) + $column;
                $newIndex = ($row * $newColumns) + $column;
                $newAreas[$newIndex] = $oldAreas[$oldIndex];
                foreach ($submittedAreas as $candidate) {
                    if (is_array($candidate) && ($candidate['id'] ?? null) === $oldAreas[$oldIndex]['id']) {
                        $newAreas[$newIndex]['border'] = $candidate['border'] ?? $oldAreas[$oldIndex]['border'];
                        break;
                    }
                }
            }
        }

        $survivingIds = array_fill_keys(array_column($newAreas, 'id'), true);
        $redirects = [];
        foreach ($oldAreas as $oldIndex => $area) {
            if (isset($survivingIds[$area['id']])) {
                continue;
            }
            $oldRow = intdiv($oldIndex, $oldColumns);
            $oldColumn = $oldIndex % $oldColumns;
            $newRow = min($oldRow, $newRows - 1);
            $newColumn = min($oldColumn, $newColumns - 1);
            $target = ($newRow * $newColumns) + $newColumn;
            $redirects[$area['id']] = $newAreas[$target]['id'];
            if (($newAreas[$target]['border'] ?? 'none') === 'none'
                && ($area['border'] ?? 'none') !== 'none') {
                $newAreas[$target]['border'] = $area['border'];
            }
        }

        foreach ($blocks as &$member) {
            $areaId = $member['layout_area_id'] ?? null;
            if (is_string($areaId) && isset($redirects[$areaId])) {
                $member['layout_area_id'] = $redirects[$areaId];
            }
        }
        unset($member);
        $updated['areas'] = $newAreas;
        $blocks[$index] = $updated;

        return $blocks;
    }

    private function assertUnchangedBlock(array $block, ?array $expectedBlock): void
    {
        if ($expectedBlock !== null && AdminOrderingState::fingerprints([$block]) !== AdminOrderingState::fingerprints([$expectedBlock])) {
            throw ValidationException::withMessages(['type' => 'This component changed since the editor was opened. Reopen the editor.']);
        }
    }

    /**
     * Native sortable groups place components at the root or inside Layout
     * areas in the same canonical document used by the Public renderer.
     */
    public function placeLayoutComponent(CustomPageSetting $settings, string $locator, int $position, string $destination): bool
    {
        if (! preg_match('/^(\\d+):([a-z_]+)$/', $locator, $matches)
            || ($destination !== 'root' && ! preg_match('/^[a-z0-9-]{8,64}$/i', $destination))) {
            throw ValidationException::withMessages(['component' => 'Choose a valid layout destination.']);
        }

        return DB::transaction(function () use ($settings, $matches, $position, $destination): bool {
            $fresh = $this->locked($settings);
            $blocks = $fresh->components();
            $index = (int) $matches[1];
            $this->assertTarget($blocks, $index, $matches[2]);
            $areaId = $destination === 'root' ? null : $destination;
            $ownerIndex = null;
            if ($areaId !== null) {
                foreach ($blocks as $candidateIndex => $candidate) {
                    if (($candidate['type'] ?? null) === 'layout'
                        && in_array($areaId, array_column($candidate['areas'] ?? [], 'id'), true)) {
                        $ownerIndex = $candidateIndex;
                        break;
                    }
                }
                if ($ownerIndex === null) {
                    throw ValidationException::withMessages(['component' => 'The destination layout area no longer exists.']);
                }
            }

            $before = AdminOrderingState::fingerprints($blocks);
            $component = $blocks[$index];
            array_splice($blocks, $index, 1);
            if ($areaId === null) {
                unset($component['layout_area_id']);
            } else {
                $component['layout_area_id'] = $areaId;
            }

            $siblings = [];
            foreach ($blocks as $candidateIndex => $candidate) {
                if (($candidate['layout_area_id'] ?? null) === $areaId) {
                    $siblings[] = $candidateIndex;
                }
            }
            $position = max(0, min($position, count($siblings)));
            if ($position < count($siblings)) {
                $insertion = $siblings[$position];
            } elseif ($siblings !== []) {
                $insertion = $siblings[count($siblings) - 1] + 1;
            } elseif ($areaId !== null) {
                // The owner may have moved when the source component was removed.
                $insertion = 0;
                foreach ($blocks as $candidateIndex => $candidate) {
                    if (($candidate['type'] ?? null) === 'layout'
                        && in_array($areaId, array_column($candidate['areas'] ?? [], 'id'), true)) {
                        $insertion = $candidateIndex + 1;
                        break;
                    }
                }
            } else {
                $insertion = count($blocks);
            }
            array_splice($blocks, $insertion, 0, [$component]);

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
            $selected = array_fill_keys($indices, true);
            $remaining = array_values(array_filter(
                $blocks, static fn (int $index): bool => ! isset($selected[$index]),
                ARRAY_FILTER_USE_KEY,
            ));
            foreach ($indices as $index) {
                CustomPageSetting::assertComponentDeletable($blocks[$index], $remaining);
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
