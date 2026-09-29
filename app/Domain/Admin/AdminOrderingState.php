<?php

namespace App\Domain\Admin;

use App\Domain\Content\HomeTemplate;
use App\Models\Artwork;
use App\Models\ArtworkMedia;
use App\Models\BlogPost;
use App\Models\CustomPageSetting;
use App\Models\Exhibition;
use App\Models\HomePresentationSetting;
use App\Models\SiteSection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final class AdminOrderingState
{
    /**
     * @param list<int|string> $state
     * @return list<string>
     */
    public static function normalize(array $state): array
    {
        $normalized = [];
        foreach ($state as $value) {
            if (! is_int($value) && ! is_string($value)) {
                throw new \InvalidArgumentException('Ordering state values must be integer or string identities.');
            }

            $normalized[] = is_int($value) ? 'i:'.$value : 's:'.$value;
        }

        return $normalized;
    }

    /**
     * @param list<string> $state
     * @return list<int|string>
     */
    public static function denormalize(array $state): array
    {
        return array_map(static function (string $identity): int|string {
            if (preg_match('/^i:(\d+)$/', $identity, $matches) === 1) {
                return (int) $matches[1];
            }
            if (str_starts_with($identity, 's:')) {
                return substr($identity, 2);
            }

            throw new \InvalidArgumentException('Invalid canonical ordering identity.');
        }, $state);
    }

    /**
     * Stable identities for structured list items whose payload remains unchanged while only order changes.
     *
     * @param list<mixed> $items
     * @return list<string>
     */
    public static function fingerprints(array $items): array
    {
        $occurrences = [];
        $keys = [];

        foreach ($items as $item) {
            $payload = self::canonicalize($item);
            $hash = hash(
                'sha256',
                json_encode($payload, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE),
            );
            $occurrences[$hash] = ($occurrences[$hash] ?? 0) + 1;
            $keys[] = $hash.'#'.$occurrences[$hash];
        }

        return $keys;
    }

    /** @return list<string>|null */
    public function current(string $scope): ?array
    {
        if ($scope === 'site-navigation') {
            return self::normalize(
                SiteSection::query()
                    ->orderBy('id')
                    ->get(['id', 'parent_id', 'position'])
                    ->map(static function (SiteSection $section): string {
                        $parentId = $section->getAttribute('parent_id');

                        return (int) $section->getKey()
                            .':'.($parentId === null ? 'root' : (int) $parentId)
                            .':'.(int) $section->getAttribute('position');
                    })
                    ->values()
                    ->all(),
            );
        }

        if (preg_match('/^gallery-artworks:(\d+)$/', $scope, $matches) === 1) {
            return self::normalize(
                Artwork::query()
                    ->where('artwork_category_id', (int) $matches[1])
                    ->orderBy('position')
                    ->orderBy('id')
                    ->pluck('id')
                    ->map(static fn (mixed $id): int => (int) $id)
                    ->all(),
            );
        }

        if (preg_match('/^artwork-media:(\d+)$/', $scope, $matches) === 1) {
            return self::normalize(
                ArtworkMedia::query()
                    ->where('artwork_id', (int) $matches[1])
                    ->where('role', 'additional')
                    ->orderBy('position')
                    ->orderBy('id')
                    ->pluck('id')
                    ->map(static fn (mixed $id): int => (int) $id)
                    ->all(),
            );
        }

        if (preg_match('/^journal-(blog|exhibitions):(\d+)$/', $scope, $matches) === 1) {
            $model = $matches[1] === 'blog' ? BlogPost::class : Exhibition::class;

            return self::normalize(
                $model::query()
                    ->where('site_section_id', (int) $matches[2])
                    ->orderBy('position')
                    ->orderBy('id')
                    ->pluck('id')
                    ->map(static fn (mixed $id): int => (int) $id)
                    ->all(),
            );
        }

        if (preg_match('/^home-artworks:(\d+)$/', $scope, $matches) === 1) {
            $settings = HomePresentationSetting::query()
                ->where('site_section_id', (int) $matches[1])
                ->first();
            if (! $settings instanceof HomePresentationSetting) {
                return null;
            }
            $configuration = $settings->configuration();
            $artwork = is_array($configuration[HomeTemplate::Artwork->value] ?? null)
                ? $configuration[HomeTemplate::Artwork->value]
                : [];
            $group = is_array($artwork['manual_group'] ?? null) && array_is_list($artwork['manual_group'])
                ? $artwork['manual_group']
                : [];

            return self::normalize(array_values(array_filter(array_map(
                static fn (mixed $member): ?int => is_array($member) && is_int($member['artwork_id'] ?? null)
                    ? $member['artwork_id']
                    : null,
                $group,
            ), static fn (?int $id): bool => $id !== null)));
        }

        if (preg_match('/^home-components:(\d+):(under_construction|custom)$/', $scope, $matches) === 1) {
            $settings = HomePresentationSetting::query()
                ->where('site_section_id', (int) $matches[1])
                ->first();
            if (! $settings instanceof HomePresentationSetting) {
                return null;
            }
            $template = HomeTemplate::from($matches[2]);

            return self::normalize(self::fingerprints($settings->components($template)));
        }

        if (preg_match('/^page-components:(\d+)$/', $scope, $matches) === 1) {
            $settings = CustomPageSetting::query()
                ->where('site_section_id', (int) $matches[1])
                ->first();

            return $settings instanceof CustomPageSetting
                ? self::normalize(self::fingerprints($settings->components()))
                : null;
        }

        if (preg_match('/^page-(list|contact):(\d+):(\d+)$/', $scope, $matches) === 1) {
            $settings = CustomPageSetting::query()
                ->where('site_section_id', (int) $matches[2])
                ->first();
            if (! $settings instanceof CustomPageSetting) {
                return null;
            }
            $blocks = $settings->components();
            $index = (int) $matches[3];
            $block = $blocks[$index] ?? null;
            if (! is_array($block) || ($block['type'] ?? null) !== $matches[1]) {
                return null;
            }

            $items = $matches[1] === 'list'
                ? (is_array($block['items'] ?? null) && array_is_list($block['items']) ? $block['items'] : [])
                : $settings->contactChildren($block);

            return self::normalize(self::fingerprints($items));
        }

        return null;
    }

    /**
     * Restore one ordering scope only when its current canonical state still equals the expected projection end.
     *
     * @param list<string> $expectedCurrent
     * @param list<string> $target
     */
    public function restore(string $scope, array $expectedCurrent, array $target): void
    {
        if ($this->current($scope) !== $expectedCurrent) {
            $this->conflict();
        }

        if ($scope === 'site-navigation') {
            $this->restoreNavigation($target);
        } elseif (preg_match('/^gallery-artworks:(\d+)$/', $scope, $matches) === 1) {
            $this->restoreIdOrder('artworks', 'artwork_category_id', (int) $matches[1], $target, 0);
        } elseif (preg_match('/^artwork-media:(\d+)$/', $scope, $matches) === 1) {
            $this->restoreIdOrder('artwork_media', 'artwork_id', (int) $matches[1], $target, 1, ['role' => 'additional']);
        } elseif (preg_match('/^journal-(blog|exhibitions):(\d+)$/', $scope, $matches) === 1) {
            $this->restoreIdOrder(
                $matches[1] === 'blog' ? 'blog_posts' : 'exhibitions',
                'site_section_id',
                (int) $matches[2],
                $target,
                1,
            );
        } elseif (preg_match('/^home-artworks:(\d+)$/', $scope, $matches) === 1) {
            $this->restoreHomeArtworks((int) $matches[1], $target);
        } elseif (preg_match('/^home-components:(\d+):(under_construction|custom)$/', $scope, $matches) === 1) {
            $this->restoreHomeComponents((int) $matches[1], HomeTemplate::from($matches[2]), $target);
        } elseif (preg_match('/^page-components:(\d+)$/', $scope, $matches) === 1) {
            $this->restorePageComponents((int) $matches[1], $target);
        } elseif (preg_match('/^page-(list|contact):(\d+):(\d+)$/', $scope, $matches) === 1) {
            $this->restorePageChildren($matches[1], (int) $matches[2], (int) $matches[3], $target);
        } else {
            throw ValidationException::withMessages(['undo' => 'This ordering change has no canonical restore contract.']);
        }

        if ($this->current($scope) !== $target) {
            throw new \RuntimeException('Ordering Undo did not restore the expected canonical state.');
        }
    }

    /** @param list<string> $target */
    private function restoreNavigation(array $target): void
    {
        $rows = [];
        foreach ($target as $identity) {
            $raw = $this->stringIdentity($identity);
            if (preg_match('/^(\d+):(root|\d+):(\d+)$/', $raw, $matches) !== 1) {
                $this->conflict();
            }
            $rows[(int) $matches[1]] = [
                'parent_id' => $matches[2] === 'root' ? null : (int) $matches[2],
                'position' => (int) $matches[3],
            ];
        }

        $ids = array_keys($rows);
        $existing = SiteSection::query()->whereKey($ids)->pluck('id')->map(fn (mixed $id): int => (int) $id)->all();
        sort($ids);
        sort($existing);
        if ($ids !== $existing) {
            $this->conflict();
        }

        foreach ($rows as $id => $_row) {
            DB::table('site_sections')->where('id', $id)->update(['position' => 1000000 + $id]);
        }
        foreach ($rows as $id => $row) {
            DB::table('site_sections')->where('id', $id)->update([
                'parent_id' => $row['parent_id'],
                'position' => $row['position'],
                'updated_at' => now(),
            ]);
        }
    }

    /**
     * @param list<string> $target
     * @param array<string,mixed> $extraWhere
     */
    private function restoreIdOrder(
        string $table,
        string $ownerColumn,
        int $ownerId,
        array $target,
        int $positionBase,
        array $extraWhere = [],
    ): void {
        $ids = array_map(fn (string $identity): int => $this->integerIdentity($identity), $target);
        $query = DB::table($table)->where($ownerColumn, $ownerId);
        foreach ($extraWhere as $column => $value) {
            $query->where($column, $value);
        }
        $existing = $query->pluck('id')->map(fn (mixed $id): int => (int) $id)->all();
        $sortedIds = $ids;
        sort($sortedIds);
        sort($existing);
        if ($sortedIds !== $existing) {
            $this->conflict();
        }

        foreach ($ids as $index => $id) {
            DB::table($table)->where('id', $id)->update(['position' => 1000000 + $index]);
        }
        foreach ($ids as $index => $id) {
            DB::table($table)->where('id', $id)->update([
                'position' => $index + $positionBase,
                'updated_at' => now(),
            ]);
        }
    }

    /** @param list<string> $target */
    private function restoreHomeArtworks(int $sectionId, array $target): void
    {
        /** @var HomePresentationSetting $settings */
        $settings = HomePresentationSetting::query()->where('site_section_id', $sectionId)->lockForUpdate()->firstOrFail();
        $configuration = $settings->configuration();
        $artwork = is_array($configuration[HomeTemplate::Artwork->value] ?? null)
            ? $configuration[HomeTemplate::Artwork->value]
            : [];
        $group = is_array($artwork['manual_group'] ?? null) && array_is_list($artwork['manual_group'])
            ? $artwork['manual_group']
            : [];
        $byId = [];
        foreach ($group as $member) {
            if (is_array($member) && is_int($member['artwork_id'] ?? null)) {
                $byId[$member['artwork_id']] = $member;
            }
        }
        $ids = array_map(fn (string $identity): int => $this->integerIdentity($identity), $target);
        $currentIds = array_keys($byId);
        $sortedCurrent = $currentIds;
        $sortedTarget = $ids;
        sort($sortedCurrent);
        sort($sortedTarget);
        if ($sortedCurrent !== $sortedTarget) {
            $this->conflict();
        }
        $artwork['manual_group'] = array_map(static fn (int $id): array => $byId[$id], $ids);
        $configuration[HomeTemplate::Artwork->value] = $artwork;
        $settings->setAttribute('configuration', $configuration);
        $settings->save();
    }

    /** @param list<string> $target */
    private function restoreHomeComponents(int $sectionId, HomeTemplate $template, array $target): void
    {
        /** @var HomePresentationSetting $settings */
        $settings = HomePresentationSetting::query()->where('site_section_id', $sectionId)->lockForUpdate()->firstOrFail();
        $configuration = $settings->configuration();
        $components = $settings->components($template);
        $configuration[$template->value]['components'] = $this->reorderStructured($components, $target);
        $settings->setAttribute('configuration', $configuration);
        $settings->save();
    }

    /** @param list<string> $target */
    private function restorePageComponents(int $sectionId, array $target): void
    {
        /** @var CustomPageSetting $settings */
        $settings = CustomPageSetting::query()->where('site_section_id', $sectionId)->lockForUpdate()->firstOrFail();
        $settings->setAttribute('blocks', $this->reorderStructured($settings->components(), $target));
        $settings->save();
    }

    /** @param list<string> $target */
    private function restorePageChildren(string $kind, int $sectionId, int $index, array $target): void
    {
        /** @var CustomPageSetting $settings */
        $settings = CustomPageSetting::query()->where('site_section_id', $sectionId)->lockForUpdate()->firstOrFail();
        $blocks = $settings->components();
        $block = $blocks[$index] ?? null;
        if (! is_array($block) || ($block['type'] ?? null) !== $kind) {
            $this->conflict();
        }

        if ($kind === 'list') {
            $items = is_array($block['items'] ?? null) && array_is_list($block['items']) ? $block['items'] : [];
            $blocks[$index]['items'] = $this->reorderStructured($items, $target);
        } else {
            $children = $settings->contactChildren($block);
            $blocks[$index]['children'] = $this->reorderStructured($children, $target);
        }

        $settings->setAttribute('blocks', $blocks);
        $settings->save();
    }

    /**
     * @param list<mixed> $items
     * @param list<string> $target
     * @return list<mixed>
     */
    private function reorderStructured(array $items, array $target): array
    {
        $fingerprints = self::fingerprints($items);
        $byIdentity = [];
        foreach ($fingerprints as $index => $fingerprint) {
            $byIdentity[$fingerprint] = $items[$index];
        }

        $targetFingerprints = array_map(fn (string $identity): string => $this->stringIdentity($identity), $target);
        $currentKeys = array_keys($byIdentity);
        $expectedKeys = $targetFingerprints;
        sort($currentKeys);
        sort($expectedKeys);
        if ($currentKeys !== $expectedKeys) {
            $this->conflict();
        }

        return array_map(static fn (string $identity): mixed => $byIdentity[$identity], $targetFingerprints);
    }

    private function integerIdentity(string $identity): int
    {
        if (preg_match('/^i:(\d+)$/', $identity, $matches) !== 1) {
            $this->conflict();
        }

        return (int) $matches[1];
    }

    private function stringIdentity(string $identity): string
    {
        if (! str_starts_with($identity, 's:')) {
            $this->conflict();
        }

        return substr($identity, 2);
    }

    private static function canonicalize(mixed $value): mixed
    {
        if (! is_array($value)) {
            return $value;
        }

        if (array_is_list($value)) {
            return array_map(self::canonicalize(...), $value);
        }

        ksort($value);
        foreach ($value as $key => $nested) {
            $value[$key] = self::canonicalize($nested);
        }

        return $value;
    }

    private function conflict(): never
    {
        throw ValidationException::withMessages([
            'undo' => 'Undo is no longer available because this ordering changed afterwards.',
        ]);
    }
}
