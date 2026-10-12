<?php

namespace App\Filament\Pages\Concerns;

use Illuminate\Validation\ValidationException;

trait CustomPageWorkspaceTargetHelpers
{
    /** @return list<array{index:int,type:string}> */
    private function selectedComponentTargetData(): array
    {
        $targets = [];
        foreach (array_values(array_unique($this->selectedComponentTargets)) as $target) {
            if (! str_contains($target, ':')) {
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
            $parts = explode(':', $target);
            if ($parts[0] === 'contact' && isset($parts[1], $parts[2]) && ctype_digit($parts[1]) && array_key_exists($parts[2], self::CONTACT_CHILD_LABELS)) {
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

    /** @return array<string, mixed> */
    private function actionComponent(array $arguments): array
    {
        [$index, $type] = $this->actionComponentTarget($arguments);

        return $this->componentAt($index, $type);
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
            if (($child['type'] ?? null) === $childType) {
                return $child;
            }
        }

        throw ValidationException::withMessages(['component' => 'This Contact child changed. Reload and try again.']);
    }
}
