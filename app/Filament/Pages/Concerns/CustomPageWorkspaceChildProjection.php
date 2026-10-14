<?php

namespace App\Filament\Pages\Concerns;


trait CustomPageWorkspaceChildProjection
{
    private function componentReorderEnabled(): bool
    {
        return trim($this->componentSearch) === ''
            && $this->componentType === 'any'
            && $this->page === 1
            && $this->total <= $this->pageSize;
    }
}
