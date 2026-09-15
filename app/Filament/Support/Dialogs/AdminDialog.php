<?php

namespace App\Filament\Support\Dialogs;

use App\Filament\Support\AdminIcon;
use Closure;
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
     * Edit dialogs autosave on native change events: text controls commit on
     * blur, while select/toggle controls commit immediately. No timers or
     * hidden submit buttons participate in persistence.
     *
     * Editorial edits use the large workspace width by default. Small is the
     * one intentional compact utility-editor exception; older Default/Mini
     * requests are promoted so an edit surface cannot accidentally collapse
     * back into a narrow single-column task.
     *
     * @param  array<mixed>|Closure  $windowAttributes
     */
    public static function edit(
        Action $action,
        AdminDialogSize $size = AdminDialogSize::Large,
        array|Closure $windowAttributes = [],
    ): Action {
        $size = $size === AdminDialogSize::Small ? AdminDialogSize::Small : AdminDialogSize::Large;

        $action = self::base($action, AdminDialogType::Edit, $size)
            ->modalSubmitAction(false)
            ->modalCancelAction(false)
            ->extraModalWindowAttributes(['wire:change' => 'persistMountedAdminEdit'], merge: true);

        if ($windowAttributes instanceof Closure || $windowAttributes !== []) {
            $action->extraModalWindowAttributes($windowAttributes, merge: true);
        }

        return $action;
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
        string|Closure $heading,
        string|Closure|null $description = null,
        string $submitLabel = 'Confirm',
        bool $danger = false,
        AdminDialogSize $size = AdminDialogSize::Mini,
        bool|Closure $required = true,
        ?AdminIcon $icon = null,
    ): Action {
        $submitIcon = $icon ?? ($danger ? AdminIcon::Delete : AdminIcon::Commit);
        $submitClass = 'admin-dialog__header-action '.($danger ? 'is-danger' : 'is-primary');

        return self::base($action, AdminDialogType::Confirm, $size)
            ->requiresConfirmation($required)
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
