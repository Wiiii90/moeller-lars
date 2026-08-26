<?php

namespace App\Domain\Content;

use App\Domain\Admin\AdminAuditService;
use App\Models\CustomPageSetting;
use App\Models\PublicContentSetting;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use InvalidArgumentException;

final class CustomPageEditorialService
{
    /** @var list<string> */
    private const COMPONENT_TYPES = [
        'image',
        'cv_list',
        'text',
        'list',
        'divider',
        'contact',
    ];

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
    public function updateBlock(CustomPageSetting $settings, int $index, string $expectedType, array $block): bool
    {
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

    /** @param array<string, mixed> $item */
    public function addListItem(CustomPageSetting $settings, int $index, string $expectedType, array $item): bool
    {
        return DB::transaction(function () use ($settings, $index, $expectedType, $item): bool {
            $fresh = $this->locked($settings);
            $blocks = $fresh->components();
            $this->assertListTarget($blocks, $index, $expectedType);

            $items = is_array($blocks[$index]['items'] ?? null) ? array_values($blocks[$index]['items']) : [];
            $items[] = $item;
            $blocks[$index]['items'] = $items;

            return $this->persist($fresh, $blocks);
        });
    }

    /** @param array<string, mixed> $item */
    public function updateListItem(CustomPageSetting $settings, int $index, string $expectedType, int $itemIndex, array $item): bool
    {
        return DB::transaction(function () use ($settings, $index, $expectedType, $itemIndex, $item): bool {
            $fresh = $this->locked($settings);
            $blocks = $fresh->components();
            $this->assertListTarget($blocks, $index, $expectedType);

            $items = is_array($blocks[$index]['items'] ?? null) ? array_values($blocks[$index]['items']) : [];
            if (! array_key_exists($itemIndex, $items) || ! is_array($items[$itemIndex])) {
                throw ValidationException::withMessages([
                    'component' => 'This list entry changed. Reload the workspace and try again.',
                ]);
            }

            $items[$itemIndex] = $item;
            $blocks[$index]['items'] = array_values($items);

            return $this->persist($fresh, $blocks);
        });
    }

    public function deleteListItem(CustomPageSetting $settings, int $index, string $expectedType, int $itemIndex): bool
    {
        return DB::transaction(function () use ($settings, $index, $expectedType, $itemIndex): bool {
            $fresh = $this->locked($settings);
            $blocks = $fresh->components();
            $this->assertListTarget($blocks, $index, $expectedType);

            $items = is_array($blocks[$index]['items'] ?? null) ? array_values($blocks[$index]['items']) : [];
            if (! array_key_exists($itemIndex, $items) || ! is_array($items[$itemIndex])) {
                throw ValidationException::withMessages([
                    'component' => 'This list entry changed. Reload the workspace and try again.',
                ]);
            }

            unset($items[$itemIndex]);
            $blocks[$index]['items'] = array_values($items);

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
            if ($key === 'type') {
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

    public function setContactToggle(CustomPageSetting $settings, int $index, string $expectedType, string $field, bool $enabled): bool
    {
        if (! in_array($field, ['show_email', 'show_form'], true)) {
            throw new InvalidArgumentException('Unsupported Contact toggle.');
        }

        return DB::transaction(function () use ($settings, $index, $expectedType, $field, $enabled): bool {
            $fresh = $this->locked($settings);
            $blocks = $fresh->components();
            $this->assertTarget($blocks, $index, $expectedType);
            if ($expectedType !== 'contact') {
                throw ValidationException::withMessages(['component' => 'Only Contact components support this setting.']);
            }

            $blocks[$index][$field] = $enabled;

            return $this->persist($fresh, $blocks);
        });
    }

    public function setContactSocialPlatform(CustomPageSetting $settings, int $index, string $expectedType, string $platform, bool $enabled): bool
    {
        return DB::transaction(function () use ($settings, $index, $expectedType, $platform, $enabled): bool {
            $this->assertAvailableSocialPlatform($platform);
            $fresh = $this->locked($settings);
            $blocks = $fresh->components();
            $this->assertTarget($blocks, $index, $expectedType);
            if ($expectedType !== 'contact') {
                throw ValidationException::withMessages(['component' => 'Only Contact components support social links.']);
            }

            $selected = array_values(array_filter(
                is_array($blocks[$index]['social_platforms'] ?? null) ? $blocks[$index]['social_platforms'] : [],
                static fn (mixed $value): bool => is_string($value),
            ));

            if ($enabled && ! in_array($platform, $selected, true)) {
                $selected[] = $platform;
            }
            if (! $enabled) {
                $selected = array_values(array_filter($selected, static fn (string $value): bool => $value !== $platform));
            }

            $blocks[$index]['social_platforms'] = $selected;

            return $this->persist($fresh, $blocks);
        });
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

            [$blocks[$index], $blocks[$target]] = [$blocks[$target], $blocks[$index]];

            return $this->persist($fresh, array_values($blocks));
        });
    }

    /** @param list<array{index:int,type:string}> $targets */
    public function reorderBlocks(CustomPageSetting $settings, array $targets): bool
    {
        return DB::transaction(function () use ($settings, $targets): bool {
            $fresh = $this->locked($settings);
            $blocks = $fresh->components();

            if (count($targets) !== count($blocks)) {
                throw ValidationException::withMessages([
                    'component' => 'The component sequence changed. Reload the workspace and try again.',
                ]);
            }

            $indices = $this->validatedIndices($blocks, $targets);
            if (count($indices) !== count($blocks)) {
                throw ValidationException::withMessages(['component' => 'The component sequence is incomplete.']);
            }

            $next = array_map(static fn (array $target): array => $blocks[$target['index']], $targets);

            return $this->persist($fresh, $next);
        });
    }

    public function deleteBlock(CustomPageSetting $settings, int $index, string $expectedType): bool
    {
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

            return $this->persist($fresh, $next);
        });
    }

    private function locked(CustomPageSetting $settings): CustomPageSetting
    {
        /** @var CustomPageSetting $fresh */
        $fresh = CustomPageSetting::query()->whereKey($settings->getKey())->lockForUpdate()->firstOrFail();

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
            if (! is_array($target) || ! is_int($target['index'] ?? null) || ! is_string($target['type'] ?? null)) {
                throw ValidationException::withMessages(['component' => 'The component sequence is invalid.']);
            }

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

    /** @param list<array<string, mixed>> $blocks */
    private function assertTarget(array $blocks, int $index, string $expectedType): void
    {
        $block = $blocks[$index] ?? null;
        if (! is_array($block) || ($block['type'] ?? null) !== $expectedType) {
            throw ValidationException::withMessages(['component' => 'This component changed. Reload the workspace and try again.']);
        }
    }

    /** @param list<array<string, mixed>> $blocks */
    private function assertListTarget(array $blocks, int $index, string $expectedType): void
    {
        $this->assertTarget($blocks, $index, $expectedType);
        if ($expectedType !== 'list') {
            throw ValidationException::withMessages(['component' => 'Only List components contain list entries.']);
        }
    }

    /** @param array<string, mixed> $block */
    private function convertedBlock(array $block, string $targetType): array
    {
        $title = in_array($block['type'] ?? null, ['text', 'list'], true) && is_string($block['title'] ?? null)
            ? $block['title']
            : null;

        return match ($targetType) {
            'image' => ['type' => 'image', 'media_asset_id' => null, 'image_decorative' => false],
            'cv_list' => ['type' => 'cv_list'],
            'text' => ['type' => 'text', 'title' => $title, 'body' => null],
            'list' => ['type' => 'list', 'title' => $title, 'items' => []],
            'divider' => ['type' => 'divider', 'variant' => 'thin'],
            'contact' => [
                'type' => 'contact',
                'form_state' => 'enabled',
                'status_text' => null,
                'show_email' => true,
                'show_form' => true,
                'social_platforms' => array_keys(SocialLinks::options()),
            ],
        };
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
        if (! in_array($type, self::COMPONENT_TYPES, true)) {
            throw ValidationException::withMessages(['component' => 'Choose a supported component type.']);
        }
    }

    private function assertAvailableSocialPlatform(string $platform): void
    {
        if (! SocialLinks::supports($platform)) {
            throw ValidationException::withMessages(['component' => 'Choose a supported social platform.']);
        }

        $available = collect(SocialLinks::visible(PublicContentSetting::general()->getAttribute('social_links')))
            ->contains(static fn (array $link): bool => ($link['platform'] ?? null) === $platform);
        if (! $available) {
            throw ValidationException::withMessages(['component' => 'This social platform is not available from General.']);
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
            throw ValidationException::withMessages(['component' => 'Choose a supported move direction.']);
        }
    }
}
