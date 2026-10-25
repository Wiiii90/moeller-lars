<?php

namespace App\Domain\Content;

use Tiptap\Core\Extension;

final class ArtistTextSpacing extends Extension
{
    public static $name = 'artistTextSpacing';

    private const OPTIONS = ['default', 'none', 'compact', 'relaxed'];

    private static function normalize(mixed $value): string
    {
        return is_string($value) && in_array($value, self::OPTIONS, true) ? $value : 'default';
    }

    public function addGlobalAttributes(): array
    {
        return [[
            'types' => ['paragraph', 'heading'],
            'attributes' => [
                'artistSpacing' => [
                    'default' => 'default',
                    'parseHTML' => static fn ($node) => self::normalize($node->getAttribute('data-artist-line-spacing')),
                    'renderHTML' => static function ($attributes): array {
                        $value = self::normalize($attributes->artistSpacing ?? null);

                        return $value === 'default' ? [] : ['data-artist-line-spacing' => $value];
                    },
                ],
            ],
        ]];
    }
}
