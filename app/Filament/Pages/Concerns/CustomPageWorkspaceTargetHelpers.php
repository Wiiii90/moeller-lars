<?php

namespace App\Filament\Pages\Concerns;

use App\Domain\Content\CustomPageEditorialService;
use App\Models\CvEntry;
use Illuminate\Validation\ValidationException;

trait CustomPageWorkspaceTargetHelpers
{
    /** @return list<array{index:int,type:string}> */
    private function selectedComponentTargetData(): array
    {
        $targets = [];
        foreach (array_values(array_unique($this->selectedComponentTargets)) as $target) {
            if (! is_string($target) || ! str_contains($target, ':')) {
                continue;
            }
            [$index, $type] = explode(':', $target, 2);
            if (! ctype_digit($index) || ! array_key_exists($type, self::COMPONENT_LABELS)) {
                continue;
            }
            $targets[] = ['index' => (int) $index, 'type' => $type];
        }

        return $targets;
    }

    /** @return list<array<string,mixed>> */
    private function selectedChildTargetData(): array
    {
        $targets = [];
        foreach (array_values(array_unique($this->selectedChildTargets)) as $target) {
            if (! is_string($target)) {
                continue;
            }
            $parts = explode(':', $target);
            if (($parts[0] ?? null) === 'cv' && isset($parts[1]) && ctype_digit($parts[1])) {
                $targets[] = ['kind' => 'cv', 'entry_id' => (int) $parts[1]];
            } elseif (($parts[0] ?? null) === 'list' && isset($parts[1], $parts[2]) && ctype_digit($parts[1]) && ctype_digit($parts[2])) {
                $targets[] = ['kind' => 'list', 'component_index' => (int) $parts[1], 'item_index' => (int) $parts[2]];
            } elseif (($parts[0] ?? null) === 'contact' && isset($parts[1], $parts[2]) && ctype_digit($parts[1]) && array_key_exists($parts[2], self::CONTACT_CHILD_LABELS)) {
                $targets[] = ['kind' => 'contact', 'component_index' => (int) $parts[1], 'child_type' => $parts[2]];
            }
        }

        return $targets;
    }

    /** @return array{0:int,1:string} */
    private function actionComponentTarget(array $arguments): array
    {
        $index = $arguments['componentIndex'] ?? null;
        $type = $arguments['componentType'] ?? null;
        if (! is_numeric($index) || ! is_string($type) || ! array_key_exists($type, self::COMPONENT_LABELS)) {
            throw ValidationException::withMessages(['component' => 'The selected component is invalid.']);
        }

        return [(int) $index, $type];
    }

    private function actionTargetComponentType(array $arguments): string
    {
        $type = $arguments['targetType'] ?? null;
        if (! is_string($type) || ! array_key_exists($type, self::COMPONENT_LABELS)) {
            throw ValidationException::withMessages(['component' => 'Choose a supported component type.']);
        }

        return $type;
    }

    private function componentTypeChangeLosesContent(array $arguments): bool
    {
        return app(CustomPageEditorialService::class)->conversionLosesContent(
            $this->actionComponent($arguments),
            $this->actionTargetComponentType($arguments),
        );
    }

    /** @return array<string, mixed> */
    private function actionComponent(array $arguments): array
    {
        [$index, $type] = $this->actionComponentTarget($arguments);

        return $this->componentAt($index, $type);
    }

    /** @return array<string,mixed> */
    private function componentAt(int $index, string $type): array
    {
        $block = $this->settings()->components()[$index] ?? null;
        if (! is_array($block) || ($block['type'] ?? null) !== $type) {
            throw ValidationException::withMessages(['component' => 'This component changed. Reload and try again.']);
        }

        return $block;
    }

    private function actionListItemIndex(array $arguments): int
    {
        $itemIndex = $arguments['itemIndex'] ?? null;
        if (! is_numeric($itemIndex) || (int) $itemIndex < 0) {
            throw ValidationException::withMessages(['component' => 'The selected list entry is invalid.']);
        }

        return (int) $itemIndex;
    }

    /** @return array<string, mixed> */
    private function actionListItem(array $arguments): array
    {
        $block = $this->actionComponent($arguments);
        if (($block['type'] ?? null) !== 'list') {
            throw ValidationException::withMessages(['component' => 'The selected component is not a List.']);
        }
        $itemIndex = $this->actionListItemIndex($arguments);
        $items = is_array($block['items'] ?? null) ? array_values($block['items']) : [];
        $item = $items[$itemIndex] ?? null;
        if (! is_array($item)) {
            throw ValidationException::withMessages(['component' => 'This list entry changed. Reload and try again.']);
        }

        return $item;
    }

    private function actionContactChildType(array $arguments): string
    {
        $childType = $arguments['childType'] ?? null;
        if (! is_string($childType) || ! array_key_exists($childType, self::CONTACT_CHILD_LABELS)) {
            throw ValidationException::withMessages(['component' => 'The selected Contact child is invalid.']);
        }

        return $childType;
    }

    /** @return array<string,mixed> */
    private function actionContactChild(array $arguments): array
    {
        $block = $this->actionComponent($arguments);
        if (($block['type'] ?? null) !== 'contact') {
            throw ValidationException::withMessages(['component' => 'The selected component is not Contact.']);
        }
        $childType = $this->actionContactChildType($arguments);
        foreach ($this->settings()->contactChildren($block) as $child) {
            if (is_array($child) && ($child['type'] ?? null) === $childType) {
                return $child;
            }
        }

        throw ValidationException::withMessages(['component' => 'This Contact child changed. Reload and try again.']);
    }

    private function actionCvEntry(array $arguments): CvEntry
    {
        $id = $arguments['entry'] ?? null;
        if (! is_numeric($id)) {
            throw ValidationException::withMessages(['entry' => 'The selected CV entry is invalid.']);
        }

        /** @var CvEntry $entry */
        $entry = CvEntry::query()->findOrFail((int) $id);

        return $entry;
    }
}
