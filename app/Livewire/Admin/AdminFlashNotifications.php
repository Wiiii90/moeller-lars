<?php

namespace App\Livewire\Admin;

use Filament\Livewire\Notifications;
use Filament\Notifications\Notification;
use Illuminate\Contracts\View\View;

final class AdminFlashNotifications extends Notifications
{
    protected function pushNotification(Notification $notification): void
    {
        $status = (string) ($notification->getStatus() ?? 'info');
        if (! in_array($status, ['success', 'warning', 'danger', 'info'], true)) {
            $status = 'info';
        }

        $this->dispatch('admin-notification-ticker', notification: [
            'id' => (string) $notification->getId(),
            'title' => trim(strip_tags((string) ($notification->getTitle() ?? 'Notification'))),
            'body' => filled($notification->getBody())
                ? trim(strip_tags((string) $notification->getBody()))
                : null,
            'status' => $status,
            'duration' => $notification->getDuration(),
        ]);
    }

    public function render(): View
    {
        return view('livewire.admin.admin-flash-notifications');
    }
}
