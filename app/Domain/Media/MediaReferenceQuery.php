<?php

namespace App\Domain\Media;

use App\Domain\Content\HomeTemplate;
use App\Domain\Content\RichTextMediaReference;
use App\Domain\Content\SiteNodeType;
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
        $blocks = $settings->components();
        $mediaIds = collect($blocks)
            ->filter(static fn (array $component): bool => in_array($component['type'] ?? null, ['image', 'cv_list'], true))
            ->pluck('media_asset_id')
            ->filter(static fn (mixed $id): bool => is_numeric($id) && (int) $id > 0)
            ->map(static fn (mixed $id): int => (int) $id)
            ->all();

        return array_values(array_unique(array_merge(
            $mediaIds,
            RichTextMediaReference::idsFromCustomPageBlocks($blocks),
        )));
    }

    public function customPageReferencesAsset(CustomPageSetting $settings, int $mediaAssetId): bool
    {
        return in_array($mediaAssetId, $this->mediaIdsForCustomPage($settings), true);
    }

    /** @return list<int> */
    public function mediaIdsForCv(): array
    {
        $ids = [];
        foreach (CustomPageSetting::query()->get(['id', 'blocks']) as $settings) {
            foreach ($settings->components() as $component) {
                if (($component['type'] ?? null) !== 'cv_list') {
                    continue;
                }

                $mediaId = $component['media_asset_id'] ?? null;
                if (is_numeric($mediaId) && (int) $mediaId > 0) {
                    $ids[] = (int) $mediaId;
                }
            }
        }

        return array_values(array_unique($ids));
    }

    /** @return list<int> */
    public function mediaIdsForJournalSection(SiteSection $section): array
    {
        if ($section->nodeType() !== SiteNodeType::Journal) {
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
     *  @return list<int>
     */
    public function mediaIdsForJournalSections(iterable $sections): array
    {
        $ids = [];
        foreach ($sections as $section) {
            if ($section instanceof SiteSection) {
                $ids = array_merge($ids, $this->mediaIdsForJournalSection($section));
            }
        }

        return array_values(array_unique($ids));
    }

    /** @return list<int> */
    public function mediaIdsForHome(HomePresentationSetting $settings): array
    {
        $ids = [];

        foreach ([HomeTemplate::UnderConstruction, HomeTemplate::Custom] as $template) {
            foreach ($settings->components($template) as $component) {
                if (($component['type'] ?? null) === 'image'
                    && is_numeric($component['media_asset_id'] ?? null)
                    && (int) $component['media_asset_id'] > 0) {
                    $ids[] = (int) $component['media_asset_id'];
                }

                if (($component['type'] ?? null) === 'text' && is_string($component['body'] ?? null)) {
                    $ids = array_merge($ids, RichTextMediaReference::ids($component['body']));
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
     *  @return list<int>
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
