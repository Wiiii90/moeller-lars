<?php

namespace App\Filament\Pages\Concerns;

use App\Domain\Admin\AdminNotifier;
use App\Domain\Content\CustomPageEditorialService;
use App\Filament\Support\Dialogs\AdminDialog;
use App\Models\CustomPageSetting;
use App\Filament\Support\AdminRowAction;
use App\Filament\Support\Dialogs\AdminDialogSize;
use Filament\Actions\Action;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

trait CustomPageWorkspaceComponentActions
{
    #[\Livewire\Attributes\Locked]
    public ?array $editingComponentOriginal = null;

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

    /** One native Livewire Sortable entry for every hierarchy level.
     * The destination group ID alone determines the new parent. */
    private function placeSortedComponent(string $target, int $position, int|string|null $groupId): void
    {
        if (! $this->componentReorderEnabled()) return;
        $changed = app(CustomPageEditorialService::class)->placeComponent(
            $this->settings(), $target, $position, $groupId,
        );
        $this->clearSelections();
        $this->reloadWorkspace();
        if ($changed) {
            app(AdminNotifier::class)->notification(title: 'Component order updated', status: 'success');
        }
    }

    public function sortComponent(string $target, int $position, int|string|null $groupId = null): void
    {
        $this->placeSortedComponent($target, $position, $groupId ?? 'root');
    }

    public function moveSelected(string $direction): void
    {
        if (! $this->componentReorderEnabled()) return;
        if (! in_array($direction, ['up', 'down'], true)) {
            throw ValidationException::withMessages(['component' => 'Invalid move direction.']);
        }
        $targets = $this->selectedComponentTargetData();
        if ($targets === []) return;
        $parents = array_values(array_filter($targets, static fn (array $t): bool => ! isset($t['child_index'])));
        $children = array_values(array_filter($targets, static fn (array $t): bool => isset($t['child_index'])));
        usort($children, static fn (array $a, array $b): int => $direction === 'up'
            ? [$a['index'], $a['child_index']] <=> [$b['index'], $b['child_index']]
            : [$b['index'], $b['child_index']] <=> [$a['index'], $a['child_index']]);
        $changed = DB::transaction(function () use ($parents, $children, $direction): bool {
            $editor = app(CustomPageEditorialService::class);
            $changed = false;
            // Child locators retain their original Image index until after
            // sibling reordering. Move any selected top-level parents LAST.
            foreach ($children as $t) {
                $index = $t['child_index'];
                $position = $index + ($direction === 'up' ? -1 : 1);
                $blocks = $this->settings()->fresh()->components();
                $siblings = $blocks[$t['index']]['children'] ?? [];
                if ($position < 0 || $position >= count($siblings)) continue;
                $changed = $editor->sortImageChild($this->settings(), $t['index'], $index, $position) || $changed;
            }
            if ($parents !== []) {
                $changed = $editor->moveSelectedBlocks($this->settings(), $parents, $direction) || $changed;
            }
            return $changed;
        });
        $count = count($targets);
        $this->clearSelections();
        $this->reloadWorkspace();
        if ($changed) app(AdminNotifier::class)->notification(
            title: 'Selection moved',
            body: $count.' selected '.($count === 1 ? 'component' : 'components').' moved.',
            status: 'success',
        );
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
                $childIndex = $target['child_index'] ?? null;
                $block = $childIndex === null
                    ? $this->componentAt($target['index'], $target['type'])
                    : $this->imageChildAt($target['index'], $childIndex, $target['type']);
                if (\App\Models\CustomPageSetting::componentPublished($block) === $published) continue;
                $block['published'] = $published;
                $editor = app(CustomPageEditorialService::class);
                $changed = ($childIndex === null
                    ? $editor->updateBlock($this->settings(), $target['index'], $target['type'], $block)
                    : $editor->updateImageChild($this->settings(), $target['index'], $childIndex, $target['type'], $block)
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

    public function sortImageChild(string $target, int $position, int|string|null $groupId = null): void
    {
        $this->placeSortedComponent($target, $position, $groupId);
    }

    /** Child components own the SAME Edit schema, action rail and persisted
     * data as their top-level counterparts. The Image owns only placement. */
    public function editImageChildAction(): Action
    {
        return $this->imageChildEditingAction('editImageChild', committed: true);
    }

    /** Non-List children use the same autosaving Edit lifecycle as root
     * components. List keeps its atomic Apply changes command. */
    public function editImageChildInstantAction(): Action
    {
        return $this->imageChildEditingAction('editImageChildInstant', committed: false);
    }

    private function imageChildEditingAction(string $actionName, bool $committed): Action
    {
        $action = Action::make($actionName)
            ->label('Edit')
            ->fillForm(function (array $arguments): array {
                [$image, $child, $type] = $this->imageChildTarget($arguments);
                $this->editingComponentOriginal = $this->imageChildAt($image, $child, $type);
                return [
                    ...$this->componentEditorData($this->editingComponentOriginal),
                    'parent_image_index' => (string) $image,
                ];
            })
            ->schema(fn (array $arguments): array => $this->componentEditorSchema(
                editingType: $this->imageChildTarget($arguments)[2],
                onTypeChange: function (mixed $state) use ($arguments): void {
                    [, , $type] = $this->imageChildTarget($arguments);
                    if (! is_string($state) || $state === $type) return;
                    $this->replaceMountedAction('changeImageChildType', [
                        ...$arguments, 'targetType' => $state,
                    ]);
                },
                onContactChange: function (string $key, mixed $value) use ($arguments): void {
                    [$image, $child, $type] = $this->imageChildTarget($arguments);
                    if ($type === 'contact') {
                        $this->persistContactEditorField($image, $child, $key, $value);
                    }
                },
            ))
            ->modalHeading('Edit component')
            ->extraModalFooterActions(function (array $arguments) use ($actionName): array {
                [$image, $child, $type] = $this->imageChildTarget($arguments);
                $blocks = $this->componentAt($image, 'image')['children'] ?? [];
                $args = ['imageIndex' => $image, 'childIndex' => $child, 'componentType' => $type];
                $actions = [];
                foreach (['up' => AdminRowAction::MoveUp, 'down' => AdminRowAction::MoveDown] as $dir => $role) {
                    $canMove = $this->componentReorderEnabled() && ($dir === 'up' ? $child > 0 : $child < count($blocks) - 1);
                    $actions[] = AdminDialog::headerAction(
                        'childMove'.ucfirst($dir), $role,
                        function () use ($image, $child, $type, $dir, $actionName): void {
                            $this->moveImageChildSibling($image, $child, $type, $dir);
                            $this->replaceMountedAction($actionName, [
                                'imageIndex' => $image,
                                'childIndex' => $child + ($dir === 'up' ? -1 : 1),
                                'componentType' => $type,
                            ]);
                        },
                    )->disabled(! $canMove);
                }
                $published = \App\Models\CustomPageSetting::componentPublished($blocks[$child]);
                $actions[] = AdminDialog::headerAction(
                    'childPublish', $published ? AdminRowAction::Unpublish : AdminRowAction::Publish,
                    function () use ($image, $child, $type, $published, $args, $actionName): void {
                        $this->setImageChildPublished($image, $child, $type, ! $published);
                        $this->replaceMountedAction($actionName, $args);
                    },
                );
                $actions[] = AdminDialog::headerAction('childDelete', AdminRowAction::Delete,
                    fn () => $this->replaceMountedAction('deleteImageChild', $args));
                return $actions;
            })
            ->action(function (array $data, array $arguments): void {
                [$image, $child, $type] = $this->imageChildTarget($arguments);
                if (($data['type'] ?? null) !== $type) return;
                try {
                    $existing = $this->imageChildAt($image, $child, $type);
                    $updated = $this->componentPayload($data, $existing);
                    $parentValue = array_key_exists('parent_image_index', $data) ? $data['parent_image_index'] : (string) $image;
                    $newParent = ($parentValue === null || $parentValue === '') ? null
                        : filter_var($parentValue, FILTER_VALIDATE_INT, ['options' => ['min_range' => 0]]);
                    if ($newParent === false) {
                        throw ValidationException::withMessages(['parent_image_index' => 'Choose a decorative Image.']);
                    }
                    $changed = DB::transaction(function () use ($image, $child, $type, $updated, $newParent): bool {
                        $editor = app(CustomPageEditorialService::class);
                        $saved = $editor->updateImageChild($this->settings(), $image, $child, $type, $updated, $this->editingComponentOriginal);
                        if ($newParent === null) {
                            return $editor->unnestImageChild($this->settings(), $image, $child) || $saved;
                        }
                        if ($newParent !== $image) {
                            return $editor->moveImageChild($this->settings(), $image, $child, $newParent) || $saved;
                        }
                        return $saved;
                    });
                } catch (ValidationException $exception) {
                    $this->throwComponentEditValidation($exception);
                }
                $this->clearSelections();
                $this->reloadWorkspace();
                if ($changed) {
                    app(AdminNotifier::class)->notification(title: 'Component saved', status: 'success');
                }
            });
        return $committed
            ? AdminDialog::command($action, 'Apply changes', AdminDialogSize::Large)
            : AdminDialog::edit($action, AdminDialogSize::Large);
    }

    public function setImageChildPublished(int $image, int $child, string $type, bool $published): void
    {
        $block = $this->imageChildAt($image, $child, $type);
        $block['published'] = $published;
        $changed = app(CustomPageEditorialService::class)->updateImageChild(
            $this->settings(), $image, $child, $type, $block,
        );
        $this->loadComponentProjection();
        if ($changed) {
            app(AdminNotifier::class)->notification(
                title: $published ? 'Component published' : 'Component unpublished', status: 'success',
            );
        }
    }

    public function moveImageChildSibling(int $image, int $child, string $type, string $direction): void
    {
        if (! $this->componentReorderEnabled()) return;
        $changed = app(CustomPageEditorialService::class)->moveImageChildByDirection(
            $this->settings(), $image, $child, $type, $direction,
        );
        $this->clearSelections();
        $this->reloadWorkspace();
        if ($changed) app(AdminNotifier::class)->notification(title: 'Component order updated', status: 'success');
    }

    public function deleteImageChildAction(): Action
    {
        $action = Action::make('deleteImageChild')
            ->label('Delete')
            ->color('danger')
            ->action(function (array $arguments): void {
                [$image, $child, $type] = $this->imageChildTarget($arguments);
                app(CustomPageEditorialService::class)->deleteImageChild(
                    $this->settings(), $image, $child, $type,
                );
                $this->clearSelections();
                $this->reloadWorkspace();
                app(AdminNotifier::class)->notification(title: 'Component deleted', status: 'success');
            });
        return AdminDialog::confirm($action, 'Delete component?', submitLabel: 'Delete', danger: true);
    }

    public function changeImageChildTypeAction(): Action
    {
        $action = Action::make('changeImageChildType')
            ->label('Change component type')
            ->action(function (array $arguments): void {
                [$image, $child, $type] = $this->imageChildTarget($arguments);
                $target = $this->actionTargetComponentType($arguments);
                $changed = app(CustomPageEditorialService::class)->convertImageChild(
                    $this->settings(), $image, $child, $type, $target,
                );
                $this->clearSelections();
                $this->reloadWorkspace();
                if ($changed) app(AdminNotifier::class)->notification(title: 'Component type updated', status: 'success');
            });
        return AdminDialog::confirm(
            $action, 'Change type?',
            'Changing a component type can remove content that cannot be carried over.',
            'Change type',
        );
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
            ->fillForm(function (array $arguments): array {
                $block = $this->actionComponent($arguments);
                $this->editingComponentOriginal = $block;

                return $this->componentEditorData($block);
            })
            ->schema(fn (array $arguments): array => $this->componentEditorSchema(
                editingType: $this->actionComponentTarget($arguments)[1],
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
                onContactChange: function (string $key, mixed $value) use ($arguments): void {
                    [$index, $type] = $this->actionComponentTarget($arguments);
                    if ($type === 'contact') {
                        $this->persistContactEditorField($index, null, $key, $value);
                    }
                },
                onImageChange: function (mixed $state) use ($arguments): void {
                    [$index, $type] = $this->actionComponentTarget($arguments);
                    if ($type !== 'image') {
                        return;
                    }

                    try {
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
                            $this->editingComponentOriginal,
                        );
                    } catch (ValidationException $exception) {
                        $this->throwComponentEditValidation($exception);
                    }
                    $this->editingComponentOriginal = $this->settings()->components()[$index];
                    if (! $changed) {
                        return;
                    }

                    $this->clearSelections();
                    $this->loadComponentProjection();
                    $this->forceRender();
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

                try {
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
                            $this->settings(), $index, $type, $payload, $imageIndex, $this->editingComponentOriginal,
                        );
                        $this->clearSelections();
                        $this->reloadWorkspace();
                        if ($changed) {
                            app(AdminNotifier::class)->notification(
                                title: 'Component moved into image', status: 'success',
                            );
                            $newIndex = $index < $imageIndex ? $imageIndex - 1 : $imageIndex;
                            $this->replaceMountedAction('editImageChild', [
                                'imageIndex' => $newIndex,
                                'childIndex' => count($this->settings()->components()[$newIndex]['children']) - 1,
                                'componentType' => $type,
                            ]);
                        }
                        return;
                    }
                    $changed = app(CustomPageEditorialService::class)->updateBlock(
                        $this->settings(), $index, $type, $payload, $this->editingComponentOriginal,
                    );
                } catch (ValidationException $exception) {
                    $this->throwComponentEditValidation($exception);
                }
                $this->editingComponentOriginal = $this->settings()->components()[$index];

                $this->clearSelections();
                $this->reloadWorkspace();
                if ($changed) {
                    $this->forceRender();
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

    /**
     * Autosave exactly one Contact field through the existing Custom Page
     * editorial mutation. Sibling settings are rebuilt from the current
     * persisted block, never overwritten by a stale modal snapshot.
     */
    private function persistContactEditorField(int $index, ?int $childIndex, string $key, mixed $value): void
    {
        $existing = $childIndex === null
            ? $this->componentAt($index, 'contact')
            : $this->imageChildAt($index, $childIndex, 'contact');
        $data = $this->componentEditorData($existing);
        if (str_starts_with($key, 'contact_social_selected.')) {
            $platform = substr($key, strlen('contact_social_selected.'));
            if (! array_key_exists($platform, $this->availableSocialPlatforms)) return;
            $data['contact_social_selected'][$platform] = (bool) $value;
        } elseif (in_array($key, [
            'title', 'contact_email_enabled', 'contact_social_enabled',
            'contact_form_enabled', 'contact_form_state', 'contact_status_text',
        ], true)) {
            $data[$key] = $value;
        } else {
            throw new \InvalidArgumentException('Unknown Contact field.');
        }

        try {
            $payload = $this->contactComponentPayload(
                $data, CustomPageSetting::componentPublished($existing), $existing,
            );
            $editor = app(CustomPageEditorialService::class);
            $changed = $childIndex === null
                ? $editor->updateBlock($this->settings(), $index, 'contact', $payload, $this->editingComponentOriginal)
                : $editor->updateImageChild($this->settings(), $index, $childIndex, 'contact', $payload, $this->editingComponentOriginal);
        } catch (ValidationException $exception) {
            $this->throwComponentEditValidation($exception);
        }
        $this->editingComponentOriginal = $childIndex === null
            ? $this->componentAt($index, 'contact')
            : $this->imageChildAt($index, $childIndex, 'contact');
        if (! $changed) return;
        $this->loadComponentProjection();
        $this->forceRender();
        app(AdminNotifier::class)->notification(title: 'Component saved', status: 'success');
    }

    private function throwComponentEditValidation(ValidationException $exception): never
    {
        $path = $this->getMountedActionSchema()->getStatePath();
        $errors = [];
        foreach ($exception->errors() as $field => $messages) {
            $errors[$path.'.'.($field === 'component' ? 'type' : $field)] = $messages;
        }
        throw ValidationException::withMessages($errors);
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
                DB::transaction(function () use ($parents): void {
                $editor = app(CustomPageEditorialService::class);
                $topLevel = array_values(array_filter($parents, static fn (array $t): bool => ! isset($t['child_index'])));
                $nested = array_values(array_filter($parents, static fn (array $t): bool => isset($t['child_index'])));
                usort($nested, static fn (array $a, array $b): int => [$b['index'], $b['child_index']] <=> [$a['index'], $a['child_index']]);
                $selectedParentIndices = array_column($topLevel, 'index');
                foreach ($nested as $t) {
                    if (in_array($t['index'], $selectedParentIndices, true)) continue;
                    $editor->deleteImageChild($this->settings(), $t['index'], $t['child_index'], $t['type']);
                }
                if ($topLevel !== []) $editor->deleteBlocks($this->settings(), $topLevel);
                });
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
