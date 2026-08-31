<?php

namespace App\Filament\Support;

use App\Domain\Media\MediaIngestService;
use App\Models\MediaAsset;
use App\Models\MediaVariant;
use Closure;
use Filament\Forms\Components\Select;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;

final class MediaAssetSelect
{
    private const OPTION_LABEL_CACHE = 'admin.media_asset_select.option_labels';

    public static function make(
        string $name,
        string $relationship,
        string|Closure $label,
        bool $imagesOnly = false,
        bool $includeDimensions = true,
    ): Select {
        return self::configure(Select::make($name), $label, $imagesOnly, $includeDimensions)
            ->relationship(
                name: $relationship,
                titleAttribute: 'original_filename',
                modifyQueryUsing: function (Builder $query) use ($imagesOnly): void {
                    self::constrainAvailable($query, $imagesOnly);
                    $query->with('variants');
                    $query->orderBy('original_filename');
                },
            )
            ->getOptionLabelFromRecordUsing(function (Model $record) use ($includeDimensions): string {
                if (! $record instanceof MediaAsset) {
                    return '';
                }

                self::primeOptionLabel($record, $includeDimensions);

                return self::optionLabel($record, $includeDimensions);
            });
    }

    public static function makeId(string $name, string $label, bool $imagesOnly = false): Select
    {
        return self::configure(Select::make($name), $label, $imagesOnly, true)
            ->getOptionLabelUsing(function (mixed $value) use ($imagesOnly): ?string {
                $id = filter_var($value, FILTER_VALIDATE_INT);
                if ($id === false) {
                    return null;
                }

                $cached = self::cachedOptionLabel((int) $id, true);
                if ($cached !== null) {
                    return $cached;
                }

                /** @var Builder<MediaAsset> $query */
                $query = MediaAsset::query()->whereKey((int) $id);
                self::constrainAvailable($query, $imagesOnly);
                $asset = $query->with('variants')->first();
                if (! $asset instanceof MediaAsset) {
                    return null;
                }

                self::primeOptionLabel($asset, true);

                return self::optionLabel($asset, true);
            });
    }

    /** @return array<int, string> */
    public static function searchOptions(string $search, bool $imagesOnly = false, bool $includeDimensions = true): array
    {
        /** @var Builder<MediaAsset> $query */
        $query = MediaAsset::query();
        self::constrainAvailable($query, $imagesOnly);

        $term = trim($search);
        if ($term !== '') {
            $query->where('original_filename', 'ilike', '%'.$term.'%');
        }

        /** @var Collection<int, MediaAsset> $assets */
        $assets = $query->with('variants')->orderBy('original_filename')->limit(30)->get();
        foreach ($assets as $asset) {
            self::primeOptionLabel($asset, $includeDimensions);
        }

        return $assets
            ->mapWithKeys(fn (MediaAsset $asset): array => [(int) $asset->getKey() => self::optionLabel($asset, $includeDimensions)])
            ->all();
    }

    public static function primeOptionLabel(MediaAsset $asset, bool $includeDimensions = true): void
    {
        $asset->loadMissing('variants');
        $cache = request()->attributes->get(self::OPTION_LABEL_CACHE, []);
        if (! is_array($cache)) {
            $cache = [];
        }

        $cache[self::cacheKey((int) $asset->getKey(), $includeDimensions)] = self::optionLabel($asset, $includeDimensions);
        request()->attributes->set(self::OPTION_LABEL_CACHE, $cache);
    }

    private static function cachedOptionLabel(int $id, bool $includeDimensions): ?string
    {
        $cache = request()->attributes->get(self::OPTION_LABEL_CACHE, []);
        $key = self::cacheKey($id, $includeDimensions);

        return is_array($cache) && array_key_exists($key, $cache) && is_string($cache[$key])
            ? $cache[$key]
            : null;
    }

    private static function configure(
        Select $select,
        string|Closure $label,
        bool $imagesOnly,
        bool $includeDimensions,
    ): Select {
        return $select
            ->label($label)
            ->searchable()
            ->getSearchResultsUsing(fn (string $search): array => self::searchOptions($search, $imagesOnly, $includeDimensions))
            ->searchDebounce(350)
            ->searchPrompt('Search Media Files by filename')
            ->noSearchResultsMessage('No matching Media Files')
            ->allowHtml();
    }

    /** @param Builder<MediaAsset> $query */
    private static function constrainAvailable(Builder $query, bool $imagesOnly): void
    {
        $query->where('state', 'available');
        if ($imagesOnly) {
            $query->where('mime_type', 'like', 'image/%');
        }
    }

    private static function optionLabel(MediaAsset $asset, bool $includeDimensions): string
    {
        $asset->loadMissing('variants');
        $filename = e((string) $asset->getAttribute('original_filename'));

        /** @var Collection<int, MediaVariant> $variants */
        $variants = $asset->getRelation('variants');
        $variant = $variants->first(
            fn (MediaVariant $candidate): bool => $candidate->getAttribute('variant_kind') === 'thumbnail'
                && $candidate->getAttribute('transform_profile') === MediaIngestService::TRANSFORM_PROFILE
                && $candidate->getAttribute('state') === 'available',
        );

        $preview = $variant instanceof MediaVariant
            ? '<img src="'.e(route('admin.media.variant', $variant)).'" alt="" width="44" height="44" loading="lazy" decoding="async">'
            : '<span aria-hidden="true">[preview pending]</span>';

        if (! $includeDimensions) {
            return $preview.' <strong>'.$filename.'</strong>';
        }

        $dimensions = $asset->getAttribute('width') && $asset->getAttribute('height')
            ? e($asset->getAttribute('width').'×'.$asset->getAttribute('height'))
            : 'Dimensions unavailable';

        return $preview.' <strong>'.$filename.'</strong>'.' <small>· '.$dimensions.'</small>';
    }

    private static function cacheKey(int $id, bool $includeDimensions): string
    {
        return $id.':'.($includeDimensions ? 'full' : 'compact');
    }
}
