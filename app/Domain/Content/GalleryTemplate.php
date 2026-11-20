<?php

namespace App\Domain\Content;

enum GalleryTemplate: string
{
    case Standard = 'standard';
    case HeroArtworks = 'hero_artworks';

    /** @return array<string, string> */
    public static function options(): array
    {
        return [
            self::Standard->value => self::Standard->label(),
            self::HeroArtworks->value => self::HeroArtworks->label(),
        ];
    }

    public function label(): string
    {
        return match ($this) {
            self::Standard => 'Standard',
            self::HeroArtworks => 'Hero Artworks',
        };
    }
}
