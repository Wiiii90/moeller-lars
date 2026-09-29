<?php

namespace App\Filament\Concerns;

use App\Domain\Admin\AdminNotifier;
use Filament\Notifications\Notification;

trait UsesAdminEditor
{
    private bool $adminEditorMutationChanged = true;

    protected function adminEditorSetMutationChanged(bool $changed): void
    {
        $this->adminEditorMutationChanged = $changed;
    }

    protected function adminEditorSavedNotificationTitle(): string
    {
        return 'Changes saved';
    }

    protected function getSavedNotification(): ?Notification
    {
        if ($this->adminEditorMutationChanged) {
            app(AdminNotifier::class)->feedback(
                title: $this->adminEditorSavedNotificationTitle(),
                status: 'success',
            );
        }

        return null;
    }

    public function areFormActionsSticky(): bool
    {
        return true;
    }

    public function canCreateAnother(): bool
    {
        return false;
    }

    protected function editorReturnUrl(string $fallback): string
    {
        $previousUrl = $this->previousUrl ?? null;
        if (is_string($previousUrl) && str_contains($previousUrl, '/admin')) {
            return $previousUrl;
        }

        return $fallback;
    }
}
