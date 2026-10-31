<?php

namespace App\Filament\Support\Dialogs;

use App\Filament\Support\AdminIcon;
use App\Filament\Support\AdminRowAction;
use Closure;
use Filament\Actions\Action;
use Filament\Support\Enums\Alignment;
use Filament\Support\Enums\Width;

final class AdminDialog
{
    public static function create(
        Action $action,
        string $submitLabel,
        AdminDialogSize $size = AdminDialogSize::Small,
        ?AdminIcon $submitIcon = null,
    ): Action {
        return self::committedTask($action, AdminDialogType::Create, $submitLabel, $size, $submitIcon);
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
                'x-on:admin-edit-list-updated.window' => '$el.closest(".fi-modal.fi-modal-open") && $wire.call("callMountedAction")',
                // Track the *existing* Livewire requests, not a second save
                // executor. The native close event is deferred only while an
                // actual edit request is in flight, and only until it succeeds.
                'data-admin-edit-close-guard' => 'true',
                'x-data' => self::editCloseGuard(),
                'x-on:close-modal.window.capture' => 'handleEditClose($event)',
                'x-on:close-modal-quietly.window.capture' => 'handleEditClose($event)',
            ], merge: true);

        if ($windowAttributes instanceof Closure || $windowAttributes !== []) {
            $action->extraModalWindowAttributes($windowAttributes, merge: true);
        }

        return $action;
    }

    /**
     * Only native Edit dialogs use this guard. All persistence remains owned
     * by the current mounted Filament Action or field update: no additional
     * request is started on close, and ordinary closes remain instantaneous.
     * Livewire v4.4 resolves Actions after server effects are applied and
     * rejects failed validation/network Actions.
     */
    private static function editCloseGuard(): string
    {
        return <<<'JS'
            {
                pendingEditActions: new Set(),
                editCloseRequested: false,
                editCloseRequestedEvent: 'close-modal',
                editCloseAuthorized: false,
                editSaveFailed: false,
                stopTrackingEditActions: null,

                init() {
                    // Livewire 4 exposes a component-local interceptor.
                    // No global request listener or second save execution.
                    this.stopTrackingEditActions = this.$wire.$intercept(({ action }) => {
                        if (! this.$el.closest('.fi-modal.fi-modal-open')
                            || action.name === 'unmountAction') return

                        // This is the same Action Promise already created by
                        // Livewire. Track it, never initiate another save.
                        // A failed validation is only cleared by a new attempt
                        // through the validating mounted Edit Action; unrelated
                        // component requests must not authorize discarding edits.
                        if (this.pendingEditActions.size === 0 && action.name === 'callMountedAction') {
                            this.editSaveFailed = false
                        }
                        this.pendingEditActions.add(action)

                        action.promise.then(
                            () => this.completeEditAction(action, true),
                            () => this.completeEditAction(action, false),
                        )
                    })
                },

                destroy() {
                    this.stopTrackingEditActions?.()
                },

                completeEditAction(action, succeeded) {
                    this.pendingEditActions.delete(action)
                    if (! succeeded) this.editSaveFailed = true

                    if (! this.editCloseRequested || this.pendingEditActions.size > 0) return
                    this.editCloseRequested = false

                    // A failed save must never be interpreted as permission to
                    // unmount a dirty editor. Only the framework success path
                    // may resume the original modal close event.
                    if (this.editSaveFailed || ! this.$el.isConnected) return

                    const modal = this.$el.closest('.fi-modal')
                    if (! modal?.classList.contains('fi-modal-open')) return

                    this.editCloseAuthorized = true
                    this.$dispatch(this.editCloseRequestedEvent, { id: modal.id })
                },

                handleEditClose(event) {
                    const modal = this.$el.closest('.fi-modal')
                    if (! modal || event.detail?.id !== modal.id) return

                    if (this.editCloseAuthorized) {
                        this.editCloseAuthorized = false
                        return
                    }

                    // No pending request: preserve Filament's immediate native
                    // close, with no server roundtrip and no delay.
                    if (! this.pendingEditActions.size && ! this.editSaveFailed) return

                    // Capture before the native Filament close-modal listener
                    // so neither X, Escape nor backdrop can discard an edit
                    // during a request or after a failed validation.
                    event.stopImmediatePropagation()
                    if (this.pendingEditActions.size) {
                        this.editCloseRequestedEvent = event.type
                        this.editCloseRequested = true
                    }
                },
            }
            JS;
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
                if (! $role->isDangerAction()) $action->halt();
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
        ?AdminIcon $icon = null,
    ): Action {
        $submitIcon = $icon ?? ($danger ? AdminIcon::Delete : AdminIcon::DialogSubmit);
        $submitClass = 'admin-dialog__header-action '.($danger ? 'is-danger' : 'is-primary');

        return self::base($action, AdminDialogType::Confirm, AdminDialogSize::Small)
            // Confirm is an application semantic, not Filament's confirmation mode.
            // A false condition deliberately makes the Action execute directly.
            ->modal($condition)
            ->modalHeading($heading)
            ->modalDescription($description)
            ->modalSubmitAction(fn (Action $action): Action => $action
                ->label($submitLabel)
                ->icon($submitIcon->value)
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
    ): Action {
        return self::base($action, $type, $size)
            ->modalSubmitAction(fn (Action $action): Action => $action
                ->label($submitLabel)
                ->icon(($submitIcon ?? AdminIcon::DialogSubmit)->value)
                ->iconButton()
                ->extraAttributes(['class' => 'admin-dialog__header-action is-primary']))
            ->modalCancelAction(false);
    }

    private static function base(Action $action, AdminDialogType $type, AdminDialogSize $size): Action
    {
        $classes = [
            'admin-task-dialog',
            $size->value,
            'admin-dialog--header-actions',
            'admin-dialog--'.$type->value,
        ];

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
