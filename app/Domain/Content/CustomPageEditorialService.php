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
        'legal_disclaimer',
    ];

    public function __construct(private readonly AdminAuditService $audit) {}

    /** @param array<string, mixed> $block */
    public function addBlock(CustomPageSetting $settings, array $block): bool
    {
        return DB::transaction(function () use ($settings, $block): bool {
            $fresh = $this->locked($settings);
            $blocks = $fresh->components();
            $type = $block['type'] ?? null;
            if ($type === 'legal_disclaimer' && $this->containsType($blocks, 'legal_disclaimer')) {
                throw ValidationException::withMessages(['component' => 'This page already contains a Legal Disclaimer component.']);
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

            $items = $this->listItems($blocks[$index]);
            $items[] = $item;
            $blocks[$index]['items'] = array_values($items);

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

            $items = $this->listItems($blocks[$index]);
            $this->assertListItem($items, $itemIndex);
            $items[$itemIndex] = $item;
            $blocks[$index]['items'] = array_values($items);

            return $this->persist($fresh, $blocks);
        });
    }

    public function setListItemPublished(
        CustomPageSetting $settings,
        int $index,
        string $expectedType,
        int $itemIndex,
        bool $published,
    ): bool {
        return $this->mutateListItems($settings, $index, $expectedType, function (array $items) use ($itemIndex, $published): array {
            $this->assertListItem($items, $itemIndex);
            $items[$itemIndex]['published'] = $published;
            unset($items[$itemIndex]['visible']);

            return $items;
        });
    }

    /** @param list<int> $itemIndices */
    public function setListItemsPublished(
        CustomPageSetting $settings,
        int $index,
        string $expectedType,
        array $itemIndices,
        bool $published,
    ): bool {
        return $this->mutateListItems($settings, $index, $expectedType, function (array $items) use ($itemIndices, $published): array {
            foreach (array_values(array_unique($itemIndices)) as $itemIndex) {
                $this->assertListItem($items, $itemIndex);
                $items[$itemIndex]['published'] = $published;
                unset($items[$itemIndex]['visible']);
            }

            return $items;
        });
    }

    public function moveListItem(
        CustomPageSetting $settings,
        int $index,
        string $expectedType,
        int $itemIndex,
        string $direction,
    ): bool {
        $this->assertDirection($direction);

        return $this->mutateListItems($settings, $index, $expectedType, function (array $items) use ($itemIndex, $direction): array {
            $this->assertListItem($items, $itemIndex);
            $target = $direction === 'up' ? $itemIndex - 1 : $itemIndex + 1;
            if (! array_key_exists($target, $items)) {
                return $items;
            }
            [$items[$itemIndex], $items[$target]] = [$items[$target], $items[$itemIndex]];

            return array_values($items);
        });
    }

    public function sortListItem(
        CustomPageSetting $settings,
        int $index,
        string $expectedType,
        int $itemIndex,
        int $position,
    ): bool {
        return $this->mutateListItems($settings, $index, $expectedType, function (array $items) use ($itemIndex, $position): array {
            $this->assertListItem($items, $itemIndex);
            $moved = $items[$itemIndex];
            array_splice($items, $itemIndex, 1);
            $position = max(0, min($position, count($items)));
            array_splice($items, $position, 0, [$moved]);

            return array_values($items);
        });
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

    /** @param array<string, mixed> $child */
    public function addContactChild(
        CustomPageSetting $settings,
        int $index,
        string $expectedType,
        array $child,
    ): bool {
        return $this->mutateContactChildren($settings, $index, $expectedType, function (array $children) use ($child): array {
            $type = $this->contactChildType($child);
            if ($this->contactChildIndex($children, $type) !== null) {
                throw ValidationException::withMessages(['component' => 'This Contact child already exists.']);
            }
            $children[] = $child;

            return array_values($children);
        });
    }

    /** @param array<string, mixed> $child */
    public function updateContactChild(
        CustomPageSetting $settings,
        int $index,
        string $expectedType,
        string $childType,
        array $child,
    ): bool {
        return $this->mutateContactChildren($settings, $index, $expectedType, function (array $children) use ($childType, $child): array {
            if ($this->contactChildType($child) !== $childType) {
                throw ValidationException::withMessages(['component' => 'The Contact child type changed while it was being edited.']);
            }
            $childIndex = $this->requiredContactChildIndex($children, $childType);
            $children[$childIndex] = $child;

            return array_values($children);
        });
    }

    public function setContactChildPublished(
        CustomPageSetting $settings,
        int $index,
        string $expectedType,
        string $childType,
        bool $published,
    ): bool {
        return $this->setContactChildrenPublished($settings, $index, $expectedType, [$childType], $published);
    }

    /** @param list<string> $childTypes */
    public function setContactChildrenPublished(
        CustomPageSetting $settings,
        int $index,
        string $expectedType,
        array $childTypes,
        bool $published,
    ): bool {
        return $this->mutateContactChildren($settings, $index, $expectedType, function (array $children) use ($childTypes, $published): array {
            foreach (array_values(array_unique($childTypes)) as $childType) {
                $childIndex = $this->requiredContactChildIndex($children, $childType);
                $children[$childIndex]['published'] = $published;
            }

            return array_values($children);
        });
    }

    public function moveContactChild(
        CustomPageSetting $settings,
        int $index,
        string $expectedType,
        string $childType,
        string $direction,
    ): bool {
        $this->assertDirection($direction);

        return $this->mutateContactChildren($settings, $index, $expectedType, function (array $children) use ($childType, $direction): array {
            $childIndex = $this->requiredContactChildIndex($children, $childType);
            $target = $direction === 'up' ? $childIndex - 1 : $childIndex + 1;
            if (! array_key_exists($target, $children)) {
                return $children;
            }
            [$children[$childIndex], $children[$target]] = [$children[$target], $children[$childIndex]];

            return array_values($children);
        });
    }

    public function sortContactChild(
        CustomPageSetting $settings,
        int $index,
        string $expectedType,
        string $childType,
        int $position,
    ): bool {
        return $this->mutateContactChildren($settings, $index, $expectedType, function (array $children) use ($childType, $position): array {
            $childIndex = $this->requiredContactChildIndex($children, $childType);
            $moved = $children[$childIndex];
            array_splice($children, $childIndex, 1);
            $position = max(0, min($position, count($children)));
            array_splice($children, $position, 0, [$moved]);

            return array_values($children);
        });
    }

    public function deleteContactChild(
        CustomPageSetting $settings,
        int $index,
        string $expectedType,
        string $childType,
    ): bool {
        return $this->deleteContactChildren($settings, $index, $expectedType, [$childType]);
    }

    /** @param list<string> $childTypes */
    public function deleteContactChildren(
        CustomPageSetting $settings,
        int $index,
        string $expectedType,
        array $childTypes,
    ): bool {
        return $this->mutateContactChildren($settings, $index, $expectedType, function (array $children) use ($childTypes): array {
            foreach (array_values(array_unique($childTypes)) as $childType) {
                $childIndex = $this->requiredContactChildIndex($children, $childType);
                unset($children[$childIndex]);
                $children = array_values($children);
            }

            return $children;
        });
    }

    /** Legacy-compatible adapter for callers outside the workspace. */
    public function setContactToggle(CustomPageSetting $settings, int $index, string $expectedType, string $field, bool $enabled): bool
    {
        $childType = match ($field) {
            'show_email' => 'public_email',
            'show_form' => 'contact_form',
            default => throw new InvalidArgumentException('Unsupported Contact toggle.'),
        };

        return $this->setContactChildPublished($settings, $index, $expectedType, $childType, $enabled);
    }

    /** Legacy-compatible adapter for callers outside the workspace. */
    public function setContactSocialPlatform(CustomPageSetting $settings, int $index, string $expectedType, string $platform, bool $enabled): bool
    {
        $this->assertAvailableSocialPlatform($platform);

        return $this->mutateContactChildren($settings, $index, $expectedType, function (array $children) use ($platform, $enabled): array {
            $childIndex = $this->requiredContactChildIndex($children, 'social_links');
            $selected = array_values(array_filter(
                is_array($children[$childIndex]['social_platforms'] ?? null) ? $children[$childIndex]['social_platforms'] : [],
                static fn (mixed $value): bool => is_string($value),
            ));

            if ($enabled && ! in_array($platform, $selected, true)) {
                $selected[] = $platform;
            }
            if (! $enabled) {
                $selected = array_values(array_filter($selected, static fn (string $value): bool => $value !== $platform));
            }
            $children[$childIndex]['social_platforms'] = $selected;

            return $children;
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
            if ($key === 'type' || $key === 'published') {
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

    /**
     * @param callable(list<array<string,mixed>>): list<array<string,mixed>> $mutator
     */
    private function mutateListItems(
        CustomPageSetting $settings,
        int $index,
        string $expectedType,
        callable $mutator,
    ): bool {
        return DB::transaction(function () use ($settings, $index, $expectedType, $mutator): bool {
            $fresh = $this->locked($settings);
            $blocks = $fresh->components();
            $this->assertListTarget($blocks, $index, $expectedType);
            $blocks[$index]['items'] = array_values($mutator($this->listItems($blocks[$index])));

            return $this->persist($fresh, $blocks);
        });
    }

    /**
     * @param callable(list<array<string,mixed>>): list<array<string,mixed>> $mutator
     */
    private function mutateContactChildren(
        CustomPageSetting $settings,
        int $index,
        string $expectedType,
        callable $mutator,
    ): bool {
        return DB::transaction(function () use ($settings, $index, $expectedType, $mutator): bool {
            $fresh = $this->locked($settings);
            $blocks = $fresh->components();
            $this->assertContactTarget($blocks, $index, $expectedType);
            $children = $fresh->contactChildren($blocks[$index]);
            $blocks[$index] = [
                'type' => 'contact',
                'published' => CustomPageSetting::componentPublished($blocks[$index]),
                'children' => array_values($mutator($children)),
            ];

            return $this->persist($fresh, $blocks);
        });
    }

    private function locked(CustomPageSetting $settings): CustomPageSetting
    {
        /** @var CustomPageSetting $fresh */
        $fresh = CustomPageSetting::query()->whereKey($settings->getKey())->lockForUpdate()->firstOrFail();

        return $fresh;
    }

    /** @param list<array<string, mixed>> $blocks
     *  @param list<array{index:int,type:string}> $targets
     *  @return list<int>
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

    /** @param list<array<string, mixed>> $blocks */
    private function assertContactTarget(array $blocks, int $index, string $expectedType): void
    {
        $this->assertTarget($blocks, $index, $expectedType);
        if ($expectedType !== 'contact') {
            throw ValidationException::withMessages(['component' => 'Only Contact components contain Contact children.']);
        }
    }

    /** @param array<string, mixed> $block
     *  @return list<array<string,mixed>>
     */
    private function listItems(array $block): array
    {
        return is_array($block['items'] ?? null) ? array_values($block['items']) : [];
    }

    /** @param list<array<string,mixed>> $items */
    private function assertListItem(array $items, int $itemIndex): void
    {
        if ($itemIndex < 0 || ! array_key_exists($itemIndex, $items) || ! is_array($items[$itemIndex])) {
            throw ValidationException::withMessages(['component' => 'This list entry changed. Reload the workspace and try again.']);
        }
    }

    /** @param array<string,mixed> $child */
    private function contactChildType(array $child): string
    {
        $type = $child['type'] ?? null;
        if (! is_string($type) || ! in_array($type, CustomPageSetting::CONTACT_CHILD_TYPES, true)) {
            throw ValidationException::withMessages(['component' => 'Choose a supported Contact child component.']);
        }

        return $type;
    }

    /** @param list<array<string,mixed>> $children */
    private function contactChildIndex(array $children, string $childType): ?int
    {
        foreach ($children as $index => $child) {
            if (is_array($child) && ($child['type'] ?? null) === $childType) {
                return $index;
            }
        }

        return null;
    }

    /** @param list<array<string,mixed>> $children */
    private function requiredContactChildIndex(array $children, string $childType): int
    {
        if (! in_array($childType, CustomPageSetting::CONTACT_CHILD_TYPES, true)) {
            throw ValidationException::withMessages(['component' => 'Choose a supported Contact child component.']);
        }
        $index = $this->contactChildIndex($children, $childType);
        if ($index === null) {
            throw ValidationException::withMessages(['component' => 'This Contact child changed. Reload the workspace and try again.']);
        }

        return $index;
    }

    /** @param array<string, mixed> $block */
    private function convertedBlock(array $block, string $targetType): array
    {
        $title = in_array($block['type'] ?? null, ['text', 'list'], true) && is_string($block['title'] ?? null)
            ? $block['title']
            : null;
        $published = CustomPageSetting::componentPublished($block);

        return match ($targetType) {
            'image' => ['type' => 'image', 'published' => $published, 'media_asset_id' => null, 'image_decorative' => false],
            'cv_list' => ['type' => 'cv_list', 'published' => $published],
            'text' => ['type' => 'text', 'published' => $published, 'title' => $title, 'body' => null],
            'list' => ['type' => 'list', 'published' => $published, 'title' => $title, 'items' => []],
            'divider' => ['type' => 'divider', 'published' => $published, 'variant' => 'thin'],
            'contact' => [
                'type' => 'contact',
                'published' => $published,
                'children' => [
                    ['type' => 'public_email', 'published' => true],
                    ['type' => 'social_links', 'published' => true, 'social_platforms' => array_keys(SocialLinks::options())],
                    ['type' => 'contact_form', 'published' => true, 'form_state' => 'enabled', 'status_text' => null],
                ],
            ],
            'legal_disclaimer' => ['type' => 'legal_disclaimer', 'published' => $published],
        };
    }

    /** @param list<array<string,mixed>> $blocks */
    private function containsType(array $blocks, string $type, ?int $exceptIndex = null): bool
    {
        foreach ($blocks as $index => $block) {
            if ($exceptIndex !== null && $index === $exceptIndex) {
                continue;
            }
            if (is_array($block) && ($block['type'] ?? null) === $type) {
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
            throw new InvalidArgumentException('Editorial order direction must be up or down.');
        }
    }
}
