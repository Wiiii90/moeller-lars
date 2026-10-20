<?php

namespace App\Domain\Content;

use Tiptap\Core\Extension;

/** Shared, fixed-value image layout attributes for the visual Rich Text editor. */
final class ArtistImageLayout extends Extension
{
    public static $name = 'artistImageLayout';

    public const PLACEMENTS = ['inline', 'left', 'center', 'right', 'float-left', 'float-right'];

    public static function placement(mixed $value): string
    {
        return is_string($value) && in_array($value, self::PLACEMENTS, true) ? $value : 'inline';
    }

    public function addGlobalAttributes(): array
    {
        return [[
            'types' => ['image'],
            'attributes' => [
                'artistPlacement' => [
                    'default' => 'inline',
                    'parseHTML' => static fn ($node) => self::placement($node->getAttribute('data-artist-placement')),
                    'renderHTML' => static fn ($attributes) => [
                        'data-artist-placement' => self::placement($attributes->artistPlacement ?? null),
                    ],
                ],
                'artistCaption' => [
                    'default' => null,
                    'parseHTML' => static fn ($node) => $node->getAttribute('data-artist-caption') ?: null,
                    'renderHTML' => static function ($attributes): array {
                        $caption = $attributes->artistCaption ?? null;

                        return is_string($caption) && trim($caption) !== ''
                            ? ['data-artist-caption' => mb_substr(trim($caption), 0, 500)]
                            : [];
                    },
                ],
            ],
        ]];
    }

    public static function decorateHtml(string $html, string $source): string
    {
        preg_match_all('/<img\\b[^>]*>/i', $source, $matches);
        $sourceImages = $matches[0] ?? [];
        $index = 0;
        preg_match_all('/<img\\b[^>]*>/i', $html, $renderedImages);

        // Each displayed image must correspond to one submitted image.
        // Never attach the next image's caption when sanitization or
        // parsing has removed a node.
        if (count($sourceImages) !== count($renderedImages[0] ?? [])) {
            throw UnsafeRichTextException::unsupportedSyntax();
        }

        // The renderer has already sanitized all image HTML. Only our own
        // fixed placement classes and escaped caption text are added.
        return preg_replace_callback('/<img\\b[^>]*>/i', static function (array $rendered) use ($sourceImages, &$index): string {
            $sourceImage = $sourceImages[$index++] ?? null;
            if (! is_string($sourceImage)) {
                return $rendered[0];
            }

            $placement = 'inline';
            if (preg_match('/\\bdata-artist-placement\\s*=\\s*([\"\\\'])(.*?)\\1/i', $sourceImage, $part)) {
                $placement = self::placement(html_entity_decode($part[2], ENT_QUOTES | ENT_HTML5, 'UTF-8'));
            }

            $caption = '';
            if (preg_match('/\\bdata-artist-caption\\s*=\\s*([\"\\\'])(.*?)\\1/i', $sourceImage, $part)) {
                $caption = trim(html_entity_decode($part[2], ENT_QUOTES | ENT_HTML5, 'UTF-8'));
            }
            $caption = mb_substr($caption, 0, 500);

            if ($placement === 'inline' && $caption === '') {
                return $rendered[0];
            }

            $class = 'rich-text__image rich-text__image--'.$placement;

            return '<span class="'.$class.'" role="figure">'.$rendered[0].
                ($caption !== '' ? '<span class="rich-text__image-caption">'.e($caption).'</span>' : '').
                '</span>';
        }, $html) ?? $html;
    }
}
