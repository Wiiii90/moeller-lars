<?php

namespace App\Filament\Pages\Concerns;

use App\Domain\Content\SiteSectionType;
use App\Models\CustomPageSetting;
use App\Models\PublicContentSetting;
use App\Models\SiteSection;
use Illuminate\Validation\ValidationException;

trait CustomPageWorkspacePresentationHelpers
{
    /**
     * @param  array<string,mixed>  $block
     * @return array{primary:string,secondary:string,meta:string}
     */
    private function componentContent(CustomPageSetting $settings, array $block, ?string $imageName): array
    {
        $type = $block['type'] ?? null;
        if ($type === 'image') {
            $count = count(is_array($block['children'] ?? null) ? $block['children'] : []);

            return [
                'primary' => $imageName ?: 'Image unavailable',
                'secondary' => ($block['image_decorative'] ?? false) ? 'Decorative · wraps text' : '',
                'meta' => $count ? $count.' '.($count === 1 ? 'child' : 'children') : '',
            ];
        }
        if ($type === 'text') {
            $title = is_string($block['title'] ?? null) ? trim($block['title']) : '';
            $body = $this->contentExcerpt($block['body'] ?? null);

            return ['primary' => $title !== '' ? $title : $body, 'secondary' => $title !== '' ? $body : '', 'meta' => ''];
        }
        if ($type === 'list') {
            $title = is_string($block['title'] ?? null) ? trim($block['title']) : '';
            $items = is_array($block['items'] ?? null) ? array_values(array_filter($block['items'], 'is_array')) : [];

            return [
                'primary' => $title,
                'secondary' => $imageName !== null ? 'Image: '.$imageName : '',
                'meta' => count($items).' '.(count($items) === 1 ? 'entry' : 'entries'),
            ];
        }
        if ($type === 'divider') {
            $variant = is_string($block['variant'] ?? null) ? $block['variant'] : 'thin';

            return ['primary' => self::DIVIDER_LABELS[$variant] ?? self::DIVIDER_LABELS['thin'], 'secondary' => '', 'meta' => ''];
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
                'secondary' => $enabled === [] ? 'No contact items enabled' : implode(' · ', $enabled),
                'meta' => '',
            ];
        }
        if ($type === 'legal_disclaimer') {
            $text = PublicContentSetting::general()->getAttribute('legal_disclaimer');

            return ['primary' => 'General legal disclaimer', 'secondary' => $this->contentExcerpt($text, 240), 'meta' => ''];
        }

        return ['primary' => '', 'secondary' => '', 'meta' => ''];
    }

    /** @return list<array{type:string,title:string,meta:string,published:bool}> */
    private function imageChildSummaries(CustomPageSetting $settings, array $image): array
    {
        $summaries = [];
        foreach ($image['children'] ?? [] as $child) {
            if (! is_array($child)) {
                continue;
            }
            $type = (string) ($child['type'] ?? '');
            if (! in_array($type, CustomPageSetting::IMAGE_CHILD_TYPES, true)) {
                continue;
            }
            $content = $this->componentContent($settings, $child, null);
            $summaries[] = [
                'type' => self::COMPONENT_LABELS[$type] ?? $type,
                'title' => $content['primary'],
                'meta' => $content['meta'],
                'published' => CustomPageSetting::componentPublished($child),
            ];
        }

        return $summaries;
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
        if (in_array($type, ['image', 'list'], true)) {
            $parts[] = $imageName;
        }
        if ($type === 'image') {
            foreach ($block['children'] ?? [] as $child) {
                if (! is_array($child)) {
                    continue;
                }
                $parts[] = $this->componentParentSearchText($settings, $child, null);
                if (($child['type'] ?? null) === 'list') {
                    foreach ($child['items'] ?? [] as $item) {
                        if (is_array($item)) {
                            $parts[] = ($item['title'] ?? '').' '.(CustomPageSetting::listItemContext($item) ?? '');
                        }
                    }
                }
            }
        } elseif ($type === 'text') {
            $parts[] = $block['title'] ?? null;
            $parts[] = $block['body'] ?? null;
        } elseif ($type === 'list') {
            $parts[] = $block['title'] ?? null;
        } elseif ($type === 'divider') {
            $variant = is_string($block['variant'] ?? null) ? $block['variant'] : 'thin';
            $parts[] = self::DIVIDER_LABELS[$variant] ?? $variant;
        } elseif ($type === 'contact') {
            $parts[] = implode(' ', array_map(
                static fn (array $child): string => self::CONTACT_CHILD_LABELS[(string) ($child['type'] ?? '')] ?? '',
                $settings->contactChildren($block),
            ));
        } elseif ($type === 'legal_disclaimer') {
            $parts[] = PublicContentSetting::general()->getAttribute('legal_disclaimer');
        }

        return implode(' ', array_filter($parts, static fn (mixed $part): bool => is_string($part) && trim($part) !== ''));
    }

    /** @return list<string> */
    private function validatedSocialPlatforms(mixed $platforms): array
    {
        if (! is_array($platforms)) {
            return [];
        }
        $selected = [];
        foreach ($platforms as $platform) {
            if (! is_string($platform) || ! array_key_exists($platform, $this->availableSocialPlatforms)) {
                throw ValidationException::withMessages(['social_platforms' => 'Choose only social links currently available in General.']);
            }
            $selected[] = $platform;
        }

        return array_values(array_unique($selected));
    }

    /** @return array<int,string> */
    private function parentOptions(): array
    {
        if (! \App\Models\PublicContentSetting::navigationNestingEnabled()) {
            return [];
        }

        return SiteSection::query()
            ->whereNull('parent_id')
            ->whereKeyNot($this->sectionId)
            ->orderBy('position')
            ->orderBy('id')
            ->get()
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
        $visibleParents = collect($this->components)->pluck('target')->filter(static fn (mixed $target): bool => is_string($target))->all();
        $this->selectedComponentTargets = array_values(array_intersect($this->selectedComponentTargets, $visibleParents));
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

        return number_format($value, $value === floor($value) ? 0 : 1);
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
