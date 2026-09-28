<?php

namespace App\Filament\Pages\Concerns;

use App\Filament\Support\Dialogs\AdminDialog;
use App\Filament\Support\Dialogs\AdminDialogSize;
use App\Filament\Support\PagesSettingsDialog;
use Filament\Actions\Action;

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
                    $changed = $dialog->save($data);
                    $this->loadSections();
                    if ($changed) {
                        app(\App\Domain\Admin\AdminNotifier::class)->transient()->title('Pages settings saved')->success()->send();
                    }
                }),
            'Save settings',
            AdminDialogSize::Default,
        );
    }
}
