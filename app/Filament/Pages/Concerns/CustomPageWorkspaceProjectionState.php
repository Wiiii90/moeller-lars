<?php

namespace App\Filament\Pages\Concerns;

use App\Domain\Analytics\ArtistReportingService;
use App\Domain\Content\SitePreviewContext;
use App\Domain\Content\SocialLinks;
use App\Models\CustomPageSetting;
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

        $this->loadComponentProjection();
    }

    private function refreshFromFirstPage(bool $refreshCvCount = false): void
    {
        $this->page = 1;
        $this->clearSelections();
        $this->loadComponentProjection();
    }

    private function componentReorderEnabled(): bool
    {
        return trim($this->componentSearch) === ''
            && $this->componentType === 'any'
            && $this->page === 1
            && $this->total <= $this->pageSize;
    }

    private function loadComponentProjection(bool $refreshCvCount = false): void
    {
        $settings = $this->settings();
        $blocks = $settings->components();
        $this->unfilteredComponentCount = count($blocks);

        $imageIds = collect($blocks)
            ->filter(static fn (array $block): bool => in_array($block['type'] ?? null, ['image', 'list'], true))
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

        foreach ($blocks as $index => $block) {
            $type = is_string($block['type'] ?? null) ? $block['type'] : '';
            if (isset($counts[$type])) {
                $counts[$type]++;
            }
            if ($type === 'list' && is_array($block['items'] ?? null)) {
                $listEntryCount += count($block['items']);
            }
            if ($type === 'image') {
                foreach ($block['children'] ?? [] as $child) {
                    if (is_array($child) && ($child['type'] ?? null) === 'list') {
                        $listEntryCount += count($child['items'] ?? []);
                    }
                }
            }
            if ($this->componentType !== 'any' && $type !== $this->componentType) {
                continue;
            }

            $published = CustomPageSetting::componentPublished($block);
            $mediaId = is_numeric($block['media_asset_id'] ?? null) ? (int) $block['media_asset_id'] : null;
            $imageName = $mediaId !== null ? ($imageNames[$mediaId] ?? null) : null;
            $parentSearch = mb_strtolower($this->componentParentSearchText($settings, $block, $imageName));
            if ($type === 'list') {
                // List entries are edited within the parent, but remain
                // searchable without creating phantom child rows.
                foreach (is_array($block['items'] ?? null) ? $block['items'] : [] as $item) {
                    if (! is_array($item)) {
                        continue;
                    }
                    $parentSearch .= ' '.mb_strtolower(implode(' ', array_filter([
                        $item['title'] ?? null,
                        CustomPageSetting::listItemContext($item),
                        CustomPageSetting::listItemDetails($item),
                    ], 'is_string')));
                }
            }

            if ($needle !== '') {
                $parentMatches = str_contains($parentSearch, $needle);
                if (! $parentMatches) {
                    continue;
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
                'editable' => $type !== 'legal_disclaimer',
                'children' => $type === 'image' ? $this->imageChildSummaries($settings, $block) : [],
            ];
        }

        $this->setPagination(count($projected));
        $offset = ($this->page - 1) * $this->pageSize;
        $reorderEnabled = $this->componentReorderEnabled();
        $this->components = array_map(
            static fn (array $component): array => [
                ...$component,
                'can_move_up' => $reorderEnabled && $component['index'] > 0,
                'can_move_down' => $reorderEnabled && $component['index'] < count($blocks) - 1,
            ],
            array_slice($projected, $offset, $this->pageSize),
        );
        $this->retainVisibleSelections();

        $this->metrics = [
            ['label' => 'Components', 'value' => number_format(count($blocks)), 'description' => 'Page sequence'],
            ['label' => 'Entries', 'value' => number_format($listEntryCount), 'description' => 'List entries'],
            ['label' => 'Images', 'value' => number_format($counts['image']), 'description' => 'Image components'],
            ['label' => 'Visits', 'value' => $this->metricValue($this->analytics['page']['visits'] ?? null), 'description' => 'This page · 30d'],
            ['label' => 'Views', 'value' => $this->metricValue($this->analytics['page']['views'] ?? null), 'description' => 'This page · 30d'],
            ['label' => 'Contact messages', 'value' => $this->metricValue($this->analytics['contact_messages'] ?? null), 'description' => 'Site-wide · 30d'],
        ];
    }
}
