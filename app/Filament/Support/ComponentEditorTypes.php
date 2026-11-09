<?php

namespace App\Filament\Support;

use App\Domain\Content\DividerVariant;

/**
 * One editorial component label map for Custom Page and Home dialogs.
 * Persistence eligibility remains owned by CustomPageSetting.
 */
final class ComponentEditorTypes
{
    public const LABELS = [
        'image' => 'Image / Video',
        'text' => 'Rich Text',
        'list' => 'List',
        'divider' => 'Divider',
        'contact' => 'Contact',
        'legal_disclaimer' => 'Legal Disclaimer',
    ];

    public const DIVIDER_LABELS = DividerVariant::LABELS;
}
