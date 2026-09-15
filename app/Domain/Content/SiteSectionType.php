<?php

namespace App\Domain\Content;

enum SiteSectionType: string
{
    case Home = 'home';
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
            self::Home => 'Home',
            self::Gallery => 'Gallery',
            self::Journal => 'Journal',
            self::CustomPage => 'Custom Page',
            self::NavigationNode => 'Group Node',
        };
    }

    public function compactLabel(): string
    {
        return match ($this) {
            self::Home => 'Landing Page',
            self::CustomPage => 'Custom',
            self::NavigationNode => 'Group',
            default => $this->label(),
        };
    }

    public function isCreatable(): bool
    {
        return $this !== self::Home;
    }

    public function hasPublicPage(): bool
    {
        return $this !== self::NavigationNode;
    }

    public function requiresSlug(): bool
    {
        return in_array($this, [self::Gallery, self::Journal, self::CustomPage], true);
    }

    /**
     * Site-section type does not participate in hierarchy compatibility.
     * Depth and cycle rules are enforced by SiteSection and SiteSectionOrderService.
     */
    public function canContainChildren(): bool
    {
        return true;
    }

    public function canHaveParent(): bool
    {
        return true;
    }

    public function canDelete(): bool
    {
        return $this !== self::Home;
    }

    public function canChangePlacement(): bool
    {
        return $this !== self::Home;
    }

    public function canChangePublication(): bool
    {
        return $this !== self::Home;
    }

    public function canConvert(): bool
    {
        return $this !== self::Home;
    }

    public function canBeChildOf(self $parent): bool
    {
        return true;
    }
}
