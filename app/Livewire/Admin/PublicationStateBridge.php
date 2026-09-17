<?php

namespace App\Livewire\Admin;

use App\Domain\Admin\AdminAuditService;
use App\Domain\Admin\AdminNotifier;
use App\Domain\Publication\PublicationService;
use App\Filament\Pages\Activity;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Str;
use Livewire\Component;

final class PublicationStateBridge extends Component
{
    public function commitPublication(): void
    {
        $actor = app(AdminAuditService::class)->requireActor();
        $publication = app(PublicationService::class);

        if (! $publication->hasPendingChanges()) {
            $this->dispatch('publication-state-changed', pending: false);

            return;
        }

        try {
            $checkpoint = $publication->commit($actor);
        } catch (\Throwable $exception) {
            try {
                app(AdminNotifier::class)->inbox(
                    user: $actor,
                    sourceId: 'publication-failed:'.Str::uuid(),
                    title: 'Publication failed',
                    body: 'The website could not be committed. Review the staged changes before retrying.',
                    status: 'danger',
                    context: [
                        'type' => 'publication.failure',
                        'action_url' => Activity::getUrl(),
                        'action_label' => 'Open Activity',
                    ],
                );
            } catch (\Throwable $notificationException) {
                report($notificationException);
            }

            throw $exception;
        }

        $this->dispatch('publication-state-changed', pending: $publication->hasPendingChanges());

        if ($checkpoint === null) {
            return;
        }

        app(AdminNotifier::class)->toast(
            title: 'Website committed',
            status: 'success',
        );
    }

    public function render(): View
    {
        return view('livewire.admin.publication-state-bridge');
    }
}
