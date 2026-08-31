<?php

namespace App\Livewire\Admin;

use App\Domain\Admin\AdminAuditService;
use App\Domain\Publication\PublicationService;
use Filament\Notifications\Notification;
use Illuminate\Contracts\View\View;
use Livewire\Component;

final class PublicationCommitDialog extends Component
{
    public bool $hasPendingChanges = false;

    public function mount(): void
    {
        $this->hasPendingChanges = app(PublicationService::class)->hasPendingChanges();
    }

    public function refreshState(): void
    {
        $this->hasPendingChanges = app(PublicationService::class)->hasPendingChanges();

        $this->dispatch('publication-state-changed', pending: $this->hasPendingChanges);
    }

    public function commitPublication(): void
    {
        $actor = app(AdminAuditService::class)->requireActor();
        $publication = app(PublicationService::class);

        if (! $publication->hasPendingChanges()) {
            $this->refreshState();

            return;
        }

        $checkpoint = $publication->commit($actor);
        $this->refreshState();

        if ($checkpoint === null) {
            return;
        }

        Notification::make()
            ->title('Website committed')
            ->success()
            ->send();
    }

    public function render(): View
    {
        return view('livewire.admin.publication-commit-dialog');
    }
}
