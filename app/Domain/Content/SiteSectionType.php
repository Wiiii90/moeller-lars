<?php

namespace App\Domain\Content;

enum SiteSectionType: string
{
    case Gallery = 'gallery';
    case Journal = 'journal';
    case CustomPage = 'custom';
    case NavigationNode = 'navigation_group';

    /** @return array<string, string> */
    public static function creatableOptions(): array
    {
        $options = [];
        foreach (self::cases() as $type) {
            if ($type->isCreatable()) {
                $options[$type->value] = $type->label();
            }
        }

        return $options;
    }

    /** @return array<string, string> */
    public static function editableOptions(): array
    {
        return self::creatableOptions();
    }

    /** @return array<string, string> */
    public static function compactOptions(): array
    {
        $options = [];
        foreach (self::cases() as $type) {
            if ($type->isCreatable()) {
                $options[$type->value] = $type->compactLabel();
            }
        }

        return $options;
    }

    public function label(?JournalTemplate $journalTemplate = null): string
    {
        if ($this === self::Journal && $journalTemplate !== null) {
            return 'Journal · '.$journalTemplate->label();
        }

        return match ($this) {
            self::Gallery => 'Gallery',
            self::Journal => 'Journal',
            self::CustomPage => 'Custom Page',
            self::NavigationNode => 'Group Node',
        };
    }

    public function compactLabel(): string
    {
        return match ($this) {
            self::CustomPage => 'Custom',
            self::NavigationNode => 'Group',
            default => $this->label(),
        };
    }

    public function isCreatable(): bool
    {
        return true;
    }

    public function hasPublicPage(): bool
    {
        return $this !== self::NavigationNode;
    }

    public function requiresSlug(): bool
    {
        return in_array($this, [self::Gallery, self::Journal, self::CustomPage], true);
    }

    public function canDelete(): bool
    {
        return true;
    }

    public function canChangePlacement(): bool
    {
        return true;
    }

    public function canChangePublication(): bool
    {
        return true;
    }

    public function canConvert(): bool
    {
        return true;
    }
}
