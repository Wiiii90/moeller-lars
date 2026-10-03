<?php

namespace App\Filament\Support;

use App\Domain\Content\SiteSectionType;
use App\Filament\Pages\CustomPageWorkspace;
use App\Filament\Pages\GalleryWorkspace;
use App\Filament\Pages\HomePresentation;
use App\Filament\Pages\JournalWorkspace;
use App\Models\SiteSection;
use LogicException;

final class SiteNodePresentation
{
    public function icon(SiteSectionType $type): AdminIcon
    {
        return match ($type) {
            SiteSectionType::Home => AdminIcon::Home,
            SiteSectionType::Gallery => AdminIcon::Gallery,
            SiteSectionType::Journal => AdminIcon::Journal,
            SiteSectionType::CustomPage => AdminIcon::CustomPage,
            SiteSectionType::NavigationNode => AdminIcon::NavigationNode,
        };
    }

    public function workspaceUrl(SiteSection $section): ?string
    {
        return match ($section->nodeType()) {
            SiteSectionType::Home => HomePresentation::getUrl(),
            SiteSectionType::Gallery => GalleryWorkspace::getUrl([
                'gallery' => $this->galleryId($section),
            ]),
            SiteSectionType::Journal => $this->journalWorkspaceUrl($section),
            SiteSectionType::CustomPage => CustomPageWorkspace::getUrl([
                'section' => $section->getKey(),
            ]),
            SiteSectionType::NavigationNode => null,
        };
    }

    private function galleryId(SiteSection $section): int
    {
        $galleryId = $section->getAttribute('artwork_category_id');
        if (! is_numeric($galleryId)) {
            throw new LogicException('Gallery site node is missing its Gallery persistence reference.');
        }

        return (int) $galleryId;
    }

    private function journalWorkspaceUrl(SiteSection $section): string
    {
        if ($section->journalTemplate() === null) {
            throw new LogicException('Journal site node is missing its required template.');
        }

        return JournalWorkspace::getUrl(['section' => $section->getKey()]);
    }
}
