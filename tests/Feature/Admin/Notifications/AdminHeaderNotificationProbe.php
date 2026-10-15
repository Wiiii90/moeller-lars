<?php

namespace Tests\Feature\Admin\Notifications;

use App\Domain\Admin\AdminNotifier;
use Livewire\Component;

final class AdminHeaderNotificationProbe extends Component
{
    public function emitNotification(): void
    {
        app(AdminNotifier::class)->notification(
            title: 'Changes saved',
            body: 'Background gradient updated.',
            status: 'success',
        );
    }

    public function emitIdempotent(int $userId): void
    {
        app(AdminNotifier::class)->notification(
            user: $userId,
            sourceId: 'media-cleanup:asset-72',
            title: 'File cleanup failed',
            body: 'Stored file cleanup could not be completed.',
            status: 'danger',
            context: [
                'type' => 'media.cleanup_failure',
                'entity_type' => 'media_asset',
                'entity_id' => 72,
            ],
        );
    }

    public function render(): string
    {
        return '<div></div>';
    }
}
