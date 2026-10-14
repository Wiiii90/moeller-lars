<?php

namespace App\Filament\Pages\Concerns;

use App\Domain\Admin\AdminNotifier;
use App\Domain\Content\CustomPageEditorialService;
use App\Filament\Support\Dialogs\AdminDialog;
use App\Filament\Support\AdminRowAction;
use App\Filament\Support\Dialogs\AdminDialogSize;
use Filament\Actions\Action;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

trait CustomPageWorkspaceComponentActions
{

    public function moveComponent(int $index, string $type, string $direction): void
    {
        if (! $this->componentReorderEnabled()) {
            return;
        }

        $changed = app(CustomPageEditorialService::class)->moveBlock($this->settings(), $index, $type, $direction);
        $this->clearSelections();
        $this->reloadWorkspace();

        if ($changed) {
            app(AdminNotifier::class)->notification(
                title: 'Component order updated',
                status: 'success',
            );
        }
    }

    /** @param list<string> $targets */
    public function reorderComponents(array $targets): void
    {
        if (! $this->componentReorderEnabled()) {
            return;
        }

        $sequence = [];
        foreach ($targets as $target) {
            if (! str_contains($target, ':')) {
                throw ValidationException::withMessages(['component' => 'The component sequence is invalid.']);
            }

            [$index, $type] = explode(':', $target, 2);
            if (! ctype_digit($index) || ! array_key_exists($type, self::COMPONENT_LABELS)) {
                throw ValidationException::withMessages(['component' => 'The component sequence is invalid.']);
            }

            $sequence[] = ['index' => (int) $index, 'type' => $type];
        }

        $changed = app(CustomPageEditorialService::class)->reorderBlocks($this->settings(), $sequence);
        $this->clearSelections();
        $this->reloadWorkspace();

        if ($changed) {
            app(AdminNotifier::class)->notification(
                title: 'Component order updated',
                status: 'success',
            );
        }
    }

    public function sortComponent(string $target, int $position): void
    {
        if (! $this->componentReorderEnabled()) {
            return;
        }

        $targets = collect($this->components)
            ->pluck('target')
            ->filter(static fn (mixed $value): bool => is_string($value))
            ->values()
            ->all();

        $from = array_search($target, $targets, true);
        if ($from === false) {
            throw ValidationException::withMessages(['component' => 'The component sequence is invalid.']);
        }

        $moved = $targets[$from];
        array_splice($targets, $from, 1);
        $position = max(0, min($position, count($targets)));
        array_splice($targets, $position, 0, [$moved]);

        $this->reorderComponents($targets);
    }

    public function moveSelected(string $direction): void
    {
        if (! $this->componentReorderEnabled()) {
            return;
        }
        if (! in_array($direction, ['up', 'down'], true)) {
            throw ValidationException::withMessages(['component' => 'The move direction is invalid.']);
        }

        $parents = $this->selectedComponentTargetData();
        if ($parents === []) {
            return;
        }
        $changed = app(CustomPageEditorialService::class)->moveSelectedBlocks($this->settings(), $parents, $direction);
        $count = count($parents);
        $this->clearSelections();
        $this->reloadWorkspace();

        if ($changed) {
            app(AdminNotifier::class)->notification(
                title: 'Selection moved',
                body: $count.' selected '.($count === 1 ? 'component' : 'components').' moved.',
                status: 'success',
            );
        }
    }

    public function moveSelectedComponents(string $direction): void
    {
        $this->moveSelected($direction);
    }

    public function setComponentPublished(int $index, string $type, bool $published): void
    {
        $block = $this->componentAt($index, $type);
        $block['published'] = $published;
        $changed = app(CustomPageEditorialService::class)->updateBlock($this->settings(), $index, $type, $block);
        $this->loadComponentProjection();
        if ($changed) {
            app(AdminNotifier::class)->notification(
                $published ? 'Component published' : 'Component unpublished',
                status: 'success',
            );
        }
    }

    public function publishSelected(): void
    {
        $this->setSelectedPublished(true);
    }

    public function unpublishSelected(): void
    {
        $this->setSelectedPublished(false);
    }

    private function setSelectedPublished(bool $published): void
    {
        $targets = $this->selectedComponentTargetData();
        if ($targets === []) {
            return;
        }
        $changed = DB::transaction(function () use ($targets, $published): bool {
            $changed = false;
            foreach ($targets as $target) {
                $block = $this->componentAt($target['index'], $target['type']);
                if (\App\Models\CustomPageSetting::componentPublished($block) === $published) {
                    continue;
                }
                $block['published'] = $published;
                $changed = app(CustomPageEditorialService::class)->updateBlock(
                    $this->settings(), $target['index'], $target['type'], $block,
                ) || $changed;
            }

            return $changed;
        });
        $this->clearSelections();
        $this->reloadWorkspace();
        if ($changed) {
            app(AdminNotifier::class)->notification(
                title: $published ? 'Selection published' : 'Selection unpublished',
                status: 'success',
            );
        }
    }

    public function addComponentAction(): Action
    {
        $action = Action::make('addComponent')
            ->label('Add component')
            ->fillForm(fn (): array => [
                'type' => 'text',
                'publication_state' => 'published',
                'image_decorative' => false,
                'variant' => 'thin',
                'media_asset_id' => null,
            ])
            ->schema($this->componentEditorSchema())
            ->modalHeading('Add component')
            ->action(function (array $data): void {
                app(CustomPageEditorialService::class)->addBlock($this->settings(), $this->componentPayload($data));

                $this->clearSelections();
                $this->reloadWorkspace();
                app(AdminNotifier::class)->notification(
                    title: 'Component added',
                    status: 'success',
                );
            });

        return AdminDialog::create($action, 'Add component', AdminDialogSize::Large);
    }

    /** Explicit nesting is the only hierarchy-changing drag/drop operation.
     * Standard wire:sort never changes parentage. */
    public function nestComponent(int $sourceIndex, string $type, int $imageIndex): void
    {
        if (! $this->componentReorderEnabled()) {
            return;
        }
        try {
            $changed = app(CustomPageEditorialService::class)->nestBlock(
                $this->settings(), $sourceIndex, $type, $imageIndex,
            );
            if ($changed) {
                app(AdminNotifier::class)->notification(title: 'Component moved into image', status: 'success');
            }
        } catch (ValidationException $exception) {
            app(AdminNotifier::class)->notification(title: 'Cannot nest component', status: 'warning');
        }
        $this->clearSelections();
        $this->reloadWorkspace();
    }

    public function moveImageChild(int $fromImage, int $childIndex, int $toImage): void
    {
        if (! $this->componentReorderEnabled()) {
            return;
        }
        try {
            $changed = app(CustomPageEditorialService::class)->moveImageChild(
                $this->settings(), $fromImage, $childIndex, $toImage,
            );
            $this->clearSelections();
            $this->reloadWorkspace();
            if ($changed) {
                app(AdminNotifier::class)->notification(title: 'Image child moved', status: 'success');
            }
        } catch (ValidationException $exception) {
            app(AdminNotifier::class)->notification(
                title: 'Image child unchanged',
                body: collect($exception->errors())->flatten()->first() ?? 'The selected parent is not allowed.',
                status: 'warning',
            );
            $this->reloadWorkspace();
        }
    }

    public function unnestImageChild(int $imageIndex, int $childIndex): void
    {
        $changed = app(CustomPageEditorialService::class)->unnestImageChild(
            $this->settings(), $imageIndex, $childIndex,
        );
        $this->clearSelections();
        $this->reloadWorkspace();
        if ($changed) {
            app(AdminNotifier::class)->notification(title: 'Component moved to page level', status: 'success');
        }
    }

    public function sortImageChild(int|string $childIndex, int $position, int|string|null $groupId = null): void
    {
        if (! $this->componentReorderEnabled() || ! is_numeric($groupId) || ! is_numeric($childIndex)) {
            return;
        }
        $changed = app(CustomPageEditorialService::class)->sortImageChild(
            $this->settings(), (int) $groupId, (int) $childIndex, $position,
        );
        $this->loadComponentProjection();
        if ($changed) {
            app(AdminNotifier::class)->notification(title: 'Image content order updated', status: 'success');
        }
    }

    public function editComponentAction(): Action
    {
        return $this->componentEditingAction('editComponent', committed: false);
    }

    public function editListComponentAction(): Action
    {
        return $this->componentEditingAction('editListComponent', committed: true);
    }

    private function componentEditingAction(string $actionName, bool $committed): Action
    {
        $action = Action::make($actionName)
            ->label('Edit')
            ->fillForm(fn (array $arguments): array => $this->componentEditorData($this->actionComponent($arguments)))
            ->schema(fn (array $arguments): array => $this->componentEditorSchema(
                onTypeChange: function (mixed $state) use ($arguments): void {
                    $target = is_string($state) ? $state : '';
                    [, $currentType] = $this->actionComponentTarget($arguments);
                    if ($target === $currentType || ! array_key_exists($target, self::COMPONENT_LABELS)) {
                        return;
                    }
                    $this->replaceMountedAction('changeComponentType', [
                        ...$arguments,
                        'targetType' => $target,
                    ]);
                },
                onImageChange: function (mixed $state) use ($arguments): void {
                    [$index, $type] = $this->actionComponentTarget($arguments);
                    if ($type !== 'image') {
                        return;
                    }

                    $id = filter_var($state, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
                    if ($id === false) {
                        throw ValidationException::withMessages(['media_asset_id' => 'Choose an image from Storage.']);
                    }

                    $existing = $this->componentAt($index, 'image');
                    $changed = app(CustomPageEditorialService::class)->updateBlock(
                        $this->settings(),
                        $index,
                        'image',
                        [...$existing, 'media_asset_id' => $id],
                    );
                    if (! $changed) {
                        return;
                    }

                    $this->clearSelections();
                    $this->loadComponentProjection();
                    app(AdminNotifier::class)->notification(
                        title: 'Component saved',
                        status: 'success',
                    );
                },
            ))
            ->modalHeading('Edit component')
            ->extraModalFooterActions(fn (array $arguments): array => $this->componentEditHeaderActions($arguments, $actionName))
            ->action(function (array $data, array $arguments): void {
                [$index, $type] = $this->actionComponentTarget($arguments);
                $existing = $this->actionComponent($arguments);
                if (($data['type'] ?? null) !== $type) {
                    return; // A type change is handled by changeComponentType.
                }

                $adoptValue = $type === 'image' ? ($data['adopt_component_index'] ?? null) : null;
                $adoptIndex = $adoptValue === null || $adoptValue === ''
                    ? null
                    : filter_var($adoptValue, FILTER_VALIDATE_INT, ['options' => ['min_range' => 0]]);
                if ($adoptIndex === false) {
                    throw ValidationException::withMessages(['adopt_component_index' => 'Choose an existing component.']);
                }
                $parentValue = $type !== 'image' ? ($data['parent_image_index'] ?? null) : null;
                $payload = $this->componentPayload($data, $existing);
                if ($parentValue !== null && $parentValue !== '') {
                    $imageIndex = filter_var($parentValue, FILTER_VALIDATE_INT, [
                        'options' => ['min_range' => 0],
                    ]);
                    if ($imageIndex === false) {
                        throw ValidationException::withMessages(['parent_image_index' => 'Choose a decorative Image parent.']);
                    }
                    $changed = app(CustomPageEditorialService::class)->updateAndNestBlock(
                        $this->settings(), $index, $type, $payload, $imageIndex,
                    );
                    $this->clearSelections();
                    $this->reloadWorkspace();
                    if ($changed) {
                        app(AdminNotifier::class)->notification(
                            title: 'Component moved into image', status: 'success',
                        );
                        $newIndex = $index < $imageIndex ? $imageIndex - 1 : $imageIndex;
                        $this->replaceMountedAction('editComponent', [
                            'componentIndex' => $newIndex, 'componentType' => 'image',
                        ]);
                    }
                    return;
                }
                $changed = $adoptIndex === null
                    ? app(CustomPageEditorialService::class)->updateBlock(
                        $this->settings(), $index, $type, $payload,
                    )
                    : app(CustomPageEditorialService::class)->updateImageAndNest(
                        $this->settings(), $index, $payload, $adoptIndex,
                    );

                $this->clearSelections();
                $this->reloadWorkspace();
                if ($changed && $adoptIndex !== null) {
                    $this->replaceMountedAction(
                        'editComponent',
                        ['componentIndex' => $adoptIndex < $index ? $index - 1 : $index,
                            'componentType' => 'image'],
                    );
                }
                if ($changed) {
                    app(AdminNotifier::class)->notification(
                        title: 'Component saved',
                        status: 'success',
                    );
                }
            });

        return $committed
            ? AdminDialog::command($action, 'Apply changes', AdminDialogSize::Large)
            : AdminDialog::edit($action, AdminDialogSize::Large);
    }

    /** @param array<string,mixed> $arguments
     * @return list<Action>
     */
    private function componentEditHeaderActions(array $arguments, string $actionName): array
    {
        [$index, $type] = $this->actionComponentTarget($arguments);
        $block = $this->componentAt($index, $type);
        $count = count($this->settings()->components());
        $target = ['componentIndex' => $index, 'componentType' => $type];
        $actions = [];

        foreach (['up' => AdminRowAction::MoveUp, 'down' => AdminRowAction::MoveDown] as $direction => $role) {
            $canMove = $this->componentReorderEnabled() && ($direction === 'up' ? $index > 0 : $index < $count - 1);
            $actions[] = AdminDialog::headerAction('dialogMove'.ucfirst($direction), $role, function () use ($index, $type, $direction, $actionName): void {
                $this->moveComponent($index, $type, $direction);
                $nextIndex = $index + ($direction === 'up' ? -1 : 1);
                $this->replaceMountedAction($actionName, ['componentIndex' => $nextIndex, 'componentType' => $type]);
            })->disabled(! $canMove);
        }

        $published = \App\Models\CustomPageSetting::componentPublished($block);
        $actions[] = AdminDialog::headerAction(
            'dialogTogglePublication',
            $published ? AdminRowAction::Unpublish : AdminRowAction::Publish,
            function () use ($index, $type, $published, $target, $actionName): void {
                $this->setComponentPublished($index, $type, ! $published);
                $this->replaceMountedAction($actionName, $target);
            },
        );
        $actions[] = AdminDialog::headerAction('dialogDeleteComponent', AdminRowAction::Delete, function () use ($target): void {
            $this->replaceMountedAction('deleteComponent', $target);
        });

        return $actions;
    }

    public function changeComponentTypeAction(): Action
    {
        $description = function (array $arguments): string {
            [, $oldType] = $this->actionComponentTarget($arguments);
            $targetType = $this->actionTargetComponentType($arguments);

            return 'Changing '.(self::COMPONENT_LABELS[$oldType] ?? $oldType)
                .' to '.(self::COMPONENT_LABELS[$targetType] ?? $targetType)
                .' can remove component-specific content that cannot be carried over.';
        };

        $action = Action::make('changeComponentType')
            ->label('Change component type')
            ->action(function (array $arguments): void {
                [$index, $oldType] = $this->actionComponentTarget($arguments);
                $targetType = $this->actionTargetComponentType($arguments);
                $changed = app(CustomPageEditorialService::class)->convertBlock($this->settings(), $index, $oldType, $targetType);
                $this->clearSelections();
                $this->reloadWorkspace();

                if ($changed) {
                    app(AdminNotifier::class)->notification(
                        title: 'Component type updated',
                        status: 'success',
                    );
                }
            });

        return AdminDialog::confirm(
            $action,
            'Change type?',
            $description,
            'Change type',
            condition: fn (array $arguments): bool => $this->componentTypeChangeLosesContent($arguments),
        );
    }

    public function deleteComponentAction(): Action
    {
        $action = Action::make('deleteComponent')
            ->label('Delete')
            ->color('danger')
            ->action(function (array $arguments): void {
                [$index, $type] = $this->actionComponentTarget($arguments);
                app(CustomPageEditorialService::class)->deleteBlock($this->settings(), $index, $type);
                $this->clearSelections();
                $this->reloadWorkspace();
                app(AdminNotifier::class)->notification(
                    title: 'Component deleted',
                    status: 'success',
                );
            });

        return AdminDialog::confirm($action, 'Delete component?', submitLabel: 'Delete', danger: true);
    }

    public function deleteSelectedAction(): Action
    {
        $action = Action::make('deleteSelected')
            ->label('Delete selected')
            ->color('danger')
            ->action(function (): void {
                $parents = $this->selectedComponentTargetData();
                if ($parents === []) {
                    return;
                }
                app(CustomPageEditorialService::class)->deleteBlocks($this->settings(), $parents);
                $count = count($parents);
                $this->clearSelections();
                $this->reloadWorkspace();
                app(AdminNotifier::class)->notification(
                    title: 'Selection deleted',
                    body: $count.' selected '.($count === 1 ? 'item' : 'items').' processed.',
                    status: 'success',
                );
            });

        return AdminDialog::confirm($action, 'Delete items?', submitLabel: 'Delete', danger: true);
    }

    public function deleteSelectedComponentsAction(): Action
    {
        return $this->deleteSelectedAction();
    }
}
