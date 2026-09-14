<?php

namespace App\Filament\Pages\Concerns;

use App\Domain\Content\CustomPageEditorialService;
use App\Filament\Support\Dialogs\AdminDialog;
use App\Filament\Support\Dialogs\AdminDialogSize;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
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
            Notification::make()->title('Component order updated')->success()->send();
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
            if (! is_string($target) || ! str_contains($target, ':')) {
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
            Notification::make()->title('Component order updated')->success()->send();
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
        $children = $this->selectedChildTargetData();
        if ($parents === [] && $children === []) {
            return;
        }

        $changed = DB::transaction(function () use ($parents, $children, $direction): bool {
            $childrenChanged = $this->moveSelectedChildTargets($children, $direction);
            $parentsChanged = $parents !== []
                && app(CustomPageEditorialService::class)->moveSelectedBlocks($this->settings(), $parents, $direction);

            return $childrenChanged || $parentsChanged;
        });

        $count = count($parents) + count($children);
        $this->clearSelections();
        $this->reloadWorkspace();

        if ($changed) {
            Notification::make()
                ->title('Selection moved')
                ->body($count.' selected '.($count === 1 ? 'item' : 'items').' updated in '.($parents !== [] && $children !== [] ? 'their own scopes.' : 'order.'))
                ->success()
                ->send();
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
        app(CustomPageEditorialService::class)->updateBlock($this->settings(), $index, $type, $block);
        $this->loadComponentProjection(refreshCvCount: false);
    }

    public function publishSelected(): void
    {
        $this->setSelectedPublished(true);
    }

    public function unpublishSelected(): void
    {
        $this->setSelectedPublished(false);
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
            ->schema($this->componentEditorSchema(includeTypeSelect: true))
            ->modalHeading('Add component')
            ->action(function (array $data): void {
                DB::transaction(function () use ($data): void {
                    app(CustomPageEditorialService::class)->addBlock($this->settings(), $this->componentPayload($data));
                    if (($data['type'] ?? null) === 'cv_list') {
                        $this->syncCvEntryEditorRows($data['cv_entries'] ?? null);
                    }
                });

                $this->clearSelections();
                $this->reloadWorkspace();
                Notification::make()->title('Component added')->success()->send();
            });

        return AdminDialog::create($action, 'Add component', AdminDialogSize::Large);
    }

    public function editComponentAction(): Action
    {
        $action = Action::make('editComponent')
            ->label('Edit')
            ->fillForm(fn (array $arguments): array => $this->componentEditorData($this->actionComponent($arguments)))
            ->schema($this->componentEditorSchema(includeTypeSelect: false))
            ->modalHeading(function (array $arguments): string {
                $block = $this->actionComponent($arguments);

                return 'Edit '.(self::COMPONENT_LABELS[(string) $block['type']] ?? 'component');
            })
            ->action(function (array $data, array $arguments): void {
                [$index, $type] = $this->actionComponentTarget($arguments);
                $existing = $this->actionComponent($arguments);

                DB::transaction(function () use ($data, $index, $type, $existing): void {
                    app(CustomPageEditorialService::class)->updateBlock(
                        $this->settings(),
                        $index,
                        $type,
                        $this->componentPayload($data, $existing),
                    );
                    if ($type === 'cv_list') {
                        $this->syncCvEntryEditorRows($data['cv_entries'] ?? null);
                    }
                });

                $this->clearSelections();
                $this->reloadWorkspace();
                Notification::make()->title('Component saved')->success()->send();
            });

        return AdminDialog::editCommit($action, 'Save component', AdminDialogSize::Large);
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
                    Notification::make()->title('Component type updated')->success()->send();
                }
            });

        return AdminDialog::confirm(
            $action,
            'Change component type?',
            $description,
            'Change type',
            required: fn (array $arguments): bool => $this->componentTypeChangeLosesContent($arguments),
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
                Notification::make()->title('Component deleted')->success()->send();
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
                $children = $this->selectedChildTargetData();
                if ($parents === [] && $children === []) {
                    return;
                }

                DB::transaction(function () use ($parents, $children): void {
                    $this->deleteSelectedChildTargets($children, $parents);
                    if ($parents !== []) {
                        app(CustomPageEditorialService::class)->deleteBlocks($this->settings(), $parents);
                    }
                });

                $count = count($parents) + count($children);
                $this->clearSelections();
                $this->reloadWorkspace();
                Notification::make()
                    ->title('Selection deleted')
                    ->body($count.' selected '.($count === 1 ? 'item' : 'items').' processed.')
                    ->success()
                    ->send();
            });

        return AdminDialog::confirm($action, 'Delete selected items?', submitLabel: 'Delete', danger: true);
    }

    public function deleteSelectedComponentsAction(): Action
    {
        return $this->deleteSelectedAction();
    }
}
