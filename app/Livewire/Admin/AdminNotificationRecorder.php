<?php

namespace App\Livewire\Admin;

use App\Models\AdminNotification;
use App\Models\User;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Str;
use Livewire\Component;

final class AdminNotificationRecorder extends Component
{
    /** @param array<string, mixed> $payload */
    public function record(array $payload): void
    {
        $user = auth()->user();
        if (! $user instanceof User) {
            return;
        }

        $sourceId = is_string($payload['sourceId'] ?? null) ? trim($payload['sourceId']) : '';
        $title = is_string($payload['title'] ?? null) ? trim(strip_tags($payload['title'])) : '';
        if ($sourceId === '' || $title === '') {
            return;
        }

        $status = is_string($payload['status'] ?? null) ? strtolower(trim($payload['status'])) : 'info';
        if (! in_array($status, ['success', 'warning', 'danger', 'info'], true)) {
            $status = 'info';
        }

        $body = is_string($payload['body'] ?? null) ? trim(strip_tags($payload['body'])) : null;
        if ($body === '') {
            $body = null;
        }

        AdminNotification::query()->firstOrCreate(
            [
                'user_id' => $user->getKey(),
                'source_id' => Str::limit($sourceId, 96, ''),
            ],
            [
                'status' => $status,
                'title' => Str::limit($title, 255, ''),
                'body' => $body === null ? null : Str::limit($body, 2000, ''),
            ],
        );
    }

    public function render(): View
    {
        return view('livewire.admin.admin-notification-recorder');
    }
}
