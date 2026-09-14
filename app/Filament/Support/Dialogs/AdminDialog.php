<?php

namespace App\Filament\Support\Dialogs;

use App\Filament\Support\AdminIcon;
use Filament\Actions\Action;
use Filament\Support\Enums\Width;

final class AdminDialog
{
    public static function create(
        Action $action,
        string $submitLabel,
        AdminDialogSize $size = AdminDialogSize::Small,
    ): Action {
        return self::committedTask($action, AdminDialogType::Create, $submitLabel, $size);
    }

    /**
     * Edit dialogs use autosave and therefore have no submit/cancel footer.
     * Optional session-level Undo actions are supplied by the caller through
     * Filament's extra modal footer actions and are lifted into the header rail.
     */
    public static function edit(
        Action $action,
        AdminDialogSize $size = AdminDialogSize::Small,
    ): Action {
        return self::base($action, AdminDialogType::Edit, $size)
            ->modalSubmitAction(false)
            ->modalCancelAction(false);
    }

    /**
     * Transitional helper for an edit that still has atomic persistence.
     * New edit flows must prefer edit(); this exists so presentation can be
     * centralized before a risky domain workflow is converted to autosave.
     */
    public static function editCommit(
        Action $action,
        string $submitLabel,
        AdminDialogSize $size = AdminDialogSize::Small,
    ): Action {
        return self::committedTask($action, AdminDialogType::Edit, $submitLabel, $size);
    }

    public static function command(
        Action $action,
        string $submitLabel,
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
        string $heading,
        ?string $description = null,
        string $submitLabel = 'Confirm',
        bool $danger = false,
        AdminDialogSize $size = AdminDialogSize::Mini,
    ): Action {
        $submitIcon = $danger ? AdminIcon::Delete : AdminIcon::Commit;
        $submitClass = 'admin-dialog__header-action '.($danger ? 'is-danger' : 'is-primary');

        return self::base($action, AdminDialogType::Confirm, $size)
            ->requiresConfirmation()
            ->modalHeading($heading)
            ->modalDescription($description)
            ->modalSubmitAction(fn (Action $submit): Action => $submit
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
        string $submitLabel,
        AdminDialogSize $size,
    ): Action {
        return self::base($action, $type, $size)
            ->modalSubmitAction(fn (Action $submit): Action => $submit
                ->label($submitLabel)
                ->icon(AdminIcon::Commit->value)
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
            ->extraModalWindowAttributes(['class' => implode(' ', $classes)]);
    }
}
