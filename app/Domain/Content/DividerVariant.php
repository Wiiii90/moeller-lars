<?php

namespace App\Domain\Content;

/**
 * Canonical divider variants shared by Custom Page and Home editors.
 * Public presentation maps these persisted values to CSS variants.
 */
final class DividerVariant
{
    public const LABELS = [
        'thin' => 'Thin',
        'subtle' => 'Subtle',
        'strong' => 'Strong',
        'dotted' => 'Dotted',
    ];

    public const VALUES = ['thin', 'subtle', 'strong', 'dotted'];
}
