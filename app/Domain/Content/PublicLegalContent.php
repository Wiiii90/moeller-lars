<?php

namespace App\Domain\Content;

use App\Models\PublicContentSetting;

/**
 * The General settings are the single authority for the editorial legal copy.
 * Only authored, nonempty sections are made public.
 */
final class PublicLegalContent
{
    public const EDITORIAL_FIELDS = [
        'legal_imprint' => 'Impressum',
        'legal_privacy' => 'Datenschutz',
        'legal_usage_rights' => 'Urheberrecht & KI-Nutzung',
        'legal_disclaimer' => 'Hinweise zu externen Links',
    ];

    private const SLUGS = [
        'legal_imprint' => 'impressum',
        'legal_privacy' => 'datenschutz',
        'legal_usage_rights' => 'urheberrecht',
        'legal_disclaimer' => 'externe-links',
    ];

    /** @return list<array{slug:string,label:string,text:string}> */
    public static function sections(PublicContentSetting $settings): array
    {
        $sections = [];
        foreach (self::EDITORIAL_FIELDS as $field => $label) {
            $value = $settings->getAttribute($field);
            if (is_string($value) && trim($value) !== '') {
                $sections[] = ['slug' => self::SLUGS[$field], 'label' => $label, 'text' => trim($value)];
            }
        }

        return $sections;
    }

    public static function editorSummary(PublicContentSetting $settings): string
    {
        $sections = self::sections($settings);

        return $sections === []
            ? 'Noch keine Rechtstexte in General hinterlegt.'
            : implode(' · ', array_column($sections, 'label')).' aus General';
    }
}
