<?php

namespace App\Filament\Pages\Concerns;

use App\Domain\Content\CustomPageEditorialService;
use App\Models\CustomPageSetting;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Support\Enums\Width;

trait CustomPageWorkspaceListContactActions
{
    public function addListEntryAction(): Action
    {
        return Action::make('addListEntry')
            ->label('Add list entry')
            ->fillForm(fn (): array => ['publication_state' => 'published'])
            ->schema($this->listEntrySchema())
            ->modalHeading('Add list entry')
            ->modalSubmitAction(fn (Action $action): Action => $action->label('Add entry')->extraAttributes(['class' => 'custom-page-dialog__primary']))
            ->modalCancelAction(fn (Action $action): Action => $action->label('Cancel')->extraAttributes(['class' => 'custom-page-dialog__cancel']))
            ->modalWidth(Width::SevenExtraLarge)
            ->extraModalWindowAttributes(['class' => 'custom-page-dialog'])
            ->action(function (array $data, array $arguments): void {
                [$index, $type] = $this->actionComponentTarget($arguments);
                app(CustomPageEditorialService::class)->addListItem($this->settings(), $index, $type, $this->listItemPayload($data));
                $this->clearSelections();
                $this->loadComponentProjection(refreshCvCount: false);
                Notification::make()->title('List entry added')->success()->send();
            });
    }

    public function editListEntryAction(): Action
    {
        return Action::make('editListEntry')
            ->label('Edit')
            ->fillForm(function (array $arguments): array {
                $item = $this->actionListItem($arguments);

                return [...$item, 'publication_state' => CustomPageSetting::listItemPublished($item) ? 'published' : 'unpublished'];
            })
            ->schema($this->listEntrySchema())
            ->modalHeading(fn (array $arguments): string => 'Edit '.(string) ($this->actionListItem($arguments)['title'] ?? 'list entry'))
            ->modalSubmitAction(fn (Action $action): Action => $action->label('Save')->extraAttributes(['class' => 'custom-page-dialog__primary']))
            ->modalCancelAction(fn (Action $action): Action => $action->label('Cancel')->extraAttributes(['class' => 'custom-page-dialog__cancel']))
            ->modalWidth(Width::SevenExtraLarge)
            ->extraModalWindowAttributes(['class' => 'custom-page-dialog'])
            ->action(function (array $data, array $arguments): void {
                [$index, $type] = $this->actionComponentTarget($arguments);
                $itemIndex = $this->actionListItemIndex($arguments);
                app(CustomPageEditorialService::class)->updateListItem(
                    $this->settings(),
                    $index,
                    $type,
                    $itemIndex,
                    $this->listItemPayload($data),
                );
                $this->clearSelections();
                $this->loadComponentProjection(refreshCvCount: false);
                Notification::make()->title('List entry saved')->success()->send();
            });
    }

    public function setListEntryPublished(int $componentIndex, string $componentType, int $itemIndex, bool $published): void
    {
        app(CustomPageEditorialService::class)->setListItemPublished(
            $this->settings(),
            $componentIndex,
            $componentType,
            $itemIndex,
            $published,
        );
        $this->loadComponentProjection(refreshCvCount: false);
    }

    public function moveListEntry(int $componentIndex, string $componentType, int $itemIndex, string $direction): void
    {
        if (! $this->componentReorderEnabled()) {
            return;
        }
        app(CustomPageEditorialService::class)->moveListItem(
            $this->settings(),
            $componentIndex,
            $componentType,
            $itemIndex,
            $direction,
        );
        $this->clearSelections();
        $this->loadComponentProjection(refreshCvCount: false);
    }

    public function deleteListEntryAction(): Action
    {
        return Action::make('deleteListEntry')
            ->label('Delete')
            ->color('danger')
            ->requiresConfirmation()
            ->modalHeading('Delete list entry?')
            ->modalSubmitAction(fn (Action $action): Action => $action->label('Delete')->extraAttributes(['class' => 'custom-page-dialog__primary']))
            ->modalCancelAction(fn (Action $action): Action => $action->label('Cancel')->extraAttributes(['class' => 'custom-page-dialog__cancel']))
            ->modalWidth(Width::Large)
            ->extraModalWindowAttributes(['class' => 'custom-page-dialog'])
            ->action(function (array $arguments): void {
                [$index, $type] = $this->actionComponentTarget($arguments);
                $itemIndex = $this->actionListItemIndex($arguments);
                app(CustomPageEditorialService::class)->deleteListItem($this->settings(), $index, $type, $itemIndex);
                $this->clearSelections();
                $this->loadComponentProjection(refreshCvCount: false);
                Notification::make()->title('List entry deleted')->success()->send();
            });
    }

    public function addContactChildAction(): Action
    {
        return Action::make('addContactChild')
            ->label('Add contact item')
            ->fillForm(fn (): array => [
                'child_type' => 'public_email',
                'publication_state' => 'published',
                'social_platforms' => array_keys($this->availableSocialPlatforms),
                'form_state' => 'enabled',
                'status_text' => null,
            ])
            ->schema(fn (array $arguments): array => $this->contactChildEditorSchema(null, includeTypeSelect: true, arguments: $arguments))
            ->modalHeading('Add contact item')
            ->modalSubmitActionLabel('Add item')
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
                Notification::make()->title('Contact item added')->success()->send();
            });
    }

    public function editContactChildAction(): Action
    {
        return Action::make('editContactChild')
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
            ->modalHeading(fn (array $arguments): string => 'Edit '.(self::CONTACT_CHILD_LABELS[$this->actionContactChildType($arguments)] ?? 'Contact item'))
            ->modalSubmitActionLabel('Save')
            ->action(function (array $data, array $arguments): void {
                [$index, $type] = $this->actionComponentTarget($arguments);
                $childType = $this->actionContactChildType($arguments);
                app(CustomPageEditorialService::class)->updateContactChild(
                    $this->settings(),
                    $index,
                    $type,
                    $childType,
                    $this->contactChildPayload([...$data, 'child_type' => $childType]),
                );
                $this->clearSelections();
                $this->loadComponentProjection(refreshCvCount: false);
                Notification::make()->title('Contact item saved')->success()->send();
            });
    }

    public function setContactChildPublished(int $index, string $type, string $childType, bool $published): void
    {
        app(CustomPageEditorialService::class)->setContactChildPublished(
            $this->settings(),
            $index,
            $type,
            $childType,
            $published,
        );
        $this->loadComponentProjection(refreshCvCount: false);
    }

    public function moveContactChild(int $index, string $type, string $childType, string $direction): void
    {
        if (! $this->componentReorderEnabled()) {
            return;
        }
        app(CustomPageEditorialService::class)->moveContactChild(
            $this->settings(),
            $index,
            $type,
            $childType,
            $direction,
        );
        $this->clearSelections();
        $this->loadComponentProjection(refreshCvCount: false);
    }

    public function deleteContactChildAction(): Action
    {
        return Action::make('deleteContactChild')
            ->label('Delete')
            ->color('danger')
            ->requiresConfirmation()
            ->modalHeading('Delete contact item?')
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
                Notification::make()->title('Contact item deleted')->success()->send();
            });
    }
}
