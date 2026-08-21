<?php

namespace App\Filament\Concerns;

trait UsesEditorOverlay
{
    /**
     * @return array<string>
     */
    public function getPageClasses(): array
    {
        return [
            ...parent::getPageClasses(),
            'artist-editor-overlay',
        ];
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
