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
        'legal_imprint' => 'Imprint',
        'legal_privacy' => 'Privacy Policy',
        'legal_usage_rights' => 'Copyright & AI Use',
        'legal_disclaimer' => 'External Links Notice',
    ];

    private const SLUGS = [
        'legal_imprint' => 'imprint',
        'legal_privacy' => 'privacy',
        'legal_usage_rights' => 'copyright-ai',
        'legal_disclaimer' => 'external-links',
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
            ? 'No legal content has been configured in General.'
            : implode(' · ', array_column($sections, 'label')).' from General';
    }
}
