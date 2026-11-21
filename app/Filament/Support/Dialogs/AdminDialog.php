<?php

namespace App\Filament\Support\Dialogs;

use App\Filament\Support\AdminIcon;
use App\Filament\Support\AdminRowAction;
use Closure;
use Filament\Actions\Action;
use Filament\Support\Enums\Alignment;
use Filament\Support\Enums\IconPosition;
use Filament\Support\Enums\Width;

final class AdminDialog
{
    public static function create(
        Action $action,
        string $submitLabel,
        AdminDialogSize $size = AdminDialogSize::Small,
        ?Closure $onClear = null,
    ): Action {
        // Every new-object editor uses one visual contract: a Plus opener
        // and a plain Check inside the shared circular header button.
        // No field is persisted when Filament's native X closes this modal.
        return self::committedTask(
            $action->icon(AdminIcon::Add->value)->iconPosition(IconPosition::Before),
            AdminDialogType::Create,
            $submitLabel,
            $size,
            AdminIcon::DialogSubmit,
            $onClear,
        );
    }

    /**
     * Edit dialogs autosave on native change events: text controls commit on
     * blur, while select/toggle controls commit immediately. No timers or
     * hidden submit buttons participate in persistence.
     *
     * Editorial edits use the large workspace width by default. Consumers may
     * deliberately choose Default for medium forms or Small for compact utility
     * editors; explicit canonical size choices are never silently promoted.
     *
     * @param  array<mixed>|Closure  $windowAttributes
     */
    public static function edit(
        Action $action,
        AdminDialogSize $size = AdminDialogSize::Large,
        array|Closure $windowAttributes = [],
    ): Action {
        $action = self::base($action, AdminDialogType::Edit, $size)
            ->modalSubmitAction(false)
            ->modalCancelAction(false)
            // Domain actions own change notifications; Filament owns validation and
            // transactions. Halt only to keep the autosaving Edit modal mounted.
            ->after(static function (Action $action): void {
                $action->halt();
            })
            ->extraModalWindowAttributes([
                'wire:change' => 'callMountedAction',
                'x-on:admin-edit-list-updated.window' => '$el.closest(\'.fi-modal.fi-modal-open\') && $wire.call(\'callMountedAction\')',
            ], merge: true);

        if ($windowAttributes instanceof Closure || $windowAttributes !== []) {
            $action->extraModalWindowAttributes($windowAttributes, merge: true);
        }

        return $action;
    }

    /**
     * Use the same semantic icon, label and danger treatment in row actions
     * and in the Edit/Viewer header. Callers own their domain mutation and
     * whether a destructive action needs a confirmation.
     */
    public static function headerAction(string $name, AdminRowAction $role, Closure $handler): Action
    {
        return Action::make($name)
            ->label($role->label())
            ->icon($role->icon()->value)
            ->iconButton()
            ->color($role->isDangerAction() ? 'danger' : 'gray')
            ->extraAttributes(['class' => 'admin-dialog__header-action'.($role->isDangerAction() ? ' is-danger' : '')])
            ->action($handler)
            // Filament auto-unmounts completed nested actions. A regular
            // move/publication control must retain its parent editor. Halt
            // only the child action after its domain mutation; a Delete
            // action still transfers to the confirmation/completion flow.
            ->after(static function (Action $action) use ($role): void {
                // Nested modal actions normally partially render the action
                // itself. The workspace behind it must reflect moves and
                // publication changes in the same Livewire response.
                $action->getLivewire()->forceRender();
                if (! $role->isDangerAction()) {
                    $action->halt();
                }
            });
    }

    public static function command(
        Action $action,
        string|Closure $submitLabel,
        AdminDialogSize $size = AdminDialogSize::Small,
    ): Action {
        return self::committedTask($action, AdminDialogType::Command, $submitLabel, $size);
    }

    public static function viewer(
        Action $action,
        AdminDialogSize $size = AdminDialogSize::Default,
    ): Action {
        return self::base($action, AdminDialogType::Viewer, $size)
            ->modalSubmitAction(false)
            ->modalCancelAction(false);
    }

    public static function confirm(
        Action $action,
        string|Closure $heading,
        string|Closure|null $description = null,
        string $submitLabel = 'Confirm',
        bool $danger = false,
        bool|Closure $condition = true,
    ): Action {
        $submitClass = 'admin-dialog__header-action '.($danger ? 'is-danger' : 'is-primary');

        return self::base($action, AdminDialogType::Confirm, AdminDialogSize::Small)
            // Confirm is an application semantic, not Filament's confirmation mode.
            // A false condition deliberately makes the Action execute directly.
            ->modal($condition)
            ->modalHeading($heading)
            ->modalDescription($description)
            ->modalSubmitAction(fn (Action $action): Action => $action
                ->label($submitLabel)
                ->icon(AdminIcon::DialogSubmit->value)
                ->iconButton()
                ->color($danger ? 'danger' : 'gray')
                ->extraAttributes(['class' => $submitClass]))
            ->modalCancelAction(false);
    }

    private static function committedTask(
        Action $action,
        AdminDialogType $type,
        string|Closure $submitLabel,
        AdminDialogSize $size,
        ?AdminIcon $submitIcon = null,
        ?Closure $onClear = null,
    ): Action {
        $dialog = self::base($action, $type, $size)
            ->modalSubmitAction(fn (Action $action): Action => $action
                ->label($submitLabel)
                ->icon(($submitIcon ?? AdminIcon::DialogSubmit)->value)
                ->iconButton()
                ->extraAttributes(['class' => 'admin-dialog__header-action is-primary']))
            ->modalCancelAction(false);

        if ($type === AdminDialogType::Create) {
            $dialog->extraModalFooterActions([
                Action::make('clearCreateForm')
                    ->label('Clear form')
                    ->icon(AdminIcon::Clear->value)
                    ->iconButton()
                    ->color('gray')
                    ->extraAttributes(['class' => 'admin-dialog__header-action'])
                    ->action(function (Action $parentAction, $livewire) use ($onClear): void {
                        // The nested Clear action cannot call Filament's protected
                        // getMountedActionSchema(); resolve its parent's public schema.
                        $livewire->getSchema('mountedActionSchema'.$parentAction->getNestingIndex())?->fill([]);
                        $onClear?->__invoke($livewire);
                        $livewire->forceRender();
                    })
                    // Clear is a nested, modal-less Action. Let Filament finish
                    // and unmount this child normally while retaining Create.
                    // Halting here leaves the child mounted, so a second Clear
                    // nests another Action and corrupts the dialog stack.
                    ->cancelParentActions(false),
            ]);
        }

        return $dialog;
    }

    private static function base(Action $action, AdminDialogType $type, AdminDialogSize $size): Action
    {
        $classes = [
            'admin-task-dialog',
            $size->value,
            'admin-dialog--header-actions',
            'admin-dialog--'.$type->value,
        ];

        if (in_array($type, [AdminDialogType::Create, AdminDialogType::Command, AdminDialogType::Confirm], true)) {
            $classes[] = 'admin-dialog--submit';
        }

        if ($type === AdminDialogType::Confirm) {
            $classes[] = 'admin-dialog--confirmation';
        }

        return $action
            // Filament still owns modal state, focus, Escape and the native X.
            // The shared CSS width modifier is the actual visual authority.
            ->modalWidth(Width::Large)
            ->modalAlignment(Alignment::Start)
            ->modalFooterActionsAlignment(Alignment::Start)
            ->extraModalWindowAttributes(['class' => implode(' ', $classes)]);
    }
}
