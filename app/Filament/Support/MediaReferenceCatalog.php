<?php

namespace App\Filament\Support;

use App\Domain\Content\JournalTemplate;
use App\Domain\Content\SiteNodeType;
use App\Filament\Resources\PublicContentSettings\PublicContentSettingResource;
use App\Models\ArtworkCategory;
use App\Models\CustomPageSetting;
use App\Models\CvEntry;
use App\Models\MediaAsset;
use App\Models\SiteSection;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;

final class MediaReferenceCatalog
{
    /** @var EloquentCollection<int, SiteSection>|null */
    private ?EloquentCollection $nodes = null;

    /** @var list<int>|null */
    private ?array $directCustomMediaIds = null;

    public function __construct(private readonly SiteNodePresentation $presentation) {}

    /** @return list<array{label:string,options:list<array{value:string,label:string}>}> */
    public function destinationGroups(): array
    {
        $groups = [
            SiteNodeType::Gallery->value => ['label' => 'Galleries', 'options' => []],
            SiteNodeType::Journal->value => ['label' => 'Journals', 'options' => []],
            SiteNodeType::CustomPage->value => ['label' => 'Custom pages', 'options' => []],
        ];

        foreach ($this->nodes() as $node) {
            $type = $node->nodeType();
            if (! isset($groups[$type->value])) {
                continue;
            }

            $groups[$type->value]['options'][] = [
                'value' => 'node:'.$node->getKey(),
                'label' => $this->nodeLabel($node),
            ];
        }

        $groups['site'] = [
            'label' => 'Site',
            'options' => [[
                'value' => 'site-identity',
                'label' => 'Site identity',
            ]],
        ];

        return array_values(array_filter(
            $groups,
            static fn (array $group): bool => $group['options'] !== [],
        ));
    }

    /** @param Builder<MediaAsset> $query */
    public function applyDestinationFilter(Builder $query, string $destination): void
    {
        if ($destination === 'all') {
            return;
        }

        if ($destination === 'site-identity') {
            $query->whereHas('siteIdentitySettings');

            return;
        }

        if ($destination === 'unassigned') {
            $this->applyReferenceFilter($query, false);

            return;
        }

        $node = $this->nodeForDestination($destination);
        if (! $node instanceof SiteSection) {
            $query->whereRaw('1 = 0');

            return;
        }

        match ($node->nodeType()) {
            SiteNodeType::Gallery => $this->applyGalleryDestination($query, $node),
            SiteNodeType::Journal => $this->applyJournalDestination($query, $node),
            SiteNodeType::CustomPage => $this->applyCustomPageDestination($query, $node),
            default => $query->whereRaw('1 = 0'),
        };
    }

    /** @param Builder<MediaAsset> $query */
    public function applyReferenceFilter(Builder $query, bool $referenced): void
    {
        $relations = ['artworks', 'exhibitions', 'cvEntries', 'blogPosts', 'siteIdentitySettings'];
        $directCustomIds = $this->directCustomMediaIds();

        if (! $referenced) {
            foreach ($relations as $relation) {
                $query->whereDoesntHave($relation);
            }
            if ($directCustomIds !== []) {
                $query->whereNotIn('media_assets.id', $directCustomIds);
            }

            return;
        }

        $query->where(function (Builder $references) use ($relations, $directCustomIds): void {
            foreach ($relations as $index => $relation) {
                $index === 0
                    ? $references->whereHas($relation)
                    : $references->orWhereHas($relation);
            }
            if ($directCustomIds !== []) {
                $references->orWhereIn('media_assets.id', $directCustomIds);
            }
        });
    }

    /** @param Builder<MediaAsset> $query */
    public function eagerLoad(Builder $query): void
    {
        $query->with([
            'variants',
            'artworks.category.siteSection',
            'exhibitions.siteSection',
            'blogPosts.siteSection',
            'cvEntries',
            'siteIdentitySettings',
        ]);
    }

    public function loadAssetReferences(MediaAsset $asset): void
    {
        $asset->loadMissing([
            'artworks.category.siteSection',
            'exhibitions.siteSection',
            'blogPosts.siteSection',
            'cvEntries',
            'siteIdentitySettings',
        ]);
    }

    /** @return list<array{type:string,label:string,url:?string}> */
    public function references(MediaAsset $asset): array
    {
        $rows = [];

        foreach ($asset->getRelation('artworks') as $artwork) {
            /** @var ArtworkCategory|null $category */
            $category = $artwork->getRelationValue('category');
            $node = $category?->getRelationValue('siteSection');
            $galleryLabel = $node instanceof SiteSection
                ? $this->nodeLabel($node)
                : trim((string) ($category?->getAttribute('name') ?? 'Gallery'));

            $rows[] = [
                'type' => 'Gallery: '.$galleryLabel,
                'label' => (string) $artwork->getAttribute('title'),
                'url' => $node instanceof SiteSection ? $this->presentation->workspaceUrl($node) : null,
            ];
        }

        foreach ($asset->getRelation('exhibitions') as $exhibition) {
            $node = $exhibition->getRelationValue('siteSection');
            $journalLabel = $node instanceof SiteSection ? $this->nodeLabel($node) : 'Exhibitions';
            $rows[] = [
                'type' => 'Journal: '.$journalLabel,
                'label' => (string) $exhibition->getAttribute('title'),
                'url' => $node instanceof SiteSection ? $this->presentation->workspaceUrl($node) : null,
            ];
        }

        foreach ($asset->getRelation('blogPosts') as $post) {
            $node = $post->getRelationValue('siteSection');
            $journalLabel = $node instanceof SiteSection ? $this->nodeLabel($node) : 'Blog';
            $rows[] = [
                'type' => 'Journal: '.$journalLabel,
                'label' => (string) $post->getAttribute('title'),
                'url' => $node instanceof SiteSection ? $this->presentation->workspaceUrl($node) : null,
            ];
        }

        foreach ($this->customPageNodes() as $node) {
            $settings = $node->getRelationValue('customPageSetting');
            if (! $settings instanceof CustomPageSetting) {
                continue;
            }

            if ($this->customPageReferencesAsset($settings, (int) $asset->getKey())) {
                $rows[] = [
                    'type' => 'Custom Page: '.$this->nodeLabel($node),
                    'label' => 'Image component',
                    'url' => $this->presentation->workspaceUrl($node),
                ];
            }

            if (! $this->hasCvList($settings)) {
                continue;
            }

            foreach ($asset->getRelation('cvEntries') as $entry) {
                $rows[] = [
                    'type' => 'Custom Page: '.$this->nodeLabel($node),
                    'label' => (string) $entry->getAttribute('title'),
                    'url' => $this->presentation->workspaceUrl($node),
                ];
            }
        }

        foreach ($asset->getRelation('siteIdentitySettings') as $setting) {
            $rows[] = [
                'type' => 'Site identity',
                'label' => 'Favicon',
                'url' => PublicContentSettingResource::getUrl('edit', ['record' => $setting->getKey()]),
            ];
        }

        $unique = [];
        foreach ($rows as $row) {
            $key = implode('|', [$row['type'], $row['label'], $row['url'] ?? '']);
            $unique[$key] = $row;
        }

        return array_values($unique);
    }

    /** @return EloquentCollection<int, SiteSection> */
    private function nodes(): EloquentCollection
    {
        if ($this->nodes instanceof EloquentCollection) {
            return $this->nodes;
        }

        /** @var EloquentCollection<int, SiteSection> $nodes */
        $nodes = SiteSection::query()
            ->with('customPageSetting')
            ->orderBy('position')
            ->orderBy('id')
            ->get();

        return $this->nodes = $nodes;
    }

    /** @return EloquentCollection<int, SiteSection> */
    private function customPageNodes(): EloquentCollection
    {
        return $this->nodes()->filter(
            static fn (SiteSection $node): bool => $node->nodeType() === SiteNodeType::CustomPage,
        );
    }

    private function nodeForDestination(string $destination): ?SiteSection
    {
        if (! preg_match('/^node:(\d+)$/', $destination, $matches)) {
            return null;
        }

        return $this->nodes()->first(
            static fn (SiteSection $node): bool => (int) $node->getKey() === (int) $matches[1],
        );
    }

    /** @param Builder<MediaAsset> $query */
    private function applyGalleryDestination(Builder $query, SiteSection $node): void
    {
        $categoryId = $node->getAttribute('artwork_category_id');
        if (! is_numeric($categoryId)) {
            $query->whereRaw('1 = 0');

            return;
        }

        $query->whereHas('artworks', static function (Builder $artworks) use ($categoryId): void {
            $artworks->where('artwork_category_id', (int) $categoryId);
        });
    }

    /** @param Builder<MediaAsset> $query */
    private function applyJournalDestination(Builder $query, SiteSection $node): void
    {
        match ($node->journalTemplate()) {
            JournalTemplate::Blog => $query->whereHas('blogPosts', static function (Builder $posts) use ($node): void {
                $posts->where('site_section_id', $node->getKey());
            }),
            JournalTemplate::Exhibitions => $query->whereHas('exhibitions', static function (Builder $exhibitions) use ($node): void {
                $exhibitions->where('site_section_id', $node->getKey());
            }),
            null => $query->whereRaw('1 = 0'),
        };
    }

    /** @param Builder<MediaAsset> $query */
    private function applyCustomPageDestination(Builder $query, SiteSection $node): void
    {
        $settings = $node->getRelationValue('customPageSetting');
        if (! $settings instanceof CustomPageSetting) {
            $query->whereRaw('1 = 0');

            return;
        }

        $mediaIds = [];
        foreach ($settings->components() as $component) {
            if (($component['type'] ?? null) === 'image' && is_numeric($component['media_asset_id'] ?? null)) {
                $mediaIds[] = (int) $component['media_asset_id'];
            }
        }

        if ($this->hasCvList($settings)) {
            $mediaIds = array_merge($mediaIds, CvEntry::query()
                ->whereNotNull('image_media_asset_id')
                ->pluck('image_media_asset_id')
                ->map(static fn (mixed $id): int => (int) $id)
                ->all());
        }

        $mediaIds = array_values(array_unique($mediaIds));
        $mediaIds === []
            ? $query->whereRaw('1 = 0')
            : $query->whereIn('media_assets.id', $mediaIds);
    }

    /** @return list<int> */
    private function directCustomMediaIds(): array
    {
        if ($this->directCustomMediaIds !== null) {
            return $this->directCustomMediaIds;
        }

        $ids = [];
        foreach ($this->customPageNodes() as $node) {
            $settings = $node->getRelationValue('customPageSetting');
            if (! $settings instanceof CustomPageSetting) {
                continue;
            }

            foreach ($settings->components() as $component) {
                if (($component['type'] ?? null) === 'image' && is_numeric($component['media_asset_id'] ?? null)) {
                    $ids[] = (int) $component['media_asset_id'];
                }
            }
        }

        return $this->directCustomMediaIds = array_values(array_unique($ids));
    }

    private function customPageReferencesAsset(CustomPageSetting $settings, int $mediaAssetId): bool
    {
        foreach ($settings->components() as $component) {
            if (($component['type'] ?? null) === 'image'
                && is_numeric($component['media_asset_id'] ?? null)
                && (int) $component['media_asset_id'] === $mediaAssetId) {
                return true;
            }
        }

        return false;
    }

    private function hasCvList(CustomPageSetting $settings): bool
    {
        foreach ($settings->components() as $component) {
            if (($component['type'] ?? null) === 'cv_list') {
                return true;
            }
        }

        return false;
    }

    private function nodeLabel(SiteSection $node): string
    {
        return trim((string) ($node->getAttribute('navigation_label') ?: $node->getAttribute('title')));
    }
}
