<?php

namespace App\Domain\Content;

enum GalleryTemplate: string
{
    case Showcase = 'showcase';
    case HeroArtworks = 'hero_artworks';

    /** @return array<string, string> */
    public static function options(): array
    {
        return [
            self::Showcase->value => self::Showcase->label(),
            self::HeroArtworks->value => self::HeroArtworks->label(),
        ];
    }

    public function label(): string
    {
        return match ($this) {
            self::Showcase => 'Showcase',
            self::HeroArtworks => 'Hero Artworks',
        };
    }
}
