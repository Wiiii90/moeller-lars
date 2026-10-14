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
            [$index, $type] = explode(':', $target, 2);
            if (! ctype_digit($index) || ! array_key_exists($type, self::COMPONENT_LABELS)) {
                continue;
            }
            $targets[] = ['index' => (int) $index, 'type' => $type];
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
