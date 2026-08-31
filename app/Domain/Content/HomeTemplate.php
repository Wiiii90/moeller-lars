<?php

namespace App\Domain\Content;

enum HomeTemplate: string
{
    case Artwork = 'artwork';
    case UnderConstruction = 'under_construction';
    case SkipHome = 'skip_home';
    case Custom = 'custom';

    /** @return array<string, string> */
    public static function options(): array
    {
        return [
            self::Artwork->value => self::Artwork->label(),
            self::UnderConstruction->value => self::UnderConstruction->label(),
            self::SkipHome->value => self::SkipHome->label(),
            self::Custom->value => self::Custom->label(),
        ];
    }

    public function label(): string
    {
        return match ($this) {
            self::Artwork => 'Hero Artwork',
            self::UnderConstruction => 'Under Construction',
            self::SkipHome => 'Skip Home',
            self::Custom => 'Custom',
        };
    }
}
