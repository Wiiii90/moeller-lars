<?php

namespace App\Domain\Content;

enum HomeTemplate: string
{
    case Artwork = 'artwork';
    case UnderConstruction = 'under_construction';
    case SkipHome = 'skip_home';
    case Custom = 'custom';

    /**
     * Templates that actually render Home content.
     *
     * SkipHome remains readable as a legacy persisted value, but redirecting
     * the public root is routing state and is no longer offered as a template.
     *
     * @return array<string, string>
     */
    public static function options(): array
    {
        return [
            self::Artwork->value => self::Artwork->label(),
            self::UnderConstruction->value => self::UnderConstruction->label(),
            self::Custom->value => self::Custom->label(),
        ];
    }

    public function contentTemplate(): self
    {
        return $this === self::SkipHome ? self::Artwork : $this;
    }

    public function isContentTemplate(): bool
    {
        return $this !== self::SkipHome;
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
