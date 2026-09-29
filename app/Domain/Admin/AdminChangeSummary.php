<?php

namespace App\Domain\Admin;

use Illuminate\Support\Str;
use JsonException;

final class AdminChangeSummary
{
    private const MAX_ITEMS = 12;

    private const IGNORED_FIELDS = [
        'id',
        'created_at',
        'updated_at',
        'legacy_id',
        'legacy_source',
        'legacy_path',
        'legacy_filename',
        'legacy_byte_size',
        'migration_batch_id',
        'migrated_at',
    ];

    private const FIELD_LABELS = [
        'background_mode' => 'Background',
        'background_color' => 'Background color',
        'background_gradient_start' => 'Background gradient start color',
        'background_gradient_end' => 'Background gradient end color',
        'background_gradient_angle' => 'Background gradient angle',
        'public_page_width' => 'Public page width',
        'public_content_padding' => 'Public content padding',
        'navigation_nesting_enabled' => 'Navigation nesting',
        'contact_recipient_email' => 'Contact form recipient',
        'public_email' => 'Public email',
        'show_public_email' => 'Public email visibility',
        'instagram_handle' => 'Instagram handle',
        'show_instagram' => 'Instagram visibility',
        'social_links' => 'Social links',
        'legal_disclaimer' => 'Legal disclaimer',
        'favicon_media_asset_id' => 'Favicon',
        'default_media_copyright_notice' => 'Default media copyright',
        'show_in_navigation' => 'Navigation visibility',
        'navigation_label' => 'Navigation label',
        'parent_id' => 'Parent page',
        'position' => 'Position',
        'state' => 'Status',
        'slug' => 'URL slug',
        'title' => 'Title',
        'name' => 'Name',
        'template' => 'Template',
        'configuration' => 'Home presentation',
        'skip_home' => 'Skip Home',
        'skip_target_section_id' => 'Skip Home target',
        'blocks' => 'Components',
        'listing_title' => 'Listing title',
        'listing_intro' => 'Listing introduction',
        'body' => 'Body',
        'excerpt' => 'Excerpt',
        'description' => 'Description',
        'medium' => 'Medium',
        'dimensions' => 'Dimensions',
        'work_date' => 'Artwork date',
        'work_year' => 'Artwork year',
        'date_precision' => 'Date precision',
        'published_at' => 'Published at',
        'scheduled_at' => 'Scheduled at',
        'starts_on' => 'Start date',
        'ends_on' => 'End date',
        'date_text' => 'Displayed date',
        'venue' => 'Venue',
        'venue_url' => 'Venue website',
        'city' => 'City',
        'country' => 'Country',
        'location_text' => 'Street address',
        'external_url' => 'External link',
        'map_enabled' => 'Map visibility',
        'map_shape' => 'Map shape',
        'gallery_enabled' => 'Gallery visibility',
        'alt_text' => 'ALT text',
        'alt_text_override' => 'ALT text override',
        'copyright_notice' => 'Copyright notice',
        'copyright_notice_mode' => 'Copyright mode',
        'credit' => 'Credit',
        'focal_point_x' => 'Focal point X',
        'focal_point_y' => 'Focal point Y',
        'media_asset_id' => 'Media file',
        'artwork_category_id' => 'Gallery',
        'cover_media_asset_id' => 'Cover image',
        'hero_media_asset_id' => 'Hero image',
        'published' => 'Visibility',
        'type' => 'Type',
        'variant' => 'Style',
        'image_decorative' => 'Decorative image',
        'form_state' => 'Contact form state',
        'status_text' => 'Status text',
        'url' => 'URL',
    ];

    /**
     * @param  list<array{entity_type:string,table:string,row_id:int,before:?array<string,mixed>,after:?array<string,mixed>}>|null  $snapshots
     * @return array{count:int,truncated:bool,items:list<array{field:string,label:string,before:string,after:string}>}|null
     */
    public function summarize(?array $snapshots): ?array
    {
        if ($snapshots === null || $snapshots === []) {
            return null;
        }

        $items = [];
        foreach ($snapshots as $snapshot) {
            $before = is_array($snapshot['before'] ?? null) ? $snapshot['before'] : null;
            $after = is_array($snapshot['after'] ?? null) ? $snapshot['after'] : null;

            if ($before === null || $after === null) {
                $this->append(
                    $items,
                    field: $snapshot['table'],
                    label: $this->tableLabel($snapshot['table']),
                    before: $before === null ? 'Not present' : 'Present',
                    after: $after === null ? 'Removed' : 'Created',
                );

                continue;
            }

            $keys = array_values(array_unique([...array_keys($before), ...array_keys($after)]));
            sort($keys);

            foreach ($keys as $key) {
                if ($this->ignored((string) $key)) {
                    continue;
                }

                $left = $this->structured($before[$key] ?? null);
                $right = $this->structured($after[$key] ?? null);
                if ($this->equivalent($left, $right)) {
                    continue;
                }

                $this->diffValue([(string) $key], $left, $right, $items);
            }
        }

        if ($items === []) {
            return null;
        }

        return [
            'count' => count($items),
            'truncated' => count($items) > self::MAX_ITEMS,
            'items' => array_slice($items, 0, self::MAX_ITEMS),
        ];
    }

    /**
     * @param  array{count:int,truncated:bool,items:list<array{field:string,label:string,before:string,after:string}>}  $summary
     * @return array{count:int,truncated:bool,items:list<array{field:string,label:string,before:string,after:string}>}
     */
    public function reverse(array $summary): array
    {
        $items = array_map(
            static fn (array $item): array => [
                'field' => $item['field'],
                'label' => $item['label'],
                'before' => $item['after'],
                'after' => $item['before'],
            ],
            $summary['items'],
        );

        return [
            'count' => max(count($items), $summary['count']),
            'truncated' => $summary['truncated'],
            'items' => $items,
        ];
    }

    /**
     * @param  list<string>  $path
     * @param  list<array{field:string,label:string,before:string,after:string}>  $items
     */
    private function diffValue(array $path, mixed $before, mixed $after, array &$items): void
    {
        if ($this->equivalent($before, $after)) {
            return;
        }

        if (is_array($before) && is_array($after)) {
            if (array_is_list($before) && array_is_list($after)) {
                $this->diffList($path, $before, $after, $items);

                return;
            }

            if (array_is_list($before) === false && array_is_list($after) === false) {
                $keys = array_values(array_unique([...array_keys($before), ...array_keys($after)]));
                sort($keys);
                foreach ($keys as $key) {
                    $this->diffValue(
                        [...$path, (string) $key],
                        $this->structured($before[$key] ?? null),
                        $this->structured($after[$key] ?? null),
                        $items,
                    );
                }

                return;
            }
        }

        $this->append(
            $items,
            field: implode('.', $path),
            label: $this->pathLabel($path),
            before: $this->displayValue($before),
            after: $this->displayValue($after),
        );
    }

    /**
     * @param  list<string>  $path
     * @param  list<mixed>  $before
     * @param  list<mixed>  $after
     * @param  list<array{field:string,label:string,before:string,after:string}>  $items
     */
    private function diffList(array $path, array $before, array $after, array &$items): void
    {
        if (count($after) === count($before) + 1) {
            $index = $this->singleInsertionIndex($before, $after);
            if ($index !== null) {
                $this->append(
                    $items,
                    field: implode('.', $path),
                    label: $this->pathLabel($path),
                    before: count($before).' item'.(count($before) === 1 ? '' : 's'),
                    after: count($after).' items · added '.$this->listItemLabel($after[$index]).' at position '.($index + 1),
                );

                return;
            }
        }

        if (count($before) === count($after) + 1) {
            $index = $this->singleInsertionIndex($after, $before);
            if ($index !== null) {
                $this->append(
                    $items,
                    field: implode('.', $path),
                    label: $this->pathLabel($path),
                    before: count($before).' items · '.$this->listItemLabel($before[$index]).' at position '.($index + 1),
                    after: count($after).' item'.(count($after) === 1 ? '' : 's').' · removed',
                );

                return;
            }
        }

        if (count($before) === count($after) && $before !== [] && $this->sameListMembers($before, $after)) {
            $this->append(
                $items,
                field: implode('.', [...$path, 'order']),
                label: $this->pathLabel([...$path, 'order']),
                before: $this->listOrder($before),
                after: $this->listOrder($after),
            );

            return;
        }

        if (count($before) === count($after)) {
            foreach (array_keys($before) as $index) {
                $this->diffValue(
                    [...$path, (string) $index],
                    $this->structured($before[$index]),
                    $this->structured($after[$index]),
                    $items,
                );
            }

            return;
        }

        $this->append(
            $items,
            field: implode('.', $path),
            label: $this->pathLabel($path),
            before: count($before).' item'.(count($before) === 1 ? '' : 's'),
            after: count($after).' item'.(count($after) === 1 ? '' : 's'),
        );
    }

    /**
     * @param  list<mixed>  $shorter
     * @param  list<mixed>  $longer
     */
    private function singleInsertionIndex(array $shorter, array $longer): ?int
    {
        for ($candidate = 0, $count = count($longer); $candidate < $count; $candidate++) {
            $copy = $longer;
            array_splice($copy, $candidate, 1);
            if ($this->equivalent($shorter, $copy)) {
                return $candidate;
            }
        }

        return null;
    }

    /**
     * @param  list<mixed>  $left
     * @param  list<mixed>  $right
     */
    private function sameListMembers(array $left, array $right): bool
    {
        $hashes = static fn (array $values): array => array_map(
            static fn (mixed $value): string => hash('sha256', json_encode($value, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE)),
            $values,
        );

        $leftHashes = $hashes($left);
        $rightHashes = $hashes($right);
        sort($leftHashes);
        sort($rightHashes);

        return $leftHashes === $rightHashes && $this->listOrder($left) !== $this->listOrder($right);
    }

    /** @param  list<mixed>  $values */
    private function listOrder(array $values): string
    {
        return collect($values)
            ->map(fn (mixed $value): string => $this->listItemLabel($value))
            ->implode(' → ');
    }

    private function listItemLabel(mixed $value): string
    {
        if (is_array($value) === false) {
            return $this->displayValue($value);
        }

        $type = is_string($value['type'] ?? null) ? $value['type'] : null;
        $title = is_string($value['title'] ?? null) ? trim($value['title']) : '';
        $label = $type === null ? 'item' : match ($type) {
            'text' => 'Rich Text',
            'image' => 'Image',
            'list' => 'List',
            'divider' => 'Divider',
            'contact' => 'Contact',
            'legal_disclaimer' => 'Legal Disclaimer',
            'public_email' => 'Public email',
            'social_links' => 'Social links',
            'contact_form' => 'Contact form',
            default => Str::headline($type),
        };

        return $title === '' ? $label : $label.' “'.Str::limit($title, 50, '…').'”';
    }

    /** @param  list<string>  $path */
    private function pathLabel(array $path): string
    {
        $labels = [];
        foreach ($path as $index => $segment) {
            if (ctype_digit($segment)) {
                $previous = $path[$index - 1] ?? '';
                $noun = in_array($previous, ['blocks', 'components'], true)
                    ? 'Component'
                    : (in_array($previous, ['items', 'children'], true) ? 'Item' : 'Entry');
                $labels[] = $noun.' '.((int) $segment + 1);

                continue;
            }

            if ($segment === 'configuration') {
                $labels[] = 'Home presentation';

                continue;
            }
            if ($segment === 'blocks' || $segment === 'components') {
                $labels[] = 'Components';

                continue;
            }
            if ($segment === 'items') {
                $labels[] = 'List entries';

                continue;
            }
            if ($segment === 'children') {
                $labels[] = 'Contact items';

                continue;
            }
            if ($segment === 'order') {
                $labels[] = 'Order';

                continue;
            }

            $labels[] = self::FIELD_LABELS[$segment] ?? Str::headline($segment);
        }

        return implode(' · ', array_values(array_unique($labels)));
    }

    private function tableLabel(string $table): string
    {
        return match ($table) {
            'custom_page_settings' => 'Custom Page',
            'home_presentation_settings' => 'Home presentation',
            'journal_settings' => 'Journal settings',
            'journal_entry_media' => 'Journal media',
            'artwork_media' => 'Artwork media',
            'media_assets' => 'Media file',
            'media_variants' => 'Media variant',
            'public_content_settings' => 'Website settings',
            default => Str::headline(Str::singular($table)),
        };
    }

    private function displayValue(mixed $value): string
    {
        if ($value === null) {
            return 'None';
        }
        if (is_bool($value)) {
            return $value ? 'On' : 'Off';
        }
        if (is_int($value) || is_float($value)) {
            return (string) $value;
        }
        if (is_string($value)) {
            $value = trim(preg_replace('/\s+/u', ' ', $value) ?? $value);
            if ($value === '') {
                return 'Empty';
            }
            if (mb_strlen($value) > 120) {
                return 'Text ('.mb_strlen($value).' characters)';
            }

            return $value;
        }
        if (is_array($value)) {
            if (array_is_list($value)) {
                return count($value).' item'.(count($value) === 1 ? '' : 's');
            }

            return count($value).' field'.(count($value) === 1 ? '' : 's');
        }

        return Str::limit((string) $value, 120, '…');
    }

    private function structured(mixed $value): mixed
    {
        if (is_string($value) === false) {
            return $value;
        }

        $trimmed = trim($value);
        if ($trimmed === '' || in_array($trimmed[0] ?? '', ['[', '{'], true) === false) {
            return $value;
        }

        try {
            $decoded = json_decode($trimmed, true, flags: JSON_THROW_ON_ERROR);

            return is_array($decoded) ? $decoded : $value;
        } catch (JsonException) {
            return $value;
        }
    }

    private function equivalent(mixed $left, mixed $right): bool
    {
        return $this->canonical($left) === $this->canonical($right);
    }

    private function canonical(mixed $value): string
    {
        return json_encode($value, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    }

    private function ignored(string $field): bool
    {
        return in_array($field, self::IGNORED_FIELDS, true);
    }

    /**
     * @param  list<array{field:string,label:string,before:string,after:string}>  $items
     */
    private function append(array &$items, string $field, string $label, string $before, string $after): void
    {
        $items[] = compact('field', 'label', 'before', 'after');
    }
}
