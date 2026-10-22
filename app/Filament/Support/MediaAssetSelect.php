<?php

namespace App\Filament\Support;

use App\Domain\Media\MediaIngestService;
use App\Filament\Support\Controls\AdminControl;
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

    private const INITIAL_PREVIEW_LIMIT = 24;

    private const SEARCH_RESULT_LIMIT = 30;

    public static function make(
        string $name,
        string $relationship,
        string|Closure $label,
        bool $imagesOnly = false,
        bool $includeDimensions = true,
    ): Select {
        return self::configure(Select::make($name), $label, $imagesOnly, $includeDimensions, visual: true)
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

                self::primeOptionLabel($record, $includeDimensions, true);

                return self::optionLabel($record, $includeDimensions, true);
            })
            ->preload()
            ->optionsLimit(self::INITIAL_PREVIEW_LIMIT)
            ->getSearchResultsUsing(fn (string $search): array => self::searchOptions(
                $search,
                $imagesOnly,
                $includeDimensions,
                visual: true,
            ));
    }

    /**
     * @param  list<string>|null  $allowedMimeTypes
     */
    public static function makeId(
        string $name,
        string $label,
        bool $imagesOnly = false,
        ?array $allowedMimeTypes = null,
        ?Closure $modifyQueryUsing = null,
        bool $visual = true,
    ): Select {
        $select = self::configure(
            Select::make($name),
            $label,
            $imagesOnly,
            true,
            $allowedMimeTypes,
            $modifyQueryUsing,
            $visual,
        )
            ->getOptionLabelUsing(function (mixed $value) use ($imagesOnly, $allowedMimeTypes, $modifyQueryUsing, $visual): ?string {
                $id = filter_var($value, FILTER_VALIDATE_INT);
                if ($id === false) {
                    return null;
                }

                if ($allowedMimeTypes === null && $modifyQueryUsing === null) {
                    $cached = self::cachedOptionLabel((int) $id, true, $visual);
                    if ($cached !== null) {
                        return $cached;
                    }
                }

                /** @var Builder<MediaAsset> $query */
                $query = MediaAsset::query()->whereKey((int) $id);
                self::constrainAvailable($query, $imagesOnly, $allowedMimeTypes, $modifyQueryUsing);
                $asset = $query->with('variants')->first();
                if (! $asset instanceof MediaAsset) {
                    return null;
                }

                self::primeOptionLabel($asset, true, $visual);

                return self::optionLabel($asset, true, $visual);
            });

        if ($visual) {
            $select->options(fn (): array => self::searchOptions(
                '',
                $imagesOnly,
                true,
                $allowedMimeTypes,
                $modifyQueryUsing,
                visual: true,
            ));
        }

        return $select;
    }

    /**
     * @param  list<string>|null  $allowedMimeTypes
     * @return array<int, string>
     */
    public static function searchOptions(
        string $search,
        bool $imagesOnly = false,
        bool $includeDimensions = true,
        ?array $allowedMimeTypes = null,
        ?Closure $modifyQueryUsing = null,
        bool $visual = false,
    ): array {
        /** @var Builder<MediaAsset> $query */
        $query = MediaAsset::query();
        self::constrainAvailable($query, $imagesOnly, $allowedMimeTypes, $modifyQueryUsing);

        $term = trim($search);
        if ($term !== '') {
            $query->where(static fn (Builder $query): Builder => $query
                ->where('original_filename', 'ilike', '%'.$term.'%')
                ->orWhere('alt_text', 'ilike', '%'.$term.'%'));
        }

        /** @var Collection<int, MediaAsset> $assets */
        if ($term === '') {
            $query->orderByDesc('created_at')->orderByDesc('id');
        } else {
            $query->orderBy('original_filename')->orderBy('id');
        }

        $assets = $query->with('variants')
            ->limit($term === '' ? self::INITIAL_PREVIEW_LIMIT : self::SEARCH_RESULT_LIMIT)
            ->get();
        foreach ($assets as $asset) {
            self::primeOptionLabel($asset, $includeDimensions, $visual);
        }

        return $assets
            ->mapWithKeys(fn (MediaAsset $asset): array => [(int) $asset->getKey() => self::optionLabel($asset, $includeDimensions, $visual)])
            ->all();
    }

    public static function primeOptionLabel(MediaAsset $asset, bool $includeDimensions = true, bool $visual = false): void
    {
        $asset->loadMissing('variants');
        $cache = request()->attributes->get(self::OPTION_LABEL_CACHE, []);
        if (! is_array($cache)) {
            $cache = [];
        }

        $cache[self::cacheKey((int) $asset->getKey(), $includeDimensions, $visual)] = self::optionLabel($asset, $includeDimensions, $visual);
        request()->attributes->set(self::OPTION_LABEL_CACHE, $cache);
    }

    private static function cachedOptionLabel(int $id, bool $includeDimensions, bool $visual = false): ?string
    {
        $cache = request()->attributes->get(self::OPTION_LABEL_CACHE, []);
        $key = self::cacheKey($id, $includeDimensions, $visual);

        return is_array($cache) && array_key_exists($key, $cache) && is_string($cache[$key])
            ? $cache[$key]
            : null;
    }

    /**
     * @param  list<string>|null  $allowedMimeTypes
     */
    private static function configure(
        Select $select,
        string|Closure $label,
        bool $imagesOnly,
        bool $includeDimensions,
        ?array $allowedMimeTypes = null,
        ?Closure $modifyQueryUsing = null,
        bool $visual = false,
    ): Select {
        if ($visual) {
            $select->extraAlpineAttributes(['class' => 'admin-media-picker--visual'], merge: true);
        }

        return $select
            ->label($label)
            ->placeholder(__('Choose from Storage'))
            ->searchable()
            ->getSearchResultsUsing(fn (string $search): array => self::searchOptions(
                $search,
                $imagesOnly,
                $includeDimensions,
                $allowedMimeTypes,
                $modifyQueryUsing,
                $visual,
            ))
            ->searchDebounce(AdminControl::SEARCH_DEBOUNCE_MS)
            ->searchPrompt(__('Search Storage by filename or ALT text'))
            ->noSearchResultsMessage(__('No matching files in Storage'))
            ->allowHtml();
    }

    /**
     * @param  Builder<MediaAsset>  $query
     * @param  list<string>|null  $allowedMimeTypes
     */
    private static function constrainAvailable(
        Builder $query,
        bool $imagesOnly,
        ?array $allowedMimeTypes = null,
        ?Closure $modifyQueryUsing = null,
    ): void {
        $query->where('state', 'available');

        if ($allowedMimeTypes !== null) {
            $query->whereIn('mime_type', $allowedMimeTypes);
        } elseif ($imagesOnly) {
            $query->where('mime_type', 'like', 'image/%');
        }

        if ($modifyQueryUsing instanceof Closure) {
            $modifyQueryUsing($query);
        }
    }

    private static function optionLabel(MediaAsset $asset, bool $includeDimensions, bool $visual = false): string
    {
        $asset->loadMissing('variants');
        $filename = e((string) $asset->getAttribute('original_filename'));

        /** @var Collection<int, MediaVariant> $variants */
        $variant = \App\Domain\Media\MediaPreviewVariant::preview($asset);

        if ($visual) {
            $preview = $variant instanceof MediaVariant
                ? '<img src="'.e(route('admin.media.variant', $variant)).'" alt="" width="160" height="120" loading="lazy" decoding="async">'
                : '<span class="admin-media-choice__unavailable">'.e(__('Preview unavailable')).'</span>';

            $details = $includeDimensions && $asset->getAttribute('width') && $asset->getAttribute('height')
                ? e($asset->getAttribute('width').'×'.$asset->getAttribute('height'))
                : '';
            $alt = trim((string) $asset->getAttribute('alt_text'));

            return '<span class="admin-media-choice">'
                .'<span class="admin-media-choice__preview">'.$preview.'</span>'
                .'<span class="admin-media-choice__meta"><strong>'.$filename.'</strong>'
                .($details !== '' ? '<small>'.$details.'</small>' : '')
                .($alt !== '' ? '<small class="admin-media-choice__alt">'.e($alt).'</small>' : '')
                .'</span></span>';
        }

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

    private static function cacheKey(int $id, bool $includeDimensions, bool $visual = false): string
    {
        return $id.':'.($includeDimensions ? 'full' : 'compact').($visual ? ':visual' : '');
    }
}
