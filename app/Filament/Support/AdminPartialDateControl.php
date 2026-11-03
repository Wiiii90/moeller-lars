<?php

namespace App\Filament\Support;

use App\Domain\Content\RegionalFormats;
use Filament\Forms\Components\ViewField;
use Illuminate\Support\HtmlString;

/** One reusable Year / Month / Exact Day control; no second date editor. */
final class AdminPartialDateControl
{
    public static function make(
        string $name = 'artwork_date',
        ?string $label = null,
        ?string $help = null,
        ?string $region = null,
    ): ViewField
    {
        $accessibleLabel = $label ?? 'Artwork date';
        $shownLabel = $help === null ? $accessibleLabel : new HtmlString(
            '<span class="admin-form-label-with-help">'.e($accessibleLabel)
            .AdminHelp::make($accessibleLabel.' help', $help)->toHtml().'</span>',
        );

        return ViewField::make($name)
            ->label($shownLabel)
            ->view('filament.forms.components.admin-partial-date-control')
            ->viewData([
                'region' => $region ?? RegionalFormats::publicRegion(),
                'accessibleLabel' => $accessibleLabel,
            ]);
    }
}
