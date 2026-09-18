<?php

namespace App\Filament\Pages\Concerns;

use App\Filament\Support\Dialogs\AdminDialog;
use App\Filament\Support\Dialogs\AdminDialogSize;
use App\Filament\Support\PagesSettingsDialog;
use Filament\Actions\Action;
use Filament\Notifications\Notification;

trait ManagesSitePagesSettings
{
    public function pagesSettingsAction(): Action
    {
        $dialog = app(PagesSettingsDialog::class);

        return AdminDialog::command(
            Action::make('pagesSettings')
                ->label('Settings')
                ->modalHeading('Pages settings')
                ->modalDescription('Configure the site hierarchy and Home routing shared by the Pages workspace.')
                ->fillForm(fn (): array => $dialog->fill())
                ->schema($dialog->schema())
                ->action(function (array $data) use ($dialog): void {
                    $dialog->save($data);
                    $this->loadSections();
                    Notification::make()->title('Pages settings saved')->success()->send();
                }),
            'Save settings',
            AdminDialogSize::Default,
        );
    }
}
