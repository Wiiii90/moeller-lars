<?php

namespace App\Filament\Support;

use App\Domain\Media\MediaIngestService;
use App\Models\MediaAsset;
use App\Models\MediaVariant;
use Filament\Forms\Components\Select;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;

final class MediaAssetSelect
{
    public static function make(string $name, string $relationship, string $label, bool $imagesOnly = false): Select
    {
        return Select::make($name)
            ->label($label)
            ->relationship(
                name: $relationship,
                titleAttribute: 'original_filename',
                modifyQueryUsing: function (Builder $query) use ($imagesOnly): void {
                    self::constrainAvailable($query, $imagesOnly);
                    $query->with('variants');
                    $query->orderBy('original_filename');
                },
            )
            ->getOptionLabelFromRecordUsing(function (Model $record): string {
                if (! $record instanceof MediaAsset) {
                    return '';
                }

                return self::optionLabel($record);
            })
            ->searchable()
            ->getSearchResultsUsing(fn (string $search): array => self::searchOptions($search, $imagesOnly))
            ->searchDebounce(350)
            ->searchPrompt('Search Media Files by filename')
            ->noSearchResultsMessage('No matching Media Files')
            ->allowHtml();
    }

    /** @return array<int, string> */
    public static function searchOptions(string $search, bool $imagesOnly = false): array
    {
        /** @var Builder<MediaAsset> $query */
        $query = MediaAsset::query();
        self::constrainAvailable($query, $imagesOnly);

        $term = trim($search);
        if ($term !== '') {
            $query->where('original_filename', 'ilike', '%'.$term.'%');
        }

        /** @var Collection<int, MediaAsset> $assets */
        $assets = $query
            ->with('variants')
            ->orderBy('original_filename')
            ->limit(30)
            ->get();

        return $assets
            ->mapWithKeys(fn (MediaAsset $asset): array => [(int) $asset->getKey() => self::optionLabel($asset)])
            ->all();
    }

    /** @param Builder<MediaAsset> $query */
    private static function constrainAvailable(Builder $query, bool $imagesOnly): void
    {
        $query->where('state', 'available');
        if ($imagesOnly) {
            $query->where('mime_type', 'like', 'image/%');
        }
    }

    private static function optionLabel(MediaAsset $asset): string
    {
        $asset->loadMissing('variants');

        $filename = e((string) $asset->getAttribute('original_filename'));
        $dimensions = $asset->getAttribute('width') && $asset->getAttribute('height')
            ? e($asset->getAttribute('width').'×'.$asset->getAttribute('height'))
            : 'Dimensions unavailable';

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

        return $preview
            .' <strong>'.$filename.'</strong>'
            .' <small>· '.$dimensions.'</small>';
    }
}
