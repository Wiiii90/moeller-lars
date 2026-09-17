<?php

namespace App\Livewire\Admin;

use App\Domain\Storage\SiteStorageReclaimService;
use App\Filament\Resources\MediaAssets\MediaAssetResource;
use App\Filament\Support\Dialogs\AdminDialog;
use App\Filament\Support\Dialogs\AdminDialogSize;
use Filament\Actions\Action;
use Filament\Actions\Concerns\InteractsWithActions;
use Filament\Actions\Contracts\HasActions;
use Filament\Notifications\Notification;
use Filament\Schemas\Concerns\InteractsWithSchemas;
use Filament\Schemas\Contracts\HasSchemas;
use Illuminate\Contracts\View\View;
use Livewire\Component;

final class SiteStorageReclaimControl extends Component implements HasActions, HasSchemas
{
    use InteractsWithActions;
    use InteractsWithSchemas;

    public function freeStorageAction(): Action
    {
        $action = Action::make('freeStorage')
            ->label('Free storage now')
            ->color('danger')
            ->action(function (): void {
                $result = app(SiteStorageReclaimService::class)->reclaim();

                Notification::make()
                    ->title('Storage reclaimed')
                    ->body(sprintf(
                        'Cleared %d Undo entries and released %d older publication restore snapshots. Activity remains available.',
                        $result['undo_receipts'],
                        $result['publication_snapshots'],
                    ))
                    ->success()
                    ->send();

                $this->redirect(MediaAssetResource::getUrl('index'), navigate: false);
            });

        return AdminDialog::confirm(
            $action,
            'Free recovery storage?',
            'This permanently clears Undo history and releases restore data for older publication checkpoints. Activity remains. The current live restore snapshot and any restore or revert source currently in use stay protected. Logical site usage updates immediately; the physical PostgreSQL file may shrink later during routine maintenance.',
            submitLabel: 'Free storage',
            danger: true,
            size: AdminDialogSize::Default,
        );
    }

    public function render(): View
    {
        return view('livewire.admin.site-storage-reclaim-control');
    }
}
