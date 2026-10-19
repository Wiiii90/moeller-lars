<?php

namespace App\Filament\Support;

use Filament\Forms\Components\Repeater;

/**
 * Filament Repeater form-state lifecycle with the canonical admin table chrome.
 *
 * Filament 5.7's built-in table repeater paints a separate rounded table and
 * its own action bar. Only the embedded view is replaced: the child schemas,
 * ColorPicker, validation and native add/delete actions stay Filament-owned.
 */
final class AdminTableRepeater extends Repeater
{
    protected function toTableEmbeddedHtml(): string
    {
        return $this->wrapEmbeddedHtml(
            view('filament.forms.components.admin-table-repeater', [
                'repeater' => $this,
            ])->render(),
            labelTag: 'div',
        );
    }
}
