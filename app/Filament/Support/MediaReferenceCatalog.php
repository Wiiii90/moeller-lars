<?php

namespace App\Filament\Support;

use App\Domain\Content\HomeTemplate;
use App\Domain\Content\JournalTemplate;
use App\Domain\Content\RichTextMediaReference;
use App\Domain\Content\SiteNodeType;
use App\Domain\Media\MediaReferenceQuery;
use App\Domain\Media\MediaTypePolicy;
use App\Filament\Resources\PublicContentSettings\PublicContentSettingResource;
use App\Models\ArtworkCategory;
use App\Models\BlogPost;
use App\Models\CustomPageSetting;
use App\Models\Exhibition;
use App\Models\HomePresentationSetting;
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

    private ?HomePresentationSetting $homeSettings = null;

    private bool $homeSettingsLoaded = false;

    public function __construct(
        private readonly SiteNodePresentation $presentation,
        private readonly MediaReferenceQuery $referenceQuery,
    ) {}

    /** @return list<array{label:string,options:list<array{value:string,label:string}>}> */
    public function destinationGroups(): array
    {
        $groups = [
            SiteNodeType::Gallery->value => ['label' => 'Galleries', 'options' => []],
            SiteNodeType::Journal->value => ['label' => 'Journals', 'options' => []],
            SiteNodeType::CustomPage->value => ['label' => 'Custom pages', 'options' => []],
        ];
        $broad = [
            SiteNodeType::Gallery->value => 'Any Gallery',
            SiteNodeType::Journal->value => 'Any Journal',
            SiteNodeType::CustomPage->value => 'Any Custom Page',
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
                ['value' => 'cv', 'label' => 'CV'],
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
        if ($destination === 'cv') {
            $ids = $this->referenceQuery->mediaIdsForCv();
            $ids === [] ? $query->whereRaw('1 = 0') : $query->whereIn('media_assets.id', $ids);

            return;
        }
        if (str_starts_with($destination, 'kind:')) {
            $type = SiteNodeType::tryFrom(substr($destination, 5));
            if ($type === null || ! in_array($type, [SiteNodeType::Gallery, SiteNodeType::Journal, SiteNodeType::CustomPage], true)) {
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
            SiteNodeType::Gallery => $this->applyGalleryDestination($query, $node),
            SiteNodeType::Journal => $this->applyJournalDestination($query, $node),
            SiteNodeType::CustomPage => $this->applyCustomPageDestination($query, $node),
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

    /** @return list<array{type:string,label:string,url:?string}> */
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
            $unique[implode('|', [$row['type'], $row['label'], $row['url'] ?? ''])] = $row;
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
            ->filter(fn (SiteSection $node): bool => $node->nodeType() === SiteNodeType::CustomPage);
    }

    /** @return EloquentCollection<int, SiteSection> */
    private function journalNodes(): EloquentCollection
    {
        return $this->nodes()
            ->filter(fn (SiteSection $node): bool => $node->nodeType() === SiteNodeType::Journal);
    }

    private function homeNode(): ?SiteSection
    {
        $node = $this->nodes()->first(
            fn (SiteSection $candidate): bool => $candidate->nodeType() === SiteNodeType::Home,
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
    private function applyKindDestination(Builder $query, SiteNodeType $type): void
    {
        if ($type === SiteNodeType::Gallery) {
            $ids = $this->nodes()
                ->filter(fn (SiteSection $node): bool => $node->nodeType() === SiteNodeType::Gallery)
                ->pluck('artwork_category_id')
                ->filter(static fn (mixed $id): bool => is_numeric($id))
                ->map(static fn (mixed $id): int => (int) $id)
                ->values()
                ->all();
            $ids === []
                ? $query->whereRaw('1 = 0')
                : $query->whereHas('artworks', fn (Builder $artwork) => $artwork->whereIn('artwork_category_id', $ids));

            return;
        }

        if ($type === SiteNodeType::Journal) {
            $ids = $this->referenceQuery->mediaIdsForJournalSections($this->journalNodes());
            $ids === [] ? $query->whereRaw('1 = 0') : $query->whereIn('media_assets.id', $ids);

            return;
        }

        if ($type === SiteNodeType::CustomPage) {
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
        $id = $node->getAttribute('artwork_category_id');
        if (! is_numeric($id)) {
            $query->whereRaw('1 = 0');

            return;
        }

        $query->whereHas('artworks', fn (Builder $artwork) => $artwork->where('artwork_category_id', (int) $id));
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
        $settings = $this->homeSettings();
        if (! $settings instanceof HomePresentationSetting) {
            $query->whereRaw('1 = 0');

            return;
        }

        $ids = $this->referenceQuery->mediaIdsForHome($settings);
        $ids === [] ? $query->whereRaw('1 = 0') : $query->whereIn('media_assets.id', $ids);
    }

    private function homeSettings(): ?HomePresentationSetting
    {
        if ($this->homeSettingsLoaded) {
            return $this->homeSettings;
        }

        $this->homeSettingsLoaded = true;
        $settings = HomePresentationSetting::query()->first();
        $this->homeSettings = $settings instanceof HomePresentationSetting ? $settings : null;

        return $this->homeSettings;
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

                if ($type === 'image' && is_numeric($mediaId) && (int) $mediaId > 0) {
                    $this->appendReferenceRow($rows, (int) $mediaId, [
                        'type' => 'Custom Page: '.$this->nodeLabel($node),
                        'label' => 'Image component',
                        'url' => $this->presentation->workspaceUrl($node),
                    ]);
                }

                if ($type === 'cv_list' && is_numeric($mediaId) && (int) $mediaId > 0) {
                    $this->appendReferenceRow($rows, (int) $mediaId, [
                        'type' => 'CV',
                        'label' => $this->nodeLabel($node).' — Portrait',
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

        $homeNode = $this->homeNode();
        $homeUrl = $homeNode instanceof SiteSection ? $this->presentation->workspaceUrl($homeNode) : null;
        foreach (HomePresentationSetting::query()->get(['id', 'configuration']) as $settings) {
            foreach ([HomeTemplate::UnderConstruction, HomeTemplate::Custom] as $template) {
                foreach ($settings->components($template) as $component) {
                    if (($component['type'] ?? null) === 'image'
                        && is_numeric($component['media_asset_id'] ?? null)
                        && (int) $component['media_asset_id'] > 0) {
                        $this->appendReferenceRow($rows, (int) $component['media_asset_id'], [
                            'type' => 'Home: '.$template->label(),
                            'label' => 'Image component',
                            'url' => $homeUrl,
                        ]);
                    }

                    if (($component['type'] ?? null) === 'text' && is_string($component['body'] ?? null)) {
                        $title = trim((string) ($component['title'] ?? ''));
                        foreach (RichTextMediaReference::ids($component['body']) as $mediaId) {
                            $this->appendReferenceRow($rows, $mediaId, [
                                'type' => 'Home: '.$template->label(),
                                'label' => $title === '' ? 'Rich Text component' : 'Rich Text · '.$title,
                                'url' => $homeUrl,
                            ]);
                        }
                    }
                }
            }
        }

        return $this->contentReferenceRowsByMediaId = $rows;
    }

    /**
     * @param array<int, list<array{type:string,label:string,url:?string}>> $rows
     * @param array{type:string,label:string,url:?string} $row
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
