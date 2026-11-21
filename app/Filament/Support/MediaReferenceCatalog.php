<?php

namespace App\Filament\Support;

use App\Domain\Content\GalleryTemplate;
use App\Domain\Content\JournalTemplate;
use App\Domain\Content\RichTextMediaReference;
use App\Domain\Content\SiteSectionType;
use App\Domain\Media\MediaReferenceQuery;
use App\Domain\Media\MediaTypePolicy;
use App\Filament\Resources\PublicContentSettings\PublicContentSettingResource;
use App\Models\ArtworkCategory;
use App\Models\BlogPost;
use App\Models\CustomPageSetting;
use App\Models\Exhibition;
use App\Models\GalleryHeroSetting;
use App\Models\JournalEntryMedia;
use App\Models\MediaAsset;
use App\Models\SiteSection;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Database\Eloquent\Relations\Pivot;

final class MediaReferenceCatalog
{
    /** @var EloquentCollection<int, SiteSection>|null */
    private ?EloquentCollection $nodes = null;

    /** @var array<int, list<array{type:string,label:string,url:?string}>>|null */
    private ?array $contentReferenceRowsByMediaId = null;

    public function __construct(
        private readonly SiteNodePresentation $presentation,
        private readonly MediaReferenceQuery $referenceQuery,
    ) {}

    /** @return list<array{label:string,options:list<array{value:string,label:string}>}> */
    public function destinationGroups(): array
    {
        $groups = [
            SiteSectionType::Gallery->value => ['label' => 'Galleries', 'options' => []],
            SiteSectionType::Journal->value => ['label' => 'Journals', 'options' => []],
            SiteSectionType::CustomPage->value => ['label' => 'Custom pages', 'options' => []],
        ];
        $broad = [
            SiteSectionType::Gallery->value => 'Any Gallery',
            SiteSectionType::Journal->value => 'Any Journal',
            SiteSectionType::CustomPage->value => 'Any Custom Page',
        ];

        foreach ($this->nodes() as $node) {
            $type = $node->nodeType();
            if (isset($groups[$type->value])) {
                $groups[$type->value]['options'][] = [
                    'value' => 'node:'.$node->getKey(),
                    'label' => $this->nodeLabel($node),
                ];
            }
        }

        foreach ($groups as $type => &$group) {
            if ($group['options'] !== []) {
                array_unshift($group['options'], [
                    'value' => 'kind:'.$type,
                    'label' => $broad[$type],
                ]);
            }
        }
        unset($group);

        $groups['site'] = [
            'label' => 'Site',
            'options' => [
                ['value' => 'home', 'label' => 'Home'],
                ['value' => 'site-identity', 'label' => 'Site identity'],
            ],
        ];

        return array_values(array_filter(
            $groups,
            static fn (array $group): bool => $group['options'] !== [],
        ));
    }

    /** @param Builder<MediaAsset> $query */
    public function applyUsageFilter(Builder $query, string $usage): void
    {
        if ($usage === 'all') {
            return;
        }
        if ($usage === 'in-use') {
            $this->referenceQuery->apply($query, true);

            return;
        }
        if ($usage === 'unreferenced') {
            $this->referenceQuery->apply($query, false);

            return;
        }

        $this->applyDestinationFilter($query, $usage);
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
        if ($destination === 'home') {
            $this->applyHomeDestination($query);

            return;
        }
        if (str_starts_with($destination, 'kind:')) {
            $type = SiteSectionType::tryFrom(substr($destination, 5));
            if ($type === null || ! in_array($type, [SiteSectionType::Gallery, SiteSectionType::Journal, SiteSectionType::CustomPage], true)) {
                $query->whereRaw('1 = 0');

                return;
            }
            $this->applyKindDestination($query, $type);

            return;
        }

        $node = $this->nodeForDestination($destination);
        if (! $node instanceof SiteSection) {
            $query->whereRaw('1 = 0');

            return;
        }

        match ($node->nodeType()) {
            SiteSectionType::Gallery => $this->applyGalleryDestination($query, $node),
            SiteSectionType::Journal => $this->applyJournalDestination($query, $node),
            SiteSectionType::CustomPage => $this->applyCustomPageDestination($query, $node),
            default => $query->whereRaw('1 = 0'),
        };
    }

    /** @return array{files:int,images:int,videos:int,audio:int,unreferenced:int,bytes:int} */
    public function libraryMetrics(): array
    {
        $available = MediaAsset::query()
            ->where('state', 'available')
            ->whereIn('mime_type', MediaTypePolicy::acceptedMimeTypes());
        $unreferenced = clone $available;
        $this->referenceQuery->apply($unreferenced, false);

        return [
            'files' => (clone $available)->count(),
            'images' => (clone $available)->whereIn('mime_type', MediaTypePolicy::IMAGE_MIME_TYPES)->count(),
            'videos' => (clone $available)->whereIn('mime_type', MediaTypePolicy::VIDEO_MIME_TYPES)->count(),
            'audio' => (clone $available)->whereIn('mime_type', MediaTypePolicy::AUDIO_MIME_TYPES)->count(),
            'unreferenced' => $unreferenced->count(),
            'bytes' => (int) (clone $available)->sum('byte_size'),
        ];
    }

    /** @param Builder<MediaAsset> $query */
    public function applyReferenceFilter(Builder $query, bool $referenced): void
    {
        $this->referenceQuery->apply($query, $referenced);
    }

    /** @param Builder<MediaAsset> $query */
    public function eagerLoad(Builder $query): void
    {
        $query->with([
            'variants',
            'artworks.category.siteSection',
            'journalEntryMedia.blogPost.siteSection',
            'journalEntryMedia.exhibition.siteSection',
            'siteIdentitySettings',
        ]);
    }

    public function loadAssetReferences(MediaAsset $asset): void
    {
        $asset->loadMissing([
            'artworks.category.siteSection',
            'journalEntryMedia.blogPost.siteSection',
            'journalEntryMedia.exhibition.siteSection',
            'siteIdentitySettings',
        ]);
    }

    /** @return list<array{type:string,label:string,url:?string,artwork_id?:int,primary_artwork_id?:?int}> */
    public function references(MediaAsset $asset): array
    {
        $rows = [];
        $kind = MediaTypePolicy::kind((string) $asset->getAttribute('mime_type'));
        $noun = match ($kind) {
            'video' => 'video',
            'audio' => 'audio',
            default => 'image',
        };

        foreach ($asset->getRelation('artworks') as $artwork) {
            $category = $artwork->getRelationValue('category');
            $node = $category instanceof ArtworkCategory ? $category->getRelationValue('siteSection') : null;
            $gallery = $node instanceof SiteSection
                ? $this->nodeLabel($node)
                : trim((string) ($category?->getAttribute('name') ?? 'Gallery'));
            $pivot = $artwork->getRelationValue('pivot');
            $role = $pivot instanceof Pivot ? (string) $pivot->getAttribute('role') : 'additional';
            $rows[] = [
                'type' => 'Gallery: '.$gallery,
                'label' => (string) $artwork->getAttribute('title').' — '.($role === 'primary' ? 'Primary ' : 'Additional ').$noun,
                'url' => $node instanceof SiteSection ? $this->presentation->workspaceUrl($node) : null,
                // Only primary Artwork references own an optional Artwork
                // deletion checkbox in the Storage confirmation.
                'artwork_id' => (int) $artwork->getKey(),
                'primary_artwork_id' => $role === 'primary' ? (int) $artwork->getKey() : null,
            ];
        }

        foreach ($asset->getRelation('journalEntryMedia') as $usage) {
            if (! $usage instanceof JournalEntryMedia) {
                continue;
            }

            $entry = $usage->getRelationValue('blogPost');
            $template = 'Blog';
            if ($entry === null) {
                $entry = $usage->getRelationValue('exhibition');
                $template = 'Exhibitions';
            }
            if ($entry === null) {
                continue;
            }

            $node = $entry->getRelationValue('siteSection');
            $role = match ((string) $usage->getAttribute('role')) {
                JournalEntryMedia::ROLE_COVER => 'Cover image',
                JournalEntryMedia::ROLE_GALLERY => 'Gallery image',
                default => 'Journal image',
            };
            $rows[] = [
                'type' => 'Journal: '.$template,
                'label' => (string) $entry->getAttribute('title').' — '.$role,
                'url' => $node instanceof SiteSection ? $this->presentation->workspaceUrl($node) : null,
            ];
        }

        foreach ($this->contentReferenceRowsByMediaId()[(int) $asset->getKey()] ?? [] as $row) {
            $rows[] = $row;
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
            // Different Artworks can legitimately share the same title,
            // Gallery and media role. Never merge their delete choices.
            $unique[implode('|', [
                $row['type'], $row['label'], $row['url'] ?? '',
                $row['artwork_id'] ?? '',
            ])] = $row;
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
        return $this->nodes()
            ->filter(fn (SiteSection $node): bool => $node->nodeType() === SiteSectionType::CustomPage);
    }

    /** @return EloquentCollection<int, SiteSection> */
    /** @return EloquentCollection<int, SiteSection> */
    private function heroNodes(): EloquentCollection
    {
        return $this->nodes()->filter(
            fn (SiteSection $node): bool => $node->nodeType() === SiteSectionType::Gallery
                && $node->galleryTemplate() === GalleryTemplate::HeroArtworks,
        );
    }

    /** @return list<int> */
    private function heroMediaIds(SiteSection $node): array
    {
        if ($node->nodeType() !== SiteSectionType::Gallery
            || $node->galleryTemplate() !== GalleryTemplate::HeroArtworks) {
            return [];
        }
        /** @var GalleryHeroSetting|null $settings */
        $settings = GalleryHeroSetting::query()->where('site_section_id', $node->getKey())->first();

        return $settings instanceof GalleryHeroSetting
            ? $this->referenceQuery->galleryHeroMediaIds($settings)
            : [];
    }

    private function journalNodes(): EloquentCollection
    {
        return $this->nodes()
            ->filter(fn (SiteSection $node): bool => $node->nodeType() === SiteSectionType::Journal);
    }

    private function homeNode(): ?SiteSection
    {
        $node = $this->nodes()->first(
            fn (SiteSection $candidate): bool => $candidate->isHome(),
        );

        return $node instanceof SiteSection ? $node : null;
    }

    private function nodeForDestination(string $destination): ?SiteSection
    {
        if (preg_match('/^node:(\d+)$/', $destination, $matches) !== 1) {
            return null;
        }

        $node = $this->nodes()->first(
            fn (SiteSection $candidate): bool => (int) $candidate->getKey() === (int) $matches[1],
        );

        return $node instanceof SiteSection ? $node : null;
    }

    /** @param Builder<MediaAsset> $query */
    private function applyKindDestination(Builder $query, SiteSectionType $type): void
    {
        if ($type === SiteSectionType::Gallery) {
            $categoryIds = $this->nodes()
                ->filter(fn (SiteSection $node): bool => $node->nodeType() === SiteSectionType::Gallery)
                ->pluck('artwork_category_id')
                ->filter(static fn (mixed $id): bool => is_numeric($id))
                ->map(static fn (mixed $id): int => (int) $id)
                ->values()->all();
            $heroIds = [];
            foreach ($this->heroNodes() as $node) {
                $heroIds = array_merge($heroIds, $this->heroMediaIds($node));
            }
            $heroIds = array_values(array_unique($heroIds));

            if ($categoryIds === [] && $heroIds === []) {
                $query->whereRaw('1 = 0');
            } else {
                $query->where(function (Builder $assets) use ($categoryIds, $heroIds): void {
                    if ($categoryIds !== []) {
                        $assets->whereHas('artworks', fn (Builder $artwork) => $artwork->whereIn('artwork_category_id', $categoryIds));
                    }
                    if ($heroIds !== []) {
                        $categoryIds !== []
                            ? $assets->orWhereIn('media_assets.id', $heroIds)
                            : $assets->whereIn('media_assets.id', $heroIds);
                    }
                });
            }

            return;
        }

        if ($type === SiteSectionType::Journal) {
            $ids = $this->referenceQuery->mediaIdsForJournalSections($this->journalNodes());
            $ids === [] ? $query->whereRaw('1 = 0') : $query->whereIn('media_assets.id', $ids);

            return;
        }

        if ($type === SiteSectionType::CustomPage) {
            $ids = [];
            foreach ($this->customPageNodes() as $node) {
                $settings = $node->getRelationValue('customPageSetting');
                if ($settings instanceof CustomPageSetting) {
                    $ids = array_merge($ids, $this->referenceQuery->mediaIdsForCustomPage($settings));
                }
            }
            $ids = array_values(array_unique($ids));
            $ids === [] ? $query->whereRaw('1 = 0') : $query->whereIn('media_assets.id', $ids);

            return;
        }

        $query->whereRaw('1 = 0');
    }

    /** @param Builder<MediaAsset> $query */
    private function applyGalleryDestination(Builder $query, SiteSection $node): void
    {
        $galleryId = $node->getAttribute('artwork_category_id');
        $heroIds = $this->heroMediaIds($node);

        if (! is_numeric($galleryId) && $heroIds === []) {
            $query->whereRaw('1 = 0');

            return;
        }

        $query->where(function (Builder $assets) use ($galleryId, $heroIds): void {
            if (is_numeric($galleryId)) {
                $assets->whereHas('artworks',
                    fn (Builder $artwork) => $artwork->where('artwork_category_id', (int) $galleryId));
            }
            if ($heroIds !== []) {
                is_numeric($galleryId)
                    ? $assets->orWhereIn('media_assets.id', $heroIds)
                    : $assets->whereIn('media_assets.id', $heroIds);
            }
        });
    }

    /** @param Builder<MediaAsset> $query */
    private function applyJournalDestination(Builder $query, SiteSection $node): void
    {
        if (! in_array($node->journalTemplate(), [JournalTemplate::Blog, JournalTemplate::Exhibitions], true)) {
            $query->whereRaw('1 = 0');

            return;
        }

        $ids = $this->referenceQuery->mediaIdsForJournalSection($node);
        $ids === [] ? $query->whereRaw('1 = 0') : $query->whereIn('media_assets.id', $ids);
    }

    /** @param Builder<MediaAsset> $query */
    private function applyCustomPageDestination(Builder $query, SiteSection $node): void
    {
        $settings = $node->getRelationValue('customPageSetting');
        if (! $settings instanceof CustomPageSetting) {
            $query->whereRaw('1 = 0');

            return;
        }

        $ids = $this->referenceQuery->mediaIdsForCustomPage($settings);
        $ids === [] ? $query->whereRaw('1 = 0') : $query->whereIn('media_assets.id', $ids);
    }

    /** @param Builder<MediaAsset> $query */
    private function applyHomeDestination(Builder $query): void
    {
        $home = $this->homeNode();
        if (! $home instanceof SiteSection) {
            $query->whereRaw('1 = 0');

            return;
        }

        match ($home->nodeType()) {
            SiteSectionType::Gallery => $this->applyGalleryDestination($query, $home),
            SiteSectionType::Journal => $this->applyJournalDestination($query, $home),
            SiteSectionType::CustomPage => $this->applyCustomPageDestination($query, $home),
            default => $query->whereRaw('1 = 0'),
        };
    }

    /** @return array<int, list<array{type:string,label:string,url:?string}>> */
    private function contentReferenceRowsByMediaId(): array
    {
        if ($this->contentReferenceRowsByMediaId !== null) {
            return $this->contentReferenceRowsByMediaId;
        }

        $rows = [];

        foreach (BlogPost::query()->with('siteSection')->whereNotNull('body')->get(['id', 'site_section_id', 'title', 'body']) as $post) {
            $body = $post->getAttribute('body');
            if (! is_string($body)) {
                continue;
            }
            $node = $post->getRelationValue('siteSection');
            foreach (RichTextMediaReference::ids($body) as $mediaId) {
                $this->appendReferenceRow($rows, $mediaId, [
                    'type' => 'Journal: Blog',
                    'label' => (string) $post->getAttribute('title').' — Rich Text image',
                    'url' => $node instanceof SiteSection ? $this->presentation->workspaceUrl($node) : null,
                ]);
            }
        }

        foreach (Exhibition::query()->with('siteSection')->whereNotNull('description')->get(['id', 'site_section_id', 'title', 'description']) as $exhibition) {
            $description = $exhibition->getAttribute('description');
            if (! is_string($description)) {
                continue;
            }
            $node = $exhibition->getRelationValue('siteSection');
            foreach (RichTextMediaReference::ids($description) as $mediaId) {
                $this->appendReferenceRow($rows, $mediaId, [
                    'type' => 'Journal: Exhibitions',
                    'label' => (string) $exhibition->getAttribute('title').' — Rich Text image',
                    'url' => $node instanceof SiteSection ? $this->presentation->workspaceUrl($node) : null,
                ]);
            }
        }

        foreach ($this->customPageNodes() as $node) {
            $settings = $node->getRelationValue('customPageSetting');
            if (! $settings instanceof CustomPageSetting) {
                continue;
            }

            foreach ($settings->components() as $component) {
                $type = $component['type'] ?? null;
                $mediaId = $component['media_asset_id'] ?? null;

                if (in_array($type, CustomPageSetting::MEDIA_COMPONENT_TYPES, true) && is_numeric($mediaId) && (int) $mediaId > 0) {
                    $this->appendReferenceRow($rows, (int) $mediaId, [
                        'type' => 'Custom Page: '.$this->nodeLabel($node),
                        'label' => $type === 'video' ? 'Video component' : 'Image component',
                        'url' => $this->presentation->workspaceUrl($node),
                    ]);
                }

                if ($type === 'list' && is_numeric($mediaId) && (int) $mediaId > 0) {
                    $this->appendReferenceRow($rows, (int) $mediaId, [
                        'type' => 'Custom Page: '.$this->nodeLabel($node),
                        'label' => 'List component image',
                        'url' => $this->presentation->workspaceUrl($node),
                    ]);
                }

                if ($type === 'text' && is_string($component['body'] ?? null)) {
                    $title = trim((string) ($component['title'] ?? ''));
                    foreach (RichTextMediaReference::ids($component['body']) as $richTextMediaId) {
                        $this->appendReferenceRow($rows, $richTextMediaId, [
                            'type' => 'Custom Page: '.$this->nodeLabel($node),
                            'label' => $title === '' ? 'Rich Text component' : 'Rich Text · '.$title,
                            'url' => $this->presentation->workspaceUrl($node),
                        ]);
                    }
                }

                if ($type === 'list' && is_array($component['items'] ?? null)) {
                    foreach ($component['items'] as $item) {
                        if (! is_array($item) || ! is_string($item['body'] ?? null)) {
                            continue;
                        }
                        $itemTitle = trim((string) ($item['title'] ?? ''));
                        foreach (RichTextMediaReference::ids($item['body']) as $richTextMediaId) {
                            $this->appendReferenceRow($rows, $richTextMediaId, [
                                'type' => 'Custom Page: '.$this->nodeLabel($node),
                                'label' => $itemTitle === '' ? 'List item Rich Text' : 'List item Rich Text · '.$itemTitle,
                                'url' => $this->presentation->workspaceUrl($node),
                            ]);
                        }
                    }
                }
            }
        }

        foreach (GalleryHeroSetting::query()->with('siteSection')->get(['id', 'site_section_id', 'configuration']) as $setting) {
            $node = $setting->getRelationValue('siteSection');
            if (! $node instanceof SiteSection) {
                continue;
            }

            foreach ($this->referenceQuery->galleryHeroMediaIds($setting) as $mediaId) {
                $this->appendReferenceRow($rows, $mediaId, [
                    'type' => ($node->isHome() ? 'Home' : 'Gallery').': Hero Artworks',
                    'label' => 'Selected Storage Hero candidate',
                    'url' => $this->presentation->workspaceUrl($node),
                ]);
            }
        }

        return $this->contentReferenceRowsByMediaId = $rows;
    }

    /**
     * @param  array<int, list<array{type:string,label:string,url:?string}>>  $rows
     * @param  array{type:string,label:string,url:?string}  $row
     */
    private function appendReferenceRow(array &$rows, int $mediaId, array $row): void
    {
        if ($mediaId <= 0) {
            return;
        }

        $rows[$mediaId] ??= [];
        $rows[$mediaId][] = $row;
    }

    private function nodeLabel(SiteSection $node): string
    {
        return trim((string) ($node->getAttribute('navigation_label') ?: $node->getAttribute('title')));
    }
}
