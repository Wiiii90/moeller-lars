<?php

namespace App\Domain\Media;

use App\Domain\Content\HomeTemplate;
use App\Domain\Content\RichTextMediaReference;
use App\Domain\Content\SiteSectionType;
use App\Models\BlogPost;
use App\Models\CustomPageSetting;
use App\Models\Exhibition;
use App\Models\HomePresentationSetting;
use App\Models\JournalEntryMedia;
use App\Models\MediaAsset;
use App\Models\SiteSection;
use Illuminate\Database\Eloquent\Builder;

final class MediaReferenceQuery
{
    /** @var list<string> */
    private const CANONICAL_RELATIONS = ['artworks', 'journalEntryMedia', 'siteIdentitySettings'];

    /** @var list<int>|null */
    private ?array $directContentMediaIds = null;

    /** @param Builder<MediaAsset> $query */
    public function apply(Builder $query, bool $referenced): void
    {
        $directContentIds = $this->directContentMediaIds();

        if (! $referenced) {
            foreach (self::CANONICAL_RELATIONS as $relation) {
                $query->whereDoesntHave($relation);
            }
            if ($directContentIds !== []) {
                $query->whereNotIn('media_assets.id', $directContentIds);
            }

            return;
        }

        $query->where(function (Builder $references) use ($directContentIds): void {
            foreach (self::CANONICAL_RELATIONS as $index => $relation) {
                $index === 0
                    ? $references->whereHas($relation)
                    : $references->orWhereHas($relation);
            }
            if ($directContentIds !== []) {
                $references->orWhereIn('media_assets.id', $directContentIds);
            }
        });
    }

    public function isReferenced(MediaAsset $asset): bool
    {
        /** @var Builder<MediaAsset> $query */
        $query = MediaAsset::query()->whereKey($asset->getKey());
        $this->apply($query, true);

        return $query->exists();
    }

    /** @return list<int> */
    public function mediaIdsForCustomPage(CustomPageSetting $settings): array
    {
        return $this->componentMediaIds($settings->components());
    }

    /**
     * One canonical reader for direct media and Rich Text references across
     * Custom Page and Home components, including decorative Image children.
     * Public delivery applies publication flags to precisely the same tree.
     *
     * @param  list<mixed>  $components
     * @return list<int>
     */
    public function componentMediaIds(array $components, bool $publishedOnly = false): array
    {
        $ids = [];
        foreach ($components as $component) {
            if (! is_array($component) || ($publishedOnly && ! CustomPageSetting::componentPublished($component))) {
                continue;
            }

            $type = $component['type'] ?? null;
            if (in_array($type, ['image', 'list'], true)) {
                $mediaId = $component['media_asset_id'] ?? null;
                if (is_numeric($mediaId) && (int) $mediaId > 0) {
                    $ids[] = (int) $mediaId;
                }
            }

            if ($type === 'text' && is_string($component['body'] ?? null)) {
                $ids = array_merge($ids, RichTextMediaReference::ids($component['body']));
            }

            if ($type === 'list' && is_array($component['items'] ?? null)) {
                foreach ($component['items'] as $item) {
                    if (! is_array($item) || ($publishedOnly && ! CustomPageSetting::listItemPublished($item))) {
                        continue;
                    }
                    if (is_string($item['body'] ?? null)) {
                        $ids = array_merge($ids, RichTextMediaReference::ids($item['body']));
                    }
                }
            }

            if ($type === 'image' && is_array($component['children'] ?? null)) {
                $ids = array_merge($ids, $this->componentMediaIds($component['children'], $publishedOnly));
            }
        }

        return array_values(array_unique($ids));
    }

    /** Refresh content-derived references after an in-transaction content mutation. */
    public function forgetContentReferences(): void
    {
        $this->directContentMediaIds = null;
    }

    public function customPageReferencesAsset(CustomPageSetting $settings, int $mediaAssetId): bool
    {
        return in_array($mediaAssetId, $this->mediaIdsForCustomPage($settings), true);
    }

    /** @return list<int> */
    public function mediaIdsForJournalSection(SiteSection $section): array
    {
        if ($section->nodeType() !== SiteSectionType::Journal) {
            return [];
        }

        $sectionId = (int) $section->getKey();

        $blogEntries = BlogPost::query()->where('site_section_id', $sectionId);
        $blogEntryIds = (clone $blogEntries)->pluck('id')->all();
        $blogStructured = JournalEntryMedia::query()
            ->whereIn('blog_post_id', $blogEntryIds)
            ->pluck('media_asset_id')
            ->filter(static fn (mixed $id): bool => is_numeric($id) && (int) $id > 0)
            ->map(static fn (mixed $id): int => (int) $id)
            ->all();
        $blogRichText = $this->richTextIds(
            (clone $blogEntries)->whereNotNull('body')->pluck('body'),
        );

        $exhibitionEntries = Exhibition::query()->where('site_section_id', $sectionId);
        $exhibitionEntryIds = (clone $exhibitionEntries)->pluck('id')->all();
        $exhibitionStructured = JournalEntryMedia::query()
            ->whereIn('exhibition_id', $exhibitionEntryIds)
            ->pluck('media_asset_id')
            ->filter(static fn (mixed $id): bool => is_numeric($id) && (int) $id > 0)
            ->map(static fn (mixed $id): int => (int) $id)
            ->all();
        $exhibitionRichText = $this->richTextIds(
            (clone $exhibitionEntries)->whereNotNull('description')->pluck('description'),
        );

        return array_values(array_unique(array_merge(
            $blogStructured,
            $blogRichText,
            $exhibitionStructured,
            $exhibitionRichText,
        )));
    }

    /** @param iterable<SiteSection> $sections
     * @return list<int>
     */
    public function mediaIdsForJournalSections(iterable $sections): array
    {
        $ids = [];
        foreach ($sections as $section) {
            $ids = array_merge($ids, $this->mediaIdsForJournalSection($section));
        }

        return array_values(array_unique($ids));
    }

    /** @return list<int> */
    public function mediaIdsForHome(HomePresentationSetting $settings): array
    {
        $ids = $this->homeHeroMediaIds($settings);
        foreach ([HomeTemplate::UnderConstruction, HomeTemplate::Custom] as $template) {
            $ids = array_merge($ids, $this->componentMediaIds($settings->components($template)));
        }

        return array_values(array_unique($ids));
    }

    /**
     * Stored source profiles own media retention even while inactive. Public
     * delivery must instead use only the active profile and enabled Manual
     * selection, never another Source's private draft candidates.
     *
     * @return list<int>
     */
    public function homeHeroMediaIds(HomePresentationSetting $settings, bool $activeOnly = false): array
    {
        if ($activeOnly) {
            // Only the resolver knows the current eligible, size-limited Hero
            // group. Do not authorize disabled Manual candidates or items
            // beyond Semi-Automatic's configured source size.
            $group = app(\App\Domain\Content\HomeHeroResolver::class)->resolve($settings)['group'];

            return $group
                ->filter(static fn (\App\Domain\Content\HomeHeroCandidate $candidate): bool => $candidate->kind === 'media')
                ->map(static fn (\App\Domain\Content\HomeHeroCandidate $candidate): int => (int) $candidate->record->getKey())
                ->unique()->values()->all();
        }

        $configuration = app(\App\Domain\Content\HomeHeroConfigurationService::class);
        $ids = [];
        foreach (['semi_automatic', 'manual'] as $source) {
            $profile = $configuration->configuration($settings, $source);
            foreach ($profile['manual_group'] as $member) {
                if (isset($member['media_asset_id'])) {
                    $ids[] = (int) $member['media_asset_id'];
                }
            }
        }

        return array_values(array_unique($ids));
    }

    public function homeReferencesAsset(HomePresentationSetting $settings, int $mediaAssetId): bool
    {
        return in_array($mediaAssetId, $this->mediaIdsForHome($settings), true);
    }

    /** @return list<int> */
    private function directContentMediaIds(): array
    {
        if ($this->directContentMediaIds !== null) {
            return $this->directContentMediaIds;
        }

        $ids = [];
        foreach (CustomPageSetting::query()->get(['id', 'blocks']) as $settings) {
            $ids = array_merge($ids, $this->mediaIdsForCustomPage($settings));
        }

        $ids = array_merge($ids, $this->richTextIds(BlogPost::query()->whereNotNull('body')->pluck('body')));
        $ids = array_merge($ids, $this->richTextIds(Exhibition::query()->whereNotNull('description')->pluck('description')));

        foreach (HomePresentationSetting::query()->get(['id', 'configuration']) as $settings) {
            $ids = array_merge($ids, $this->mediaIdsForHome($settings));
        }

        return $this->directContentMediaIds = array_values(array_unique($ids));
    }

    /** @param iterable<mixed> $values
     * @return list<int>
     */
    private function richTextIds(iterable $values): array
    {
        $ids = [];
        foreach ($values as $value) {
            if (is_string($value)) {
                $ids = array_merge($ids, RichTextMediaReference::ids($value));
            }
        }

        return array_values(array_unique($ids));
    }
}
