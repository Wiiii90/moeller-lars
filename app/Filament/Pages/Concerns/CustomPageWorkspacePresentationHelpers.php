<?php

namespace App\Filament\Pages\Concerns;

use App\Domain\Content\SiteNodeType;
use App\Models\CustomPageSetting;
use App\Models\PublicContentSetting;
use App\Models\SiteSection;
use Illuminate\Validation\ValidationException;

trait CustomPageWorkspacePresentationHelpers
{
    /**
     * @param array<string,mixed> $block
     * @return array{primary:string,secondary:string,meta:string}
     */
    private function componentContent(CustomPageSetting $settings, array $block, ?string $imageName): array
    {
        $type = $block['type'] ?? null;
        if ($type === 'image') {
            return ['primary' => $imageName ?: 'Image unavailable', 'secondary' => '', 'meta' => ''];
        }
        if ($type === 'text') {
            $title = is_string($block['title'] ?? null) ? trim($block['title']) : '';
            $body = $this->contentExcerpt($block['body'] ?? null);

            return ['primary' => $title !== '' ? $title : $body, 'secondary' => $title !== '' ? $body : '', 'meta' => ''];
        }
        if ($type === 'list') {
            $title = is_string($block['title'] ?? null) ? trim($block['title']) : '';
            $items = is_array($block['items'] ?? null) ? array_values(array_filter($block['items'], 'is_array')) : [];

            return ['primary' => $title, 'secondary' => '', 'meta' => count($items).' '.(count($items) === 1 ? 'entry' : 'entries')];
        }
        if ($type === 'cv_list') {
            return [
                'primary' => 'Canonical CV entries',
                'secondary' => $imageName !== null ? 'Image: '.$imageName : '',
                'meta' => $this->cvEntryCount.' '.($this->cvEntryCount === 1 ? 'entry' : 'entries'),
            ];
        }
        if ($type === 'divider') {
            $variant = is_string($block['variant'] ?? null) ? $block['variant'] : 'thin';

            return ['primary' => self::DIVIDER_LABELS[$variant] ?? self::DIVIDER_LABELS['thin'], 'secondary' => '', 'meta' => ''];
        }
        if ($type === 'contact') {
            $count = count($settings->contactChildren($block));

            return ['primary' => 'Contact', 'secondary' => '', 'meta' => $count.' '.($count === 1 ? 'item' : 'items')];
        }
        if ($type === 'legal_disclaimer') {
            $text = PublicContentSetting::general()->getAttribute('legal_disclaimer');

            return ['primary' => 'General legal disclaimer', 'secondary' => $this->contentExcerpt($text, 120), 'meta' => 'Canonical General setting'];
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
        if (in_array($type, ['image', 'cv_list'], true)) {
            $parts[] = $imageName;
        }
        if ($type === 'text') {
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

    private function socialChildDetail(array $child): string
    {
        $platforms = is_array($child['social_platforms'] ?? null) ? $child['social_platforms'] : [];
        $labels = array_values(array_filter(array_map(
            fn (mixed $platform): ?string => is_string($platform) ? ($this->availableSocialPlatforms[$platform] ?? null) : null,
            $platforms,
        )));

        return $labels === [] ? 'No social links selected' : implode(', ', $labels);
    }

    /** @return array<string,string> */
    private function availableContactChildOptions(array $arguments): array
    {
        try {
            $block = $this->actionComponent($arguments);
        } catch (ValidationException) {
            return self::CONTACT_CHILD_LABELS;
        }
        if (($block['type'] ?? null) !== 'contact') {
            return [];
        }
        $existing = collect($this->settings()->contactChildren($block))
            ->pluck('type')
            ->filter(static fn (mixed $value): bool => is_string($value))
            ->all();

        return array_filter(
            self::CONTACT_CHILD_LABELS,
            static fn (string $label, string $type): bool => ! in_array($type, $existing, true),
            ARRAY_FILTER_USE_BOTH,
        );
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
        return SiteSection::query()
            ->whereNull('parent_id')
            ->whereKeyNot($this->sectionId)
            ->orderBy('position')
            ->orderBy('id')
            ->get()
            ->filter(static fn (SiteSection $section): bool => $section->canContainChildren())
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
        $visibleParents = collect($this->components)
            ->pluck('target')
            ->filter(static fn (mixed $target): bool => is_string($target))
            ->all();
        $visibleChildren = collect($this->components)
            ->flatMap(static fn (array $component): array => is_array($component['children'] ?? null) ? $component['children'] : [])
            ->pluck('target')
            ->filter(static fn (mixed $target): bool => is_string($target))
            ->all();

        $this->selectedComponentTargets = array_values(array_intersect($this->selectedComponentTargets, $visibleParents));
        $this->selectedChildTargets = array_values(array_intersect($this->selectedChildTargets, $visibleChildren));
    }

    private function clearSelections(): void
    {
        $this->selectedComponentTargets = [];
        $this->selectedChildTargets = [];
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
        $section = SiteSection::query()
            ->whereKey($this->sectionId)
            ->where('type', SiteNodeType::CustomPage->value)
            ->firstOrFail();

        return $section;
    }

    private function settings(): CustomPageSetting
    {
        /** @var CustomPageSetting $settings */
        $settings = CustomPageSetting::query()->findOrFail($this->settingsId);

        return $settings;
    }
}
