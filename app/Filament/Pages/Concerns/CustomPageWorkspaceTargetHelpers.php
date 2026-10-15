<?php

namespace App\Filament\Pages\Concerns;

use App\Domain\Content\CustomPageEditorialService;
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
            [$position, $type] = explode(':', $target, 2);
            if (! array_key_exists($type, self::COMPONENT_LABELS)) continue;
            if (preg_match('/^(\\d+)\\.(\\d+)$/', $position, $matches)) {
                $targets[] = [
                    'index' => (int) $matches[1], 'child_index' => (int) $matches[2],
                    'type' => $type,
                ];
                continue;
            }
            if (ctype_digit($position)) {
                $targets[] = ['index' => (int) $position, 'type' => $type];
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

    /**
     * Read the persisted component at its absolute sequence index. The type
     * is part of the action target: reject a row that was reordered or
     * converted since the action was rendered.
     *
     * @return array<string, mixed>
     */
    private function componentAt(int $index, string $expectedType): array
    {
        $block = $this->settings()->components()[$index] ?? null;
        if (! is_array($block) || ($block['type'] ?? null) !== $expectedType) {
            throw ValidationException::withMessages([
                'component' => 'This component changed. Reload the workspace and try again.',
            ]);
        }

        return $block;
    }

    /** Stable child target: owning Image, child position and expected type. */
    private function imageChildTarget(array $arguments): array
    {
        $image = filter_var($arguments['imageIndex'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 0]]);
        $child = filter_var($arguments['childIndex'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 0]]);
        $type = $arguments['componentType'] ?? null;
        if ($image === false || $child === false || ! is_string($type)
            || ! in_array($type, \App\Models\CustomPageSetting::IMAGE_CHILD_TYPES, true)) {
            throw ValidationException::withMessages(['component' => 'The child component is invalid.']);
        }
        return [(int) $image, (int) $child, $type];
    }

    private function imageChildAt(int $image, int $child, string $type): array
    {
        $item = $this->componentAt($image, 'image')['children'][$child] ?? null;
        if (! is_array($item) || ($item['type'] ?? null) !== $type) {
            throw ValidationException::withMessages(['component' => 'This child component changed. Reload and try again.']);
        }
        return $item;
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

}
