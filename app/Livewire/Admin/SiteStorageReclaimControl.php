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
                        'Cleared %d Undo entries, released %d older publication restore snapshots, and removed %d rebuildable generated files. Activity remains available.',
                        $result['undo_receipts'],
                        $result['publication_snapshots'],
                        $result['generated_files'],
                    ))
                    ->success()
                    ->send();

                $this->redirect(MediaAssetResource::getUrl('index'), navigate: false);
            });

        return AdminDialog::confirm(
            $action,
            'Free recovery storage?',
            'This permanently clears Undo history, releases restore data for older publication checkpoints, and removes rebuildable generated thumbnails. Activity remains. The current live restore snapshot and any restore or revert source currently in use stay protected. Generated thumbnails are recreated from their canonical originals when next needed. Logical site usage updates immediately; the physical PostgreSQL file may shrink later during routine maintenance.',
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
