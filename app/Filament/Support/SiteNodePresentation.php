<?php

namespace App\Filament\Support;

use App\Domain\Content\JournalTemplate;
use App\Domain\Content\SiteNodeType;
use App\Filament\Pages\HomePresentation;
use App\Filament\Resources\ArtworkCategories\ArtworkCategoryResource;
use App\Filament\Resources\Artworks\ArtworkResource;
use App\Filament\Resources\BlogPosts\BlogPostResource;
use App\Filament\Resources\CustomPageSettings\CustomPageSettingResource;
use App\Filament\Resources\Exhibitions\ExhibitionResource;
use App\Filament\Resources\JournalSettings\JournalSettingResource;
use App\Models\CustomPageSetting;
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
            SiteNodeType::CustomPage => Heroicon::OutlinedDocumentText,
            SiteNodeType::NavigationNode => Heroicon::OutlinedFolder,
        };
    }

    public function workspaceUrl(SiteSection $section): ?string
    {
        return match ($section->nodeType()) {
            SiteNodeType::Home => HomePresentation::getUrl(),
            SiteNodeType::Gallery => ArtworkResource::getUrl('gallery', [
                'gallery' => $this->galleryId($section),
            ]),
            SiteNodeType::Journal => $this->journalWorkspaceUrl($section),
            SiteNodeType::CustomPage => CustomPageSettingResource::getUrl('edit', [
                'record' => $this->customPageSetting($section),
            ]),
            SiteNodeType::NavigationNode => null,
        };
    }

    public function editorUrl(SiteSection $section): ?string
    {
        return match ($section->nodeType()) {
            SiteNodeType::Gallery => ArtworkCategoryResource::getUrl('edit', [
                'record' => $this->galleryId($section),
            ]),
            SiteNodeType::Journal => JournalSettingResource::getSettingsUrl($section),
            SiteNodeType::Home,
            SiteNodeType::CustomPage,
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

    private function customPageSetting(SiteSection $section): CustomPageSetting
    {
        if (! $section->relationLoaded('customPageSetting')) {
            throw new LogicException('Custom Page presentation requires customPageSetting to be eager-loaded.');
        }

        $settings = $section->getRelation('customPageSetting');
        if (! $settings instanceof CustomPageSetting) {
            throw new LogicException('Custom Page site node is missing its required settings record.');
        }

        return $settings;
    }

    private function journalWorkspaceUrl(SiteSection $section): string
    {
        return match ($section->journalTemplate()) {
            JournalTemplate::Blog => BlogPostResource::getUrl('index', ['section' => $section->getKey()]),
            JournalTemplate::Exhibitions => ExhibitionResource::getUrl('index', ['section' => $section->getKey()]),
            null => throw new LogicException('Journal site node is missing its required template.'),
        };
    }
}
