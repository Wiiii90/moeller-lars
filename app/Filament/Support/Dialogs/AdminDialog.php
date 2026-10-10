<?php

namespace App\Filament\Support\Dialogs;

use App\Filament\Support\AdminIcon;
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
    ): Action {
        return self::committedTask($action, AdminDialogType::Create, $submitLabel, $size);
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
            // Filament validates and runs the domain Action inside its own transaction.
            // Halt only after a successful edit so the native modal stays open.
            ->after(static function (Action $action): void {
                $action->halt();
            })
            ->extraModalWindowAttributes(['wire:change' => 'callMountedAction'], merge: true);

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
        string $submitLabel,
        AdminDialogSize $size,
    ): Action {
        return self::base($action, $type, $size)
            ->modalSubmitAction(fn (Action $action): Action => $action
                ->label($submitLabel)
                ->icon(AdminIcon::DialogSubmit->value)
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
