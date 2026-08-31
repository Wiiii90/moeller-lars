<?php

namespace App\Filament\Support;

use App\Domain\Content\SiteNodeType;
use App\Filament\Pages\CustomPageWorkspace;
use App\Filament\Pages\GalleryWorkspace;
use App\Filament\Pages\HomePresentation;
use App\Filament\Pages\JournalWorkspace;
use App\Models\SiteSection;
use Filament\Support\Icons\Heroicon;
use LogicException;

final class SiteNodePresentation
{
    public function icon(SiteNodeType $type): Heroicon
    {
        return match ($type) {
            SiteNodeType::Home => Heroicon::OutlinedHome,
            SiteNodeType::Gallery => Heroicon::OutlinedPhoto,
            SiteNodeType::Journal => Heroicon::OutlinedNewspaper,
            SiteNodeType::CustomPage => Heroicon::OutlinedWindow,
            SiteNodeType::NavigationNode => Heroicon::OutlinedFolder,
        };
    }

    public function workspaceUrl(SiteSection $section): ?string
    {
        return match ($section->nodeType()) {
            SiteNodeType::Home => HomePresentation::getUrl(),
            SiteNodeType::Gallery => GalleryWorkspace::getUrl([
                'gallery' => $this->galleryId($section),
            ]),
            SiteNodeType::Journal => $this->journalWorkspaceUrl($section),
            SiteNodeType::CustomPage => CustomPageWorkspace::getUrl([
                'section' => $section->getKey(),
            ]),
            SiteNodeType::NavigationNode => null,
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
