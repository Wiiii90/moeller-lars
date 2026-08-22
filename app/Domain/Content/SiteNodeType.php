<?php

namespace App\Domain\Content;

use App\Models\SiteSection;
use Filament\Support\Icons\Heroicon;

enum SiteNodeType: string
{
    case Home = 'home';
    case Gallery = 'gallery';
    case Journal = 'journal';
    case CustomPage = 'custom';
    case NavigationNode = 'navigation_group';

    public static function fromSection(SiteSection $section): self
    {
        return self::from((string) $section->getAttribute('type'));
    }

    /** @return array<string, string> */
    public static function creatableOptions(): array
    {
        return [
            self::Gallery->value => self::Gallery->label(),
            self::Journal->value => self::Journal->label(),
            self::CustomPage->value => self::CustomPage->label(),
            self::NavigationNode->value => self::NavigationNode->label(),
        ];
    }

    public function label(?string $journalTemplate = null): string
    {
        if ($this === self::Journal && $journalTemplate !== null) {
            $template = JournalTemplate::tryFrom($journalTemplate);

            return $template === null ? 'Journal' : 'Journal · '.$template->label();
        }

        return match ($this) {
            self::Home => 'Home',
            self::Gallery => 'Gallery',
            self::Journal => 'Journal',
            self::CustomPage => 'Custom Page',
            self::NavigationNode => 'Navigation Node',
        };
    }

    public function navigationIcon(): Heroicon
    {
        return match ($this) {
            self::Home => Heroicon::OutlinedHome,
            self::Gallery => Heroicon::OutlinedPhoto,
            self::Journal => Heroicon::OutlinedNewspaper,
            self::CustomPage => Heroicon::OutlinedDocumentText,
            self::NavigationNode => Heroicon::OutlinedFolder,
        };
    }

    public function hasPublicPage(): bool
    {
        return $this !== self::NavigationNode;
    }

    public function requiresSlug(): bool
    {
        return $this !== self::NavigationNode;
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

    public function canBeChildOf(self $parent): bool
    {
        if (! $this->canHaveParent() || ! $parent->canContainChildren()) {
            return false;
        }

        if ($this === self::Gallery) {
            return in_array($parent, [self::Gallery, self::NavigationNode], true);
        }

        return $parent === self::NavigationNode;
    }

    public function publicPath(SiteSection $section): ?string
    {
        return match ($this) {
            self::Home => '/',
            self::NavigationNode => null,
            self::Gallery,
            self::Journal,
            self::CustomPage => '/'.$section->getAttribute('slug'),
        };
    }

    public function publicUrl(SiteSection $section): ?string
    {
        return match ($this) {
            self::Home => route('home'),
            self::NavigationNode => null,
            self::Gallery,
            self::Journal,
            self::CustomPage => route('site.section', ['section' => $section->getAttribute('slug')]),
        };
    }

    public function isCurrentRequest(SiteSection $section): bool
    {
        return match ($this) {
            self::Home => request()->routeIs('home', 'preview.home'),
            self::NavigationNode => false,
            self::Gallery,
            self::CustomPage => request()->routeIs('site.section', 'preview.site.section')
                && request()->route('section') === $section->getAttribute('slug'),
            self::Journal => request()->routeIs('site.section', 'preview.site.section', 'journal.show', 'preview.journal.show')
                && request()->route('section') === $section->getAttribute('slug'),
        };
    }
}
