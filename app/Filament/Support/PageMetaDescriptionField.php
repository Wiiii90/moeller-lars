<?php

namespace App\Filament\Support;

use Filament\Forms\Components\Textarea;
use Illuminate\Support\HtmlString;

/** One shared page-level metadata field for Home, Gallery, Journal and Custom. */
final class PageMetaDescriptionField
{
    public static function make(): Textarea
    {
        return Textarea::make('meta_description')
            ->label(new HtmlString(
                '<span class="admin-form-label-with-help">Meta Description'
                .AdminHelp::make(
                    'About Meta Description',
                    'This short page summary may appear under your page title in search results. Search engines can choose their own excerpt instead; this is not a direct ranking factor. Around 150–160 characters is a useful guideline.',
                )
                .'</span>',
            ))
            ->rows(2)
            ->nullable()
            ->maxLength(10000)
            ->columnSpanFull();
    }
}
