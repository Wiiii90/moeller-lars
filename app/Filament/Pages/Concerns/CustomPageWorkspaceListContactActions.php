<?php

namespace App\Filament\Pages\Concerns;

use App\Domain\Admin\AdminNotifier;
use App\Domain\Content\CustomPageEditorialService;
use App\Filament\Support\Dialogs\AdminDialog;
use App\Filament\Support\AdminRowAction;
use App\Filament\Support\Dialogs\AdminDialogSize;
use App\Models\CustomPageSetting;
use Filament\Actions\Action;

trait CustomPageWorkspaceListContactActions
{

    public function addListEntryAction(): Action
    {
        $action = Action::make('addListEntry')
            ->label('Add list entry')
            ->fillForm(fn (): array => ['publication_state' => 'published'])
            ->schema($this->listEntrySchema())
            ->modalHeading('Add entry')
            ->action(function (array $data, array $arguments): void {
                [$index, $type] = $this->actionComponentTarget($arguments);
                app(CustomPageEditorialService::class)->addListItem($this->settings(), $index, $type, $this->listItemPayload($data));
                $this->clearSelections();
                $this->loadComponentProjection(refreshCvCount: false);
                app(AdminNotifier::class)->notification(
                    title: 'List entry added',
                    status: 'success',
                );
            });

        return AdminDialog::create($action, 'Add entry', AdminDialogSize::Large);
    }

    public function editListEntryAction(): Action
    {
        $action = Action::make('editListEntry')
            ->label('Edit')
            ->fillForm(function (array $arguments): array {
                $item = $this->actionListItem($arguments);

                return [...$item, 'publication_state' => CustomPageSetting::listItemPublished($item) ? 'published' : 'unpublished'];
            })
            ->schema($this->listEntrySchema(isNew: false))
            ->modalHeading('Edit entry')
            ->extraModalFooterActions(fn (array $arguments): array => $this->listEntryEditHeaderActions($arguments))
            ->action(function (array $data, array $arguments): void {
                [$index, $type] = $this->actionComponentTarget($arguments);
                $itemIndex = $this->actionListItemIndex($arguments);
                $changed = app(CustomPageEditorialService::class)->updateListItem(
                    $this->settings(),
                    $index,
                    $type,
                    $itemIndex,
                    $this->listItemPayload($data),
                );
                $this->clearSelections();
                $this->loadComponentProjection(refreshCvCount: false);
                if ($changed) {
                    app(AdminNotifier::class)->notification(
                        title: 'List entry saved',
                        status: 'success',
                    );
                }
            });

        return AdminDialog::edit($action, AdminDialogSize::Large);
    }

    /** @param array<string,mixed> $arguments
     * @return list<Action>
     */
    private function listEntryEditHeaderActions(array $arguments): array
    {
        [$index, $type] = $this->actionComponentTarget($arguments);
        $itemIndex = $this->actionListItemIndex($arguments);
        $entry = $this->actionListItem($arguments);
        $items = $this->componentAt($index, $type)['items'] ?? [];
        $count = is_array($items) ? count($items) : 0;
        $target = ['componentIndex' => $index, 'componentType' => $type, 'itemIndex' => $itemIndex];
        $actions = [];

        foreach (['up' => AdminRowAction::MoveUp, 'down' => AdminRowAction::MoveDown] as $direction => $role) {
            $canMove = $this->componentReorderEnabled() && ($direction === 'up' ? $itemIndex > 0 : $itemIndex < $count - 1);
            $actions[] = AdminDialog::headerAction('dialogMove'.ucfirst($direction), $role, function () use ($index, $type, $itemIndex, $direction): void {
                $this->moveListEntry($index, $type, $itemIndex, $direction);
                $this->replaceMountedAction('editListEntry', [
                    'componentIndex' => $index, 'componentType' => $type,
                    'itemIndex' => $itemIndex + ($direction === 'up' ? -1 : 1),
                ]);
            })->disabled(! $canMove);
        }

        $published = CustomPageSetting::listItemPublished($entry);
        $actions[] = AdminDialog::headerAction(
            'dialogTogglePublication',
            $published ? AdminRowAction::Unpublish : AdminRowAction::Publish,
            function () use ($index, $type, $itemIndex, $published, $target): void {
                $this->setListEntryPublished($index, $type, $itemIndex, ! $published);
                $this->replaceMountedAction('editListEntry', $target);
            },
        );
        $actions[] = AdminDialog::headerAction('dialogDeleteEntry', AdminRowAction::Delete, function () use ($target): void {
            $this->replaceMountedAction('deleteListEntry', $target);
        });

        return $actions;
    }

    public function setListEntryPublished(int $componentIndex, string $componentType, int $itemIndex, bool $published): void
    {
        $changed = app(CustomPageEditorialService::class)->setListItemPublished(
            $this->settings(),
            $componentIndex,
            $componentType,
            $itemIndex,
            $published,
        );
        $this->loadComponentProjection(refreshCvCount: false);
        if ($changed) {
            app(AdminNotifier::class)->notification(
                $published ? 'List entry published' : 'List entry unpublished',
                status: 'success',
            );
        }
    }

    public function moveListEntry(int $componentIndex, string $componentType, int $itemIndex, string $direction): void
    {
        if (! $this->componentReorderEnabled()) {
            return;
        }
        $changed = app(CustomPageEditorialService::class)->moveListItem(
            $this->settings(),
            $componentIndex,
            $componentType,
            $itemIndex,
            $direction,
        );
        $this->clearSelections();
        $this->loadComponentProjection(refreshCvCount: false);
        if ($changed) {
            app(AdminNotifier::class)->notification('List entry order updated', status: 'success');
        }
    }

    public function deleteListEntryAction(): Action
    {
        $action = Action::make('deleteListEntry')
            ->label('Delete')
            ->color('danger')
            ->action(function (array $arguments): void {
                [$index, $type] = $this->actionComponentTarget($arguments);
                $itemIndex = $this->actionListItemIndex($arguments);
                app(CustomPageEditorialService::class)->deleteListItem($this->settings(), $index, $type, $itemIndex);
                $this->clearSelections();
                $this->loadComponentProjection(refreshCvCount: false);
                app(AdminNotifier::class)->notification(
                    title: 'List entry deleted',
                    status: 'success',
                );
            });

        return AdminDialog::confirm($action, 'Delete entry?', submitLabel: 'Delete', danger: true);
    }

    public function addContactChildAction(): Action
    {
        $action = Action::make('addContactChild')
            ->label('Add contact item')
            ->fillForm(fn (): array => [
                'child_type' => 'public_email',
                'publication_state' => 'published',
                'social_platforms' => array_keys($this->availableSocialPlatforms),
                'form_state' => 'enabled',
                'status_text' => null,
            ])
            ->schema(fn (array $arguments): array => $this->contactChildEditorSchema(null, includeTypeSelect: true, arguments: $arguments))
            ->modalHeading('Add contact')
            ->action(function (array $data, array $arguments): void {
                [$index, $type] = $this->actionComponentTarget($arguments);
                app(CustomPageEditorialService::class)->addContactChild(
                    $this->settings(),
                    $index,
                    $type,
                    $this->contactChildPayload($data),
                );
                $this->clearSelections();
                $this->loadComponentProjection(refreshCvCount: false);
                app(AdminNotifier::class)->notification(
                    title: 'Contact item added',
                    status: 'success',
                );
            });

        return AdminDialog::create($action, 'Add contact', AdminDialogSize::Large);
    }

    public function editContactChildAction(): Action
    {
        $action = Action::make('editContactChild')
            ->label('Edit')
            ->fillForm(function (array $arguments): array {
                $child = $this->actionContactChild($arguments);

                return [
                    ...$child,
                    'child_type' => $child['type'],
                    'publication_state' => CustomPageSetting::contactChildPublished($child) ? 'published' : 'unpublished',
                ];
            })
            ->schema(fn (array $arguments): array => $this->contactChildEditorSchema($this->actionContactChildType($arguments), false, $arguments))
            ->modalHeading('Edit contact')
            ->extraModalFooterActions(fn (array $arguments): array => $this->contactChildEditHeaderActions($arguments))
            ->action(function (array $data, array $arguments): void {
                [$index, $type] = $this->actionComponentTarget($arguments);
                $childType = $this->actionContactChildType($arguments);
                $changed = app(CustomPageEditorialService::class)->updateContactChild(
                    $this->settings(),
                    $index,
                    $type,
                    $childType,
                    $this->contactChildPayload([...$data, 'child_type' => $childType]),
                );
                $this->clearSelections();
                $this->loadComponentProjection(refreshCvCount: false);
                if ($changed) {
                    app(AdminNotifier::class)->notification(
                        title: 'Contact item saved',
                        status: 'success',
                    );
                }
            });

        return AdminDialog::edit($action, AdminDialogSize::Large);
    }

    /** @param array<string,mixed> $arguments
     * @return list<Action>
     */
    private function contactChildEditHeaderActions(array $arguments): array
    {
        [$index, $type] = $this->actionComponentTarget($arguments);
        $childType = $this->actionContactChildType($arguments);
        $child = $this->actionContactChild($arguments);
        $children = $this->settings()->contactChildren($this->componentAt($index, $type));
        $position = array_search($childType, array_column($children, 'type'), true);
        $count = count($children);
        $target = ['componentIndex' => $index, 'componentType' => $type, 'childType' => $childType];
        $actions = [];
        foreach (['up' => AdminRowAction::MoveUp, 'down' => AdminRowAction::MoveDown] as $direction => $role) {
            $canMove = $this->componentReorderEnabled() && $position !== false
                && ($direction === 'up' ? $position > 0 : $position < $count - 1);
            $actions[] = AdminDialog::headerAction('dialogMove'.ucfirst($direction), $role, function () use ($index, $type, $childType, $direction, $target): void {
                $this->moveContactChild($index, $type, $childType, $direction);
                $this->replaceMountedAction('editContactChild', $target);
            })->disabled(! $canMove);
        }

        $published = CustomPageSetting::contactChildPublished($child);
        $actions[] = AdminDialog::headerAction(
            'dialogTogglePublication',
            $published ? AdminRowAction::Unpublish : AdminRowAction::Publish,
            function () use ($index, $type, $childType, $published, $target): void {
                $this->setContactChildPublished($index, $type, $childType, ! $published);
                $this->replaceMountedAction('editContactChild', $target);
            },
        );
        $actions[] = AdminDialog::headerAction('dialogDeleteContact', AdminRowAction::Delete, function () use ($target): void {
            $this->replaceMountedAction('deleteContactChild', $target);
        });

        return $actions;
    }

    public function setContactChildPublished(int $index, string $type, string $childType, bool $published): void
    {
        $changed = app(CustomPageEditorialService::class)->setContactChildPublished(
            $this->settings(),
            $index,
            $type,
            $childType,
            $published,
        );
        $this->loadComponentProjection(refreshCvCount: false);
        if ($changed) {
            app(AdminNotifier::class)->notification(
                $published ? 'Contact item published' : 'Contact item unpublished',
                status: 'success',
            );
        }
    }

    public function moveContactChild(int $index, string $type, string $childType, string $direction): void
    {
        if (! $this->componentReorderEnabled()) {
            return;
        }
        $changed = app(CustomPageEditorialService::class)->moveContactChild(
            $this->settings(),
            $index,
            $type,
            $childType,
            $direction,
        );
        $this->clearSelections();
        $this->loadComponentProjection(refreshCvCount: false);
        if ($changed) {
            app(AdminNotifier::class)->notification('Contact item order updated', status: 'success');
        }
    }

    public function deleteContactChildAction(): Action
    {
        $action = Action::make('deleteContactChild')
            ->label('Delete')
            ->color('danger')
            ->action(function (array $arguments): void {
                [$index, $type] = $this->actionComponentTarget($arguments);
                app(CustomPageEditorialService::class)->deleteContactChild(
                    $this->settings(),
                    $index,
                    $type,
                    $this->actionContactChildType($arguments),
                );
                $this->clearSelections();
                $this->loadComponentProjection(refreshCvCount: false);
                app(AdminNotifier::class)->notification(
                    title: 'Contact item deleted',
                    status: 'success',
                );
            });

        return AdminDialog::confirm($action, 'Delete contact?', submitLabel: 'Delete', danger: true);
    }
}
