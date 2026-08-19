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
    public static function make(string $name, string $relationship, string $label): Select
    {
        return Select::make($name)
            ->label($label)
            ->relationship(
                name: $relationship,
                titleAttribute: 'original_filename',
                modifyQueryUsing: function (Builder $query): void {
                    $query->where('state', 'available');
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
            ->searchable(['original_filename'])
            ->allowHtml();
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
