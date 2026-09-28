<?php

namespace App\Filament\Concerns;

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

    protected function getSavedNotification(): ?\Filament\Notifications\Notification
    {
        if (! $this->adminEditorMutationChanged) {
            return null;
        }

        return app(\App\Domain\Admin\AdminNotifier::class)
            ->transient()
            ->success()
            ->title($this->adminEditorSavedNotificationTitle());
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
