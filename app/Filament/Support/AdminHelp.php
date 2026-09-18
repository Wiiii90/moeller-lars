<?php

namespace App\Filament\Support;

use Illuminate\Support\HtmlString;

final class AdminHelp
{
    public static function make(string $label, string $text): HtmlString
    {
        return new HtmlString(view('components.admin.help', [
            'label' => $label,
            'text' => $text,
        ])->render());
    }
}
