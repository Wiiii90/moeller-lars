<?php

namespace App\Domain\Media;

use App\Models\CustomPageSetting;
use App\Models\MediaAsset;
use Illuminate\Database\Eloquent\Builder;

final class MediaReferenceQuery
{
    /** @var list<string> */
    private const CANONICAL_RELATIONS = ['artworks', 'journalEntryMedia', 'siteIdentitySettings'];
    /** @var list<int>|null */
    private ?array $directCustomMediaIds = null;

    /** @param Builder<MediaAsset> $query */
    public function apply(Builder $query, bool $referenced): void
    {
        $directCustomIds = $this->directCustomMediaIds();
        if (! $referenced) {
            foreach (self::CANONICAL_RELATIONS as $relation) { $query->whereDoesntHave($relation); }
            if ($directCustomIds !== []) { $query->whereNotIn('media_assets.id', $directCustomIds); }
            return;
        }
        $query->where(function (Builder $references) use ($directCustomIds): void {
            foreach (self::CANONICAL_RELATIONS as $index => $relation) { $index === 0 ? $references->whereHas($relation) : $references->orWhereHas($relation); }
            if ($directCustomIds !== []) { $references->orWhereIn('media_assets.id', $directCustomIds); }
        });
    }

    public function isReferenced(MediaAsset $asset): bool
    {
        $query = MediaAsset::query()->whereKey($asset->getKey()); $this->apply($query, true); return $query->exists();
    }

    /** @return list<int> */
    public function mediaIdsForCustomPage(CustomPageSetting $settings): array
    {
        $ids = [];
        foreach ($settings->components() as $component) {
            if (($component['type'] ?? null) === 'image' && is_numeric($component['media_asset_id'] ?? null)) { $ids[] = (int) $component['media_asset_id']; }
        }
        return array_values(array_unique($ids));
    }

    public function customPageReferencesAsset(CustomPageSetting $settings, int $mediaAssetId): bool { return in_array($mediaAssetId, $this->mediaIdsForCustomPage($settings), true); }

    /** @return list<int> */
    private function directCustomMediaIds(): array
    {
        if ($this->directCustomMediaIds !== null) { return $this->directCustomMediaIds; }
        $ids = [];
        foreach (CustomPageSetting::query()->get(['id','blocks']) as $settings) { $ids = array_merge($ids, $this->mediaIdsForCustomPage($settings)); }
        return $this->directCustomMediaIds = array_values(array_unique($ids));
    }
}
