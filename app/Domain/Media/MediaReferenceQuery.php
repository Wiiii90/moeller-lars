<?php

namespace App\Domain\Media;

use App\Domain\Content\HomeTemplate;
use App\Domain\Content\RichTextMediaReference;
use App\Models\CustomPageSetting;
use App\Models\CvEntry;
use App\Models\HomePresentationSetting;
use App\Models\MediaAsset;
use Illuminate\Database\Eloquent\Builder;

final class MediaReferenceQuery
{
    /** @var list<string> */
    private const CANONICAL_RELATIONS = ['artworks', 'journalEntryMedia', 'siteIdentitySettings', 'cvEntries'];

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
            ->filter(static fn (array $component): bool => ($component['type'] ?? null) === 'image')
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

        foreach (CvEntry::query()->whereNotNull('body')->pluck('body') as $body) {
            if (is_string($body)) {
                $ids = array_merge($ids, RichTextMediaReference::ids($body));
            }
        }

        foreach (HomePresentationSetting::query()->get(['id', 'configuration']) as $settings) {
            $ids = array_merge($ids, $this->mediaIdsForHome($settings));
        }

        return $this->directContentMediaIds = array_values(array_unique($ids));
    }
}
