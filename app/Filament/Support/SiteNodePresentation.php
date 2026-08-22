<?php

namespace App\Filament\Support;

use App\Domain\Content\JournalTemplate;
use App\Domain\Content\SiteNodeType;
use App\Filament\Pages\SitePages;
use App\Filament\Resources\ArtworkCategories\ArtworkCategoryResource;
use App\Filament\Resources\Artworks\ArtworkResource;
use App\Filament\Resources\BlogPosts\BlogPostResource;
use App\Filament\Resources\CustomPageSettings\CustomPageSettingResource;
use App\Filament\Resources\Exhibitions\ExhibitionResource;
use App\Filament\Resources\JournalSettings\JournalSettingResource;
use App\Models\CustomPageSetting;
use App\Models\SiteSection;

final class SiteNodePresentation
{
    public function workspaceUrl(SiteSection $section, bool $fallbackToPages = true): ?string
    {
        $type = SiteNodeType::fromSection($section);
        $fallback = $fallbackToPages ? SitePages::getUrl().'#site-section-'.$section->getKey() : null;

        return match ($type) {
            SiteNodeType::Home => ArtworkResource::getUrl('index'),
            SiteNodeType::Gallery => $this->galleryWorkspaceUrl($section) ?? $fallback,
            SiteNodeType::CustomPage => $this->customPageWorkspaceUrl($section) ?? $fallback,
            SiteNodeType::Journal => $this->journalWorkspaceUrl($section),
            SiteNodeType::NavigationNode => null,
        };
    }

    public function editorUrl(SiteSection $section): ?string
    {
        return match (SiteNodeType::fromSection($section)) {
            SiteNodeType::Gallery => is_numeric($section->getAttribute('artwork_category_id'))
                ? ArtworkCategoryResource::getUrl('edit', ['record' => (int) $section->getAttribute('artwork_category_id')])
                : null,
            SiteNodeType::Journal => JournalSettingResource::getSettingsUrl($section),
            SiteNodeType::Home,
            SiteNodeType::CustomPage,
            SiteNodeType::NavigationNode => null,
        };
    }

    private function galleryWorkspaceUrl(SiteSection $section): ?string
    {
        $galleryId = $section->getAttribute('artwork_category_id');

        return is_numeric($galleryId)
            ? ArtworkResource::getUrl('gallery', ['gallery' => (int) $galleryId])
            : null;
    }

    private function customPageWorkspaceUrl(SiteSection $section): ?string
    {
        $settings = $section->relationLoaded('customPageSetting')
            ? $section->getRelation('customPageSetting')
            : $section->customPageSetting()->first();

        return $settings instanceof CustomPageSetting
            ? CustomPageSettingResource::getUrl('edit', ['record' => $settings])
            : null;
    }

    private function journalWorkspaceUrl(SiteSection $section): string
    {
        return JournalTemplate::tryFrom((string) $section->getAttribute('template')) === JournalTemplate::Exhibitions
            ? ExhibitionResource::getUrl('index', ['section' => $section->getKey()])
            : BlogPostResource::getUrl('index', ['section' => $section->getKey()]);
    }
}
