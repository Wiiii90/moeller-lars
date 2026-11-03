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
                if (! $result['changed'] && ! $result['cleanup_incomplete']) {
                    return;
                }

                app(AdminNotifier::class)->notification(
                    title: $result['cleanup_incomplete'] ? 'Storage cleanup incomplete' : 'Storage reclaimed',
                    body: sprintf(
                        'Cleared %d Undo entries and released %d older restore snapshots. Removed %d previously deleted files, %d orphaned media files and %d expired upload staging artifacts. Existing originals and previews stay available.%s',
                        $result['undo_receipts'],
                        $result['publication_snapshots'],
                        $result['removed_files'],
                        $result['orphan_files'],
                        $result['expired_staging'],
                        $result['cleanup_incomplete']
                            ? ' Some cleanup work could not finish and must be retried; no incomplete operation is counted as removed.'
                            : '',
                    ),
                    status: $result['cleanup_incomplete'] ? 'warning' : 'success',
                );

                if ($result['changed']) {
                    $this->dispatch('storage-reclaimed');
                }
            });

        return AdminDialog::confirm(
            $action,
            'Free storage?',
            'Clears Undo history and older restore data, expired upload staging and genuinely orphaned files with no Working, LIVE or retained-history owner. Existing registered originals and thumbnails remain intact.',
            submitLabel: 'Free storage',
            danger: true,
        );
    }

    public function render(): View
    {
        return view('livewire.admin.site-storage-reclaim-control');
    }
}
