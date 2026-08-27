<?php

namespace App\Filament\Pages\Concerns;

use App\Domain\Analytics\ArtistReportingService;
use App\Domain\Content\SitePreviewContext;
use App\Domain\Content\SocialLinks;
use App\Models\CustomPageSetting;
use App\Models\CvEntry;
use App\Models\MediaAsset;
use App\Models\PublicContentSetting;
use App\Models\SiteSection;
use App\Routing\SiteNodeRoute;

trait CustomPageWorkspaceProjectionState
{
    private function loadAvailableSocialPlatforms(): void
    {
        $general = PublicContentSetting::general();
        $this->availableSocialPlatforms = collect(SocialLinks::visible($general->getAttribute('social_links')))
            ->mapWithKeys(static fn (array $link): array => [$link['platform'] => SocialLinks::label($link['platform'])])
            ->all();
    }

    private function loadAnalyticsSnapshot(SiteSection $section): void
    {
        $path = app(SiteNodeRoute::class)->path($section);
        if (! is_string($path) || $path === '') {
            $this->analytics = [];

            return;
        }

        $this->analytics = app(ArtistReportingService::class)->customPage($path, '30d');
    }

    private function reloadWorkspace(): void
    {
        $section = $this->section();
        $this->pageTitle = (string) ($section->getAttribute('title') ?: $section->getAttribute('navigation_label') ?: 'Custom Page');
        $this->publicUrl = (string) $section->getAttribute('state') === 'published'
            ? app(SiteNodeRoute::class)->url($section)
            : null;
        $this->previewUrl = app(SitePreviewContext::class)->previewUrlFor($section);

        $this->loadComponentProjection(refreshCvCount: true);
    }

    private function refreshFromFirstPage(bool $refreshCvCount): void
    {
        $this->page = 1;
        $this->clearSelections();
        $this->loadComponentProjection($refreshCvCount);
    }

    private function loadComponentProjection(bool $refreshCvCount): void
    {
        $settings = $this->settings();
        $blocks = $settings->components();
        $this->unfilteredComponentCount = count($blocks);
        $this->hasCvList = collect($blocks)->contains(static fn (array $block): bool => ($block['type'] ?? null) === 'cv_list');

        $cvRecords = collect();
        if ($this->hasCvList) {
            $cvRecords = CvEntry::query()->orderBy('position')->orderBy('id')->get();
            if ($refreshCvCount || $this->cvEntryCount === 0) {
                $this->cvEntryCount = $cvRecords->count();
            }
        } else {
            $this->cvEntryCount = 0;
        }

        $imageIds = collect($blocks)
            ->filter(static fn (array $block): bool => in_array($block['type'] ?? null, ['image', 'cv_list'], true))
            ->pluck('media_asset_id')
            ->filter(static fn (mixed $id): bool => is_numeric($id))
            ->map(static fn (mixed $id): int => (int) $id)
            ->unique()
            ->values()
            ->all();

        $imageNames = $imageIds === [] ? [] : MediaAsset::query()
            ->whereIn('id', $imageIds)
            ->pluck('original_filename', 'id')
            ->mapWithKeys(static fn (mixed $name, mixed $id): array => [(int) $id => (string) $name])
            ->all();

        $counts = array_fill_keys(array_keys(self::COMPONENT_LABELS), 0);
        $listEntryCount = 0;
        $projected = [];
        $needle = mb_strtolower(trim($this->componentSearch));
        $reorderEnabled = $needle === ''
            && $this->componentType === 'any'
            && count($blocks) <= $this->pageSize;

        foreach ($blocks as $index => $block) {
            $type = is_string($block['type'] ?? null) ? $block['type'] : '';
            if (isset($counts[$type])) {
                $counts[$type]++;
            }
            if ($type === 'list' && is_array($block['items'] ?? null)) {
                $listEntryCount += count($block['items']);
            }
            if ($this->componentType !== 'any' && $type !== $this->componentType) {
                continue;
            }

            $published = CustomPageSetting::componentPublished($block);
            $mediaId = is_numeric($block['media_asset_id'] ?? null) ? (int) $block['media_asset_id'] : null;
            $imageName = $mediaId !== null ? ($imageNames[$mediaId] ?? null) : null;
            $children = $this->componentChildren(
                $settings,
                $block,
                $cvRecords->all(),
                $index,
                $published,
                $reorderEnabled,
            );
            $parentSearch = mb_strtolower($this->componentParentSearchText($settings, $block, $imageName));

            if ($needle !== '') {
                $parentMatches = str_contains($parentSearch, $needle);
                $matchingChildren = array_values(array_filter(
                    $children,
                    static fn (array $child): bool => str_contains(mb_strtolower((string) ($child['search_text'] ?? '')), $needle),
                ));

                if (! $parentMatches && $matchingChildren === []) {
                    continue;
                }
                if (! $parentMatches) {
                    $children = $matchingChildren;
                }
            }

            $projected[] = [
                'index' => $index,
                'position' => $index + 1,
                'type' => $type,
                'type_label' => self::COMPONENT_LABELS[$type] ?? 'Component',
                'content' => $this->componentContent($settings, $block, $imageName),
                'status' => $published ? 'Published' : 'Unpublished',
                'published' => $published,
                'target' => $index.':'.$type,
                'editable' => true,
                'can_move_up' => $reorderEnabled && $index > 0,
                'can_move_down' => $reorderEnabled && $index < count($blocks) - 1,
                'is_cv_list' => $type === 'cv_list',
                'is_list' => $type === 'list',
                'is_contact' => $type === 'contact',
                'contact_child_count' => $type === 'contact' ? count($settings->contactChildren($block)) : 0,
                'children' => $children,
            ];
        }

        $this->setPagination(count($projected));
        $offset = ($this->page - 1) * $this->pageSize;
        $this->components = array_slice($projected, $offset, $this->pageSize);
        $this->retainVisibleSelections();

        $entries = $listEntryCount + ($this->hasCvList ? $this->cvEntryCount : 0);
        $this->metrics = [
            ['label' => 'Components', 'value' => number_format(count($blocks)), 'description' => 'Page sequence'],
            ['label' => 'Entries', 'value' => number_format($entries), 'description' => 'CV + list entries'],
            ['label' => 'Images', 'value' => number_format($counts['image']), 'description' => 'Image components'],
            ['label' => 'Visits', 'value' => $this->metricValue($this->analytics['page']['visits'] ?? null), 'description' => 'This page · 30d'],
            ['label' => 'Views', 'value' => $this->metricValue($this->analytics['page']['views'] ?? null), 'description' => 'This page · 30d'],
            ['label' => 'Contact messages', 'value' => $this->metricValue($this->analytics['contact_messages'] ?? null), 'description' => 'Site-wide · 30d'],
        ];
    }
}
