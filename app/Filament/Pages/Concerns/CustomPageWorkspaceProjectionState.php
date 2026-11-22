<?php

namespace App\Filament\Pages\Concerns;

use App\Domain\Content\RegionalFormats;

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

    public function toggleCustomPreviewAutoFocus(): void
    {
        $this->customPreviewAutoFocus = ! $this->customPreviewAutoFocus;
        if ($this->customPreviewAutoFocus) {
            $this->syncCustomPreviewFocus(forceRefresh: true);
        }
    }

    public function customStagePreviewUrl(): ?string
    {
        if ($this->previewUrl === null) {
            return null;
        }

        if (! $this->customPreviewAutoFocus || $this->customPreviewFocusedTarget === null) {
            return $this->previewUrl;
        }

        // Targets are checked against the canonical component document
        // before they become Public document anchors.
        if (! preg_match('/^(\\d+):[a-z_]+$/', $this->customPreviewFocusedTarget, $matches)) {
            return $this->previewUrl;
        }

        $type = explode(':', $this->customPreviewFocusedTarget, 2)[1];
        $anchor = in_array($type, CustomPageSetting::MEDIA_COMPONENT_TYPES, true)
            ? 'custom-page-image-'.$matches[1]
            : 'custom-page-component-page-'.$matches[1];

        return $this->previewUrl.'#'.$anchor;
    }

    private function syncCustomPreviewFocus(bool $forceRefresh = false): void
    {
        $validTargets = [];
        foreach ($this->settings()->components() as $index => $block) {
            if (is_array($block) && is_string($block['type'] ?? null)) {
                $validTargets[] = $index.':'.$block['type'];
            }
        }
        $selected = array_values(array_intersect($this->selectedComponentTargets, $validTargets));
        // Multiple selected components show the complete Custom Page,
        // never an arbitrary last-selected component.
        $next = count($this->selectedComponentTargets) === 1 && count($selected) === 1
            ? $selected[0] : null;
        $changed = $next !== $this->customPreviewFocusedTarget;
        $this->customPreviewFocusedTarget = $next;

        if (($this->customPreviewAutoFocus && $changed) || $forceRefresh) {
            if ($this->customPreviewAutoFocus && $this->previewUrl !== null) {
                $this->dispatch('admin-live-preview-updated', url: $this->customStagePreviewUrl());
            } else {
                $this->dispatch('admin-live-preview-updated');
            }
        }
    }

    /**
     * The table action must evaluate the same atomic deletion set as
     * CustomPageEditorialService::deleteBlocks(): selected Layout members
     * do not prevent their owning Layout from being removed together.
     */
    public function canDeleteSelectedComponents(): bool
    {
        $targets = $this->selectedComponentTargetData();
        if ($targets === [] || count($targets) !== count($this->selectedComponentTargets)) {
            return false;
        }

        $blocks = $this->settings()->components();
        $indices = array_column($targets, 'index');
        if (count(array_unique($indices)) !== count($indices)) {
            return false;
        }

        $selected = array_fill_keys($indices, true);
        $remaining = array_values(array_filter(
            $blocks,
            static fn (int $index): bool => ! isset($selected[$index]),
            ARRAY_FILTER_USE_KEY,
        ));

        foreach ($targets as $target) {
            $block = $blocks[$target['index']] ?? null;
            if (! is_array($block) || ($block['type'] ?? null) !== $target['type']
                || ! CustomPageSetting::canDeleteComponent($block, $remaining)) {
                return false;
            }
        }

        return true;
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
            ->filter(static fn (array $block): bool => in_array($block['type'] ?? null, CustomPageSetting::MEDIA_COMPONENT_TYPES, true))
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

        $exhibitionIds = collect($blocks)->where('type', 'exhibitions')
            ->flatMap(static fn (array $block): array => $block['exhibition_ids'] ?? [])
            ->unique()->values()->all();
        $postIds = collect($blocks)->where('type', 'blog')
            ->flatMap(static fn (array $block): array => $block['post_ids'] ?? [])
            ->unique()->values()->all();
        $exhibitionNames = $exhibitionIds === [] ? []
            : \App\Models\Exhibition::query()->whereKey($exhibitionIds)->pluck('title', 'id')->all();
        $postNames = $postIds === [] ? []
            : \App\Models\BlogPost::query()->whereKey($postIds)->pluck('title', 'id')->all();

        $counts = array_fill_keys(array_keys(self::COMPONENT_LABELS), 0);
        $projected = [];
        $needle = mb_strtolower(trim($this->componentSearch));

        foreach ($blocks as $index => $block) {
            $type = is_string($block['type'] ?? null) ? $block['type'] : '';
            if (isset($counts[$type])) {
                $counts[$type]++;
            }
            if ($this->componentType !== 'any' && $type !== $this->componentType) {
                continue;
            }

            $published = CustomPageSetting::componentPublished($block);
            $mediaId = is_numeric($block['media_asset_id'] ?? null) ? (int) $block['media_asset_id'] : null;
            $imageName = $mediaId !== null ? ($imageNames[$mediaId] ?? null) : null;
            $parentSearch = mb_strtolower($this->componentParentSearchText($settings, $block, $imageName));
            if ($needle !== '') {
                $parentMatches = str_contains($parentSearch, $needle);
                if (! $parentMatches) {
                    continue;
                }
            }

            $content = $this->componentContent($settings, $block, $imageName);
            $areaId = $block['layout_area_id'] ?? null;
            $areaOptions = $this->layoutAreaOptions();
            if (is_string($areaId) && isset($areaOptions[$areaId])) {
                $content['secondary'] = trim(implode(' · ', array_filter([
                    $content['secondary'], $areaOptions[$areaId],
                ], static fn (string $value): bool => $value !== '')));
            }

            $embeddedChildren = [];
            if ($type === 'exhibitions') {
                foreach ($block['exhibition_ids'] ?? [] as $entryId) {
                    $embeddedChildren[] = [
                        'id' => (int) $entryId,
                        'kind' => 'exhibitions',
                        'title' => $exhibitionNames[$entryId] ?? 'Exhibition unavailable',
                    ];
                }
            } elseif ($type === 'blog') {
                foreach ($block['post_ids'] ?? [] as $entryId) {
                    $embeddedChildren[] = [
                        'id' => (int) $entryId,
                        'kind' => 'blog',
                        'title' => $postNames[$entryId] ?? 'Post unavailable',
                    ];
                }
            }

            $projected[] = [
                'embedded_children' => $embeddedChildren,
                'index' => $index,
                'position' => $index + 1,
                'type' => $type,
                'layout_area_id' => is_string($areaId) ? $areaId : null,
                'type_label' => self::COMPONENT_LABELS[$type] ?? 'Component',
                'content' => $content,
                'status' => $published ? 'Published' : 'Unpublished',
                'published' => $published,
                'target' => $index.':'.$type,
                'editable' => $type !== 'legal_disclaimer',
                'can_delete' => CustomPageSetting::canDeleteComponent($block, $blocks),
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
        $this->syncCustomPreviewFocus(forceRefresh: true);

        $this->metrics = [
            ['label' => 'Components', 'value' => RegionalFormats::adminNumber(count($blocks)), 'description' => 'Page sequence'],
            ['label' => 'Rich Text', 'value' => RegionalFormats::adminNumber($counts['text']), 'description' => 'Text components'],
            ['label' => 'Media', 'value' => RegionalFormats::adminNumber($counts['image'] + $counts['video']), 'description' => 'Image and video components'],
            ['label' => 'Visits', 'value' => $this->metricValue($this->analytics['page']['visits'] ?? null), 'description' => 'This page · 30d'],
            ['label' => 'Views', 'value' => $this->metricValue($this->analytics['page']['views'] ?? null), 'description' => 'This page · 30d'],
            ['label' => 'Contact messages', 'value' => $this->metricValue($this->analytics['contact_messages'] ?? null), 'description' => 'Site-wide · 30d'],
        ];
    }
}
