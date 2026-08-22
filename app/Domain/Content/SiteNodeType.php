<?php

namespace App\Domain\Content;

enum SiteNodeType: string
{
    case Home = 'home';
    case Gallery = 'gallery';
    case Journal = 'journal';
    case CustomPage = 'custom';
    case NavigationNode = 'navigation_group';

    /** @return array<string, string> */
    public static function creatableOptions(): array
    {
        return collect(self::cases())
            ->filter(static fn (self $type): bool => $type->isCreatable())
            ->mapWithKeys(static fn (self $type): array => [$type->value => $type->label()])
            ->all();
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
            self::NavigationNode => 'Navigation Node',
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

    public function canContainChildren(): bool
    {
        return in_array($this, [self::Gallery, self::NavigationNode], true);
    }

    public function canHaveParent(): bool
    {
        return ! in_array($this, [self::Home, self::NavigationNode], true);
    }

    public function canDelete(): bool
    {
        return $this !== self::Home;
    }

    public function canChangePlacement(): bool
    {
        return $this !== self::Home;
    }

    public function canBeChildOf(self $parent): bool
    {
        if (! $this->canHaveParent() || ! $parent->canContainChildren()) {
            return false;
        }

        return match ($this) {
            self::Gallery => in_array($parent, [self::Gallery, self::NavigationNode], true),
            self::Journal,
            self::CustomPage => $parent === self::NavigationNode,
            self::Home,
            self::NavigationNode => false,
        };
    }
}
