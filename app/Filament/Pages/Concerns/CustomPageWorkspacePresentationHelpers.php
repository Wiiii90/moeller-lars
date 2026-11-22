<?php

namespace App\Filament\Pages\Concerns;

use App\Domain\Content\RegionalFormats;

use App\Domain\Content\PublicLegalContent;
use App\Domain\Content\SiteSectionType;
use App\Models\CustomPageSetting;
use App\Models\PublicContentSetting;
use App\Models\SiteSection;

trait CustomPageWorkspacePresentationHelpers
{
    /**
     * @param  array<string,mixed>  $block
     * @return array{primary:string,secondary:string,meta:string}
     */
    private function componentContent(CustomPageSetting $settings, array $block, ?string $imageName): array
    {
        $type = $block['type'] ?? null;
        if (in_array($type, CustomPageSetting::MEDIA_COMPONENT_TYPES, true)) {
            // The hierarchy owns children; the media cell identifies its asset.
            return ['primary' => $imageName ?: ($type === 'video' ? 'Video unavailable' : 'Image unavailable'), 'secondary' => '', 'meta' => ''];
        }
        if ($type === 'text') {
            $title = is_string($block['title'] ?? null) ? trim($block['title']) : '';
            $body = $this->contentExcerpt($block['body'] ?? null);

            return ['primary' => $title !== '' ? $title : $body, 'secondary' => $title !== '' ? $body : '', 'meta' => ''];
        }
        if ($type === 'layout') {
            $columns = (int) ($block['columns'] ?? 2);
            $rows = (int) ($block['rows'] ?? 1);
            $areaIds = array_column($block['areas'] ?? [], 'id');
            $placed = collect($settings->components())->filter(static fn (array $item): bool =>
                in_array($item['layout_area_id'] ?? null, $areaIds, true))->count();

            return [
                'primary' => $columns.' columns × '.$rows.' rows',
                'secondary' => $placed.' '.($placed === 1 ? 'component' : 'components').' placed',
                'meta' => '',
            ];
        }
        if (in_array($type, CustomPageSetting::EMBEDDED_COMPONENT_TYPES, true)) {
            $page = \App\Models\SiteSection::query()->find($block['source_page_id'] ?? null);

            return [
                'primary' => $page?->getAttribute('title') ?? 'Choose a source page',
                'secondary' => self::COMPONENT_LABELS[$type],
                'meta' => '',
            ];
        }
        if ($type === 'contact') {
            $enabled = [];
            foreach ($settings->contactChildren($block) as $child) {
                $type = $child['type'] ?? null;
                if (is_string($type) && CustomPageSetting::contactChildPublished($child)) {
                    $enabled[] = self::CONTACT_CHILD_LABELS[$type] ?? $type;
                }
            }
            $title = trim((string) ($block['title'] ?? ''));

            return [
                'primary' => $title !== '' ? $title : 'Contact',
                'secondary' => $enabled === [] ? 'No contact items enabled' : implode(', ', $enabled),
                'meta' => '',
            ];
        }
        if ($type === 'legal_disclaimer') {
            return [
                'primary' => 'Legal Disclaimer from General',
                'secondary' => PublicLegalContent::editorSummary(PublicContentSetting::general()),
                'meta' => '',
            ];
        }

        return ['primary' => '', 'secondary' => '', 'meta' => ''];
    }

    private function contentExcerpt(mixed $value, int $limit = 170): string
    {
        if (! is_string($value)) {
            return '';
        }
        $value = preg_replace('/!\[\]\(media:\d+\)/', '[Image]', $value) ?? $value;
        $text = preg_replace('/\s+/u', ' ', trim(strip_tags($value))) ?? '';
        if (mb_strlen($text) <= $limit) {
            return $text;
        }

        return rtrim(mb_substr($text, 0, max(1, $limit - 1))).'…';
    }

    private function componentParentSearchText(CustomPageSetting $settings, array $block, ?string $imageName): string
    {
        $type = is_string($block['type'] ?? null) ? $block['type'] : '';
        $parts = [self::COMPONENT_LABELS[$type] ?? $type, CustomPageSetting::componentPublished($block) ? 'Published' : 'Unpublished'];
        if (in_array($type, CustomPageSetting::MEDIA_COMPONENT_TYPES, true)) {
            $parts[] = $imageName;
        }
        if (in_array($type, CustomPageSetting::EMBEDDED_COMPONENT_TYPES, true)) {
            $parts[] = \App\Models\SiteSection::query()->whereKey($block['source_page_id'] ?? 0)->value('title');
        }
        if ($type === 'text') {
            $parts[] = $block['title'] ?? null;
            $parts[] = $block['body'] ?? null;

        } elseif ($type === 'contact') {
            $parts[] = implode(' ', array_map(
                static fn (array $child): string => self::CONTACT_CHILD_LABELS[(string) ($child['type'] ?? '')] ?? '',
                $settings->contactChildren($block),
            ));
        } elseif ($type === 'legal_disclaimer') {
            $parts[] = PublicLegalContent::editorSummary(PublicContentSetting::general());
        }

        return implode(' ', array_filter($parts, static fn (mixed $part): bool => is_string($part) && trim($part) !== ''));
    }

    /** The component table projects the canonical Public Layout hierarchy. */
    public function componentLayoutItems(): array
    {
        return CustomPageSetting::layoutProjection($this->settings()->components());
    }

    /** @return array<int,string> */
    private function parentOptions(): array
    {
        return SiteSection::query()
            ->whereNull('parent_id')
            ->whereKeyNot($this->sectionId)
            ->orderBy('position')
            ->orderBy('id')
            ->get()
            ->filter(static fn (SiteSection $section): bool => $section->canContainSitePages())
            ->mapWithKeys(static fn (SiteSection $section): array => [
                (int) $section->getKey() => (string) ($section->getAttribute('navigation_label') ?: $section->getAttribute('title')),
            ])
            ->all();
    }

    private function setPagination(int $total): void
    {
        $this->pageSize = $this->normalizePageSize($this->pageSize);
        $this->total = $total;
        $this->pages = max(1, (int) ceil($total / $this->pageSize));
        $this->page = min(max(1, $this->page), $this->pages);
    }

    private function normalizePageSize(mixed $value): int
    {
        $size = is_numeric($value) ? (int) $value : self::DEFAULT_PAGE_SIZE;

        return in_array($size, self::PAGE_SIZES, true) ? $size : self::DEFAULT_PAGE_SIZE;
    }

    private function retainVisibleSelections(): void
    {
        $visible = array_column($this->components, 'target');
        $this->selectedComponentTargets = array_values(array_intersect($this->selectedComponentTargets, $visible));
    }

    private function clearSelections(): void
    {
        $this->selectedComponentTargets = [];
    }

    private function metricValue(mixed $metric): string
    {
        if (! is_array($metric) || ($metric['state'] ?? null) !== 'available' || ! is_numeric($metric['value'] ?? null)) {
            return '—';
        }
        $value = (float) $metric['value'];

        return RegionalFormats::adminNumber($value, $value === floor($value) ? 0 : 1);
    }

    private function section(): SiteSection
    {
        /** @var SiteSection $section */
        $section = SiteSection::query()->whereKey($this->sectionId)->where('type', SiteSectionType::CustomPage->value)->firstOrFail();

        return $section;
    }

    private function settings(): CustomPageSetting
    {
        /** @var CustomPageSetting $settings */
        $settings = CustomPageSetting::query()->findOrFail($this->settingsId);

        return $settings;
    }
}
