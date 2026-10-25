<?php

namespace App\Filament\Pages\Concerns;

use App\Domain\Admin\AdminNotifier;
use App\Filament\Support\Dialogs\AdminDialog;
use App\Filament\Support\Dialogs\AdminDialogSize;
use App\Filament\Support\PagesSettingsDialog;
use Filament\Actions\Action;
use Illuminate\Validation\ValidationException;

trait ManagesSitePagesSettings
{
    public function pagesSettingsAction(): Action
    {
        $dialog = app(PagesSettingsDialog::class);

        $persist = function (array $data) use ($dialog): void {
            try {
                $changed = $dialog->save($data);
            } catch (ValidationException $exception) {
                $this->throwPageDialogValidation($exception);
            }
            $this->loadSections(navigationChanged: $changed);
            if ($changed) {
                app(AdminNotifier::class)->notification(
                    title: 'Pages settings saved',
                    status: 'success',
                );
            }
        };

        return AdminDialog::edit(
            Action::make('pagesSettings')
                ->label('Settings')
                ->modalHeading(__('Pages settings'))
                ->fillForm(fn (): array => $dialog->fill())
                ->schema(fn (): array => $dialog->schema($persist))
                ->action($persist),
            AdminDialogSize::Default,
        );
    }
}
