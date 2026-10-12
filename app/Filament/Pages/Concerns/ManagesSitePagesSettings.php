<?php

namespace App\Filament\Pages\Concerns;

use App\Domain\Admin\AdminNotifier;
use App\Filament\Support\Dialogs\AdminDialog;
use App\Filament\Support\Dialogs\AdminDialogSize;
use App\Filament\Support\PagesSettingsDialog;
use Filament\Actions\Action;

trait ManagesSitePagesSettings
{
    public function pagesSettingsAction(): Action
    {
        $dialog = app(PagesSettingsDialog::class);

        return AdminDialog::edit(
            Action::make('pagesSettings')
                ->label('Settings')
                ->modalHeading('Pages settings')
                ->fillForm(fn (): array => $dialog->fill())
                ->schema(fn (): array => $dialog->schema())
                ->action(function (array $data) use ($dialog): void {
                    $changed = $dialog->save($data);
                    $this->loadSections(navigationChanged: $changed);
                    if ($changed) {
                        app(AdminNotifier::class)->notification(
                            title: 'Pages settings saved',
                            status: 'success',
                        );
                    }
                }),
            AdminDialogSize::Default,
        );
    }
}
