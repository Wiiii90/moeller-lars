<?php

namespace App\Domain\Media;

use App\Domain\Content\RichTextMediaReference;
use App\Models\CustomPageSetting;
use App\Models\CvEntry;
use App\Models\MediaAsset;
use Illuminate\Database\Eloquent\Builder;

final class MediaReferenceQuery
{
    /** @var list<string> */
    private const CANONICAL_RELATIONS = ['artworks', 'exhibitions', 'blogPosts', 'siteIdentitySettings'];

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

        return $this->directContentMediaIds = array_values(array_unique($ids));
    }
}
