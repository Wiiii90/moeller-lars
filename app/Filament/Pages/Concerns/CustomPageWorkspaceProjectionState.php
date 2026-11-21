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

        // Targets are checked against the current rendered table projection
        // before they become Public document anchors.
        if (! preg_match('/^(\\d+)(?:\\.(\\d+))?:[a-z_]+$/', $this->customPreviewFocusedTarget, $matches)) {
            return $this->previewUrl;
        }

        // Preserve the existing Public image anchor; all other component
        // roots share the same positional hash grammar, including Image children.
        $hasChild = ($matches[2] ?? '') !== '';
        $anchor = ($matches[3] === 'image' && ! $hasChild)
            ? 'custom-page-image-'.$matches[1]
            : 'custom-page-component-page-'.$matches[1]
                .($hasChild ? '-'.$matches[2] : '');

        return $this->previewUrl.'#'.$anchor;
    }

    private function syncCustomPreviewFocus(bool $forceRefresh = false): void
    {
        $validTargets = collect($this->components)
            ->flatMap(static fn (array $row): array => [
                $row['target'], ...array_column($row['children'] ?? [], 'target'),
            ])
            ->all();
        $selected = array_values(array_intersect($this->selectedComponentTargets, $validTargets));
        $next = $selected === [] ? null : end($selected);
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
        $childComponentCount = 0;
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
                $childComponentCount += count($block['children'] ?? []);
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
                'children' => $type === 'image' ? $this->imageChildSummaries($settings, $block, $index) : [],
                'accepts_children' => $type === 'image' && ($block['image_decorative'] ?? false) === true,
                'can_nest' => in_array($type, CustomPageSetting::IMAGE_CHILD_TYPES, true)
                    && ($type !== 'list' || ($block['media_asset_id'] ?? null) === null)
                    && ($type !== 'contact' || ! collect($settings->contactChildren($block))
                        ->contains(fn (array $item): bool => ($item['type'] ?? null) === 'contact_form'
                            && CustomPageSetting::contactChildPublished($item))),
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
            ['label' => 'Components', 'value' => RegionalFormats::adminNumber(count($blocks) + $childComponentCount), 'description' => 'Page sequence'],
            ['label' => 'Entries', 'value' => RegionalFormats::adminNumber($listEntryCount), 'description' => 'List entries'],
            ['label' => 'Images', 'value' => RegionalFormats::adminNumber($counts['image']), 'description' => 'Image components'],
            ['label' => 'Visits', 'value' => $this->metricValue($this->analytics['page']['visits'] ?? null), 'description' => 'This page · 30d'],
            ['label' => 'Views', 'value' => $this->metricValue($this->analytics['page']['views'] ?? null), 'description' => 'This page · 30d'],
            ['label' => 'Contact messages', 'value' => $this->metricValue($this->analytics['contact_messages'] ?? null), 'description' => 'Site-wide · 30d'],
        ];
    }
}
