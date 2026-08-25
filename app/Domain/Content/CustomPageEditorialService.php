<?php

namespace App\Domain\Content;

use App\Domain\Admin\AdminAuditService;
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
            $blocks[] = $block;

            return $this->persist($fresh, $blocks);
        });
    }

    /** @param array<string, mixed> $block */
    public function updateBlock(
        CustomPageSetting $settings,
        int $index,
        string $expectedType,
        array $block,
    ): bool {
        return DB::transaction(function () use ($settings, $index, $expectedType, $block): bool {
            $fresh = $this->locked($settings);
            $blocks = $fresh->components();
            $this->assertTarget($blocks, $index, $expectedType);

            if (($block['type'] ?? null) !== $expectedType) {
                throw ValidationException::withMessages([
                    'component' => 'The component type changed while it was being edited.',
                ]);
            }

            $blocks[$index] = $block;

            return $this->persist($fresh, $blocks);
        });
    }

    public function moveBlock(
        CustomPageSetting $settings,
        int $index,
        string $expectedType,
        string $direction,
    ): bool {
        $this->assertDirection($direction);

        return DB::transaction(function () use ($settings, $index, $expectedType, $direction): bool {
            $fresh = $this->locked($settings);
            $blocks = $fresh->components();
            $this->assertTarget($blocks, $index, $expectedType);

            $target = $direction === 'up' ? $index - 1 : $index + 1;
            if (! array_key_exists($target, $blocks)) {
                return false;
            }

            [$blocks[$index], $blocks[$target]] = [$blocks[$target], $blocks[$index]];

            return $this->persist($fresh, array_values($blocks));
        });
    }

    public function deleteBlock(
        CustomPageSetting $settings,
        int $index,
        string $expectedType,
    ): bool {
        return DB::transaction(function () use ($settings, $index, $expectedType): bool {
            $fresh = $this->locked($settings);
            $blocks = $fresh->components();
            $this->assertTarget($blocks, $index, $expectedType);
            unset($blocks[$index]);

            return $this->persist($fresh, array_values($blocks));
        });
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
    public function moveSelectedBlocks(
        CustomPageSetting $settings,
        array $targets,
        string $direction,
    ): bool {
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
                $sequence[] = [
                    'block' => $block,
                    'selected' => isset($selected[$index]),
                ];
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

            $next = array_map(
                static fn (array $item): array => $item['block'],
                $sequence,
            );

            return $this->persist($fresh, $next);
        });
    }

    private function locked(CustomPageSetting $settings): CustomPageSetting
    {
        /** @var CustomPageSetting $fresh */
        $fresh = CustomPageSetting::query()
            ->whereKey($settings->getKey())
            ->lockForUpdate()
            ->firstOrFail();

        return $fresh;
    }

    /**
     * @param list<array<string, mixed>> $blocks
     * @param list<array{index:int,type:string}> $targets
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

        $indices = array_values(array_unique($indices));
        sort($indices);

        return $indices;
    }

    /** @param list<array<string, mixed>> $blocks */
    private function assertTarget(array $blocks, int $index, string $expectedType): void
    {
        $block = $blocks[$index] ?? null;
        if (! is_array($block) || ($block['type'] ?? null) !== $expectedType) {
            throw ValidationException::withMessages([
                'component' => 'This component changed. Reload the workspace and try again.',
            ]);
        }
    }

    /** @param list<array<string, mixed>> $blocks */
    private function persist(CustomPageSetting $settings, array $blocks): bool
    {
        $settings->fill(['blocks' => array_values($blocks)]);
        if (! $settings->isDirty()) {
            return false;
        }

        $actor = $this->audit->requireActor();
        $settings->save();
        $this->audit->record(
            $actor,
            'site_section.updated',
            'site_section',
            (int) $settings->getAttribute('site_section_id'),
        );

        return true;
    }

    private function assertDirection(string $direction): void
    {
        if (! in_array($direction, ['up', 'down'], true)) {
            throw new InvalidArgumentException('Component direction must be up or down.');
        }
    }
}
