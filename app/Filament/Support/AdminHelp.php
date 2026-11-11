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

    public static function label(string $text, string $helpLabel, string $description): HtmlString
    {
        return new HtmlString(
            '<span class="admin-form-label-with-help">'.e($text)
            .self::make($helpLabel, $description)->toHtml()
            .'</span>',
        );
    }
}
