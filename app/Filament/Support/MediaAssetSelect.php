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
                modifyQueryUsing: fn (Builder $query): Builder => $query
                    ->with('variants')
                    ->orderBy('original_filename'),
            )
            ->getOptionLabelFromRecordUsing(function (Model $record): string {
                if (! $record instanceof MediaAsset) {
                    return '';
                }

                return self::optionLabel($record);
            })
            ->searchable(['original_filename'])
            ->preload()
            ->allowHtml();
    }

    private static function optionLabel(MediaAsset $asset): string
    {
        $asset->loadMissing('variants');

        $filename = e((string) $asset->getAttribute('original_filename'));
        $state = (string) $asset->getAttribute('state');
        $stateLabel = e(ucfirst($state));
        $previewUrl = null;

        if ($state === 'available') {
            /** @var Collection<int, MediaVariant> $variants */
            $variants = $asset->getRelation('variants');
            $variant = $variants->first(
                fn (MediaVariant $candidate): bool => $candidate->getAttribute('variant_kind') === 'thumbnail'
                    && $candidate->getAttribute('transform_profile') === MediaIngestService::TRANSFORM_PROFILE
                    && $candidate->getAttribute('state') === 'available',
            );

            if ($variant instanceof MediaVariant) {
                $previewUrl = route('admin.media.variant', $variant);
            } elseif (str_starts_with((string) $asset->getAttribute('mime_type'), 'image/')) {
                $previewUrl = route('admin.media.original', $asset);
            }
        }

        $preview = $previewUrl === null
            ? '<span aria-hidden="true" style="display:inline-flex;width:40px;height:40px;align-items:center;justify-content:center;border:1px solid currentColor;border-radius:4px;opacity:.35">—</span>'
            : '<img src="'.e($previewUrl).'" alt="" style="width:40px;height:40px;object-fit:cover;border-radius:4px">';

        return '<span style="display:flex;align-items:center;gap:.65rem">'
            .$preview
            .'<span style="display:flex;flex-direction:column;min-width:0">'
            .'<span style="overflow:hidden;text-overflow:ellipsis">'.$filename.'</span>'
            .'<small style="opacity:.65">'.$stateLabel.'</small>'
            .'</span></span>';
    }
}
