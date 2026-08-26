<?php

namespace App\Domain\Media;

use App\Domain\Content\HomeTemplate;
use App\Models\CustomPageSetting;
use App\Models\HomePresentationSetting;
use App\Models\MediaAsset;
use Illuminate\Database\Eloquent\Builder;

final class MediaReferenceQuery
{
    /** @var list<string> */
    private const CANONICAL_RELATIONS = ['artworks', 'exhibitions', 'blogPosts', 'siteIdentitySettings'];

    /** @var list<int>|null */
    private ?array $directCustomMediaIds = null;

    /** @var list<int>|null */
    private ?array $directHomeMediaIds = null;

    /** @param Builder<MediaAsset> $query */
    public function apply(Builder $query, bool $referenced): void
    {
        $directIds = array_values(array_unique(array_merge(
            $this->directCustomMediaIds(),
            $this->directHomeMediaIds(),
        )));

        if (! $referenced) {
            foreach (self::CANONICAL_RELATIONS as $relation) {
                $query->whereDoesntHave($relation);
            }
            if ($directIds !== []) {
                $query->whereNotIn('media_assets.id', $directIds);
            }

            return;
        }

        $query->where(function (Builder $references) use ($directIds): void {
            foreach (self::CANONICAL_RELATIONS as $index => $relation) {
                $index === 0
                    ? $references->whereHas($relation)
                    : $references->orWhereHas($relation);
            }
            if ($directIds !== []) {
                $references->orWhereIn('media_assets.id', $directIds);
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
        $mediaIds = [];
        foreach ($settings->components() as $component) {
            if (($component['type'] ?? null) === 'image' && is_numeric($component['media_asset_id'] ?? null)) {
                $mediaIds[] = (int) $component['media_asset_id'];
            }
        }

        return array_values(array_unique($mediaIds));
    }

    public function customPageReferencesAsset(CustomPageSetting $settings, int $mediaAssetId): bool
    {
        return in_array($mediaAssetId, $this->mediaIdsForCustomPage($settings), true);
    }

    /** @return list<int> */
    public function mediaIdsForHome(HomePresentationSetting $settings): array
    {
        $mediaIds = [];
        foreach ([HomeTemplate::UnderConstruction, HomeTemplate::Custom] as $template) {
            foreach ($settings->components($template) as $component) {
                if (($component['type'] ?? null) === 'image' && is_numeric($component['media_asset_id'] ?? null)) {
                    $mediaIds[] = (int) $component['media_asset_id'];
                }
            }
        }

        return array_values(array_unique($mediaIds));
    }

    public function homeReferencesAsset(HomePresentationSetting $settings, int $mediaAssetId): bool
    {
        return in_array($mediaAssetId, $this->mediaIdsForHome($settings), true);
    }

    /** @return list<int> */
    private function directCustomMediaIds(): array
    {
        if ($this->directCustomMediaIds !== null) {
            return $this->directCustomMediaIds;
        }

        $ids = [];
        foreach (CustomPageSetting::query()->get(['id', 'blocks']) as $settings) {
            $ids = array_merge($ids, $this->mediaIdsForCustomPage($settings));
        }

        return $this->directCustomMediaIds = array_values(array_unique($ids));
    }

    /** @return list<int> */
    private function directHomeMediaIds(): array
    {
        if ($this->directHomeMediaIds !== null) {
            return $this->directHomeMediaIds;
        }

        $ids = [];
        foreach (HomePresentationSetting::query()->get(['id', 'configuration']) as $settings) {
            $ids = array_merge($ids, $this->mediaIdsForHome($settings));
        }

        return $this->directHomeMediaIds = array_values(array_unique($ids));
    }
}