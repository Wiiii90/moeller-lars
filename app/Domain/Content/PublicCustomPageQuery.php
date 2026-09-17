<?php

namespace App\Domain\Content;

use App\Models\CustomPageSetting;
use App\Models\MediaAsset;
use App\Models\PublicContentSetting;
use App\Models\SiteSection;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;

final class PublicCustomPageQuery
{
    /**
     * @return array{
     *     settings: CustomPageSetting,
     *     blocks: list<array<string, mixed>>,
     *     assets: EloquentCollection<int, MediaAsset>,
     *     generalSettings: PublicContentSetting
     * }|null
     */
    public function presentation(SiteSection $section): ?array
    {
        $section->loadMissing('customPageSetting');
        $settings = $section->getRelation('customPageSetting');
        if (! $settings instanceof CustomPageSetting) {
            return null;
        }

        $blocks = $settings->components();
        $mediaIds = collect($blocks)
            ->filter(fn (array $block): bool => in_array($block['type'] ?? null, ['image', 'list'], true))
            ->pluck('media_asset_id')
            ->filter(fn ($id): bool => is_numeric($id))
            ->map(fn ($id): int => (int) $id)
            ->unique()
            ->values();

        /** @var EloquentCollection<int, MediaAsset> $assets */
        $assets = MediaAsset::query()
            ->whereKey($mediaIds)
            ->with('variants')
            ->get()
            ->keyBy(fn (MediaAsset $asset): int => (int) $asset->getKey());

        return [
            'settings' => $settings,
            'blocks' => $blocks,
            'assets' => $assets,
            'generalSettings' => PublicContentSetting::general(),
        ];
    }
}
