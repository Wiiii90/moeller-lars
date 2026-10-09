<?php

namespace App\Livewire\Admin;

use App\Domain\Admin\AdminNotifier;
use App\Domain\Storage\SiteStorageReclaimService;
use App\Filament\Support\Dialogs\AdminDialog;
use Filament\Actions\Action;
use Filament\Actions\Concerns\InteractsWithActions;
use Filament\Actions\Contracts\HasActions;
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
            ->label('Free storage')
            ->color('danger')
            ->action(function (): void {
                $result = app(SiteStorageReclaimService::class)->reclaim();

                app(AdminNotifier::class)->notification(
                    title: 'Storage reclaimed',
                    body: sprintf(
                        'Cleared %d Undo entries, released %d older publication restore snapshots, and removed %d rebuildable generated files. Activity remains available.',
                        $result['undo_receipts'],
                        $result['publication_snapshots'],
                        $result['generated_files'],
                    ),
                    status: 'success',
                );

                $this->dispatch('storage-reclaimed');
            });

        return AdminDialog::confirm(
            $action,
            'Free storage?',
            'Clears Undo history, older restore data and rebuildable thumbnails. The current LIVE restore snapshot and active restore/revert sources stay protected.',
            submitLabel: 'Free storage',
            danger: true,
        );
    }

    public function render(): View
    {
        return view('livewire.admin.site-storage-reclaim-control');
    }
}
