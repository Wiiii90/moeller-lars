<?php

namespace App\Domain\Content;

use Tiptap\Core\Extension;

final class ArtistTextSpacing extends Extension
{
    public static $name = 'artistTextSpacing';

    private const LEGACY = [
        'default' => '1.5',
        'none' => '1.0',
        'compact' => '1.15',
        'relaxed' => '2.0',
    ];

    private const OPTIONS = ['1.0', '1.15', '1.5', '2.0'];

    private static function normalize(mixed $value): string
    {
        if (! is_string($value)) {
            return '1.5';
        }

        $value = self::LEGACY[$value] ?? $value;

        return in_array($value, self::OPTIONS, true) ? $value : '1.5';
    }

    public function addGlobalAttributes(): array
    {
        return [[
            'types' => ['paragraph', 'heading'],
            'attributes' => [
                'artistSpacing' => [
                    'default' => '1.5',
                    'parseHTML' => static fn ($node) => self::normalize($node->getAttribute('data-artist-line-spacing')),
                    'renderHTML' => static function ($attributes): array {
                        $value = self::normalize($attributes->artistSpacing ?? null);

                        return $value === '1.5' ? [] : ['data-artist-line-spacing' => $value];
                    },
                ],
            ],
        ]];
    }
}
