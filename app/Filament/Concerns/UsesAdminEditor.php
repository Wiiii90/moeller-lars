<?php

namespace App\Filament\Concerns;

trait UsesAdminEditor
{
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
