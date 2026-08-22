<?php

namespace App\Domain\Content;

enum JournalTemplate: string
{
    case Blog = 'blog';
    case Exhibitions = 'exhibitions';

    /** @return array<string, string> */
    public static function options(): array
    {
        return [
            self::Blog->value => self::Blog->label(),
            self::Exhibitions->value => self::Exhibitions->label(),
        ];
    }

    public function label(): string
    {
        return match ($this) {
            self::Blog => 'Blog',
            self::Exhibitions => 'Exhibitions',
        };
    }
}
