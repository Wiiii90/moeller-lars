<?php

namespace App\Filament\Support;

use App\Domain\Media\MediaPreviewVariant;
use App\Filament\Support\Controls\AdminControl;
use App\Models\MediaAsset;
use App\Models\MediaVariant;
use Closure;
use Filament\Forms\Components\Select;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

final class MediaAssetSelect
{
    public const PAGE_SIZE = 30;

    private const OPTION_LABEL_CACHE = 'admin.media_asset_select.option_labels';

    public static function make(
        string $name,
        string $relationship,
        string|Closure $label,
        bool $imagesOnly = false,
        bool $includeDimensions = true,
    ): Select {
        return self::configure(MediaAssetPicker::make($name), $label, $imagesOnly, $includeDimensions, visual: true)
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

                $label = self::optionLabel($record, $includeDimensions, true);
                self::cacheOptionLabel((int) $record->getKey(), $includeDimensions, true, $label);

                return $label;
            });
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
            MediaAssetPicker::make($name),
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

                $label = self::optionLabel($asset, true, $visual);
                self::cacheOptionLabel((int) $asset->getKey(), true, $visual, $label);

                return $label;
            });

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
        ?int $maxResults = null,
    ): array {
        $query = self::optionQuery($search, $imagesOnly, $allowedMimeTypes, $modifyQueryUsing);

        if ($maxResults !== null) {
            $query->limit(max(1, min($maxResults, 100)));
        }

        $assets = $query->with('variants')->get();

        // This explicit synchronous search API is retained for existing
        // consumers and tests; dialogs use pageOptions() instead.
        $cache = request()->attributes->get(self::OPTION_LABEL_CACHE, []);
        if (! is_array($cache)) {
            $cache = [];
        }

        $options = [];
        foreach ($assets as $asset) {
            $id = (int) $asset->getKey();
            $label = self::optionLabel($asset, $includeDimensions, $visual);
            $options[$id] = $label;
            $cache[self::cacheKey($id, $includeDimensions, $visual)] = $label;
        }

        request()->attributes->set(self::OPTION_LABEL_CACHE, $cache);

        return $options;
    }

    /**
     * Fetch one bounded page for the shared Storage picker. This is invoked
     * only when the user opens its list, searches, or scrolls near the end.
     *
     * @param list<string>|null $allowedMimeTypes
     * @return array{options:list<array{value:string,label:string}>,hasMore:bool}
     */
    public static function pageOptions(
        int $offset,
        string $search = '',
        bool $imagesOnly = false,
        bool $includeDimensions = true,
        ?array $allowedMimeTypes = null,
        ?Closure $modifyQueryUsing = null,
        bool $visual = true,
    ): array {
        $query = self::optionQuery($search, $imagesOnly, $allowedMimeTypes, $modifyQueryUsing);
        $assets = $query
            ->offset(max(0, $offset))
            ->limit(self::PAGE_SIZE + 1)
            ->with('variants')
            ->get();

        $hasMore = $assets->count() > self::PAGE_SIZE;
        $cache = request()->attributes->get(self::OPTION_LABEL_CACHE, []);
        if (! is_array($cache)) {
            $cache = [];
        }

        $options = [];
        foreach ($assets->take(self::PAGE_SIZE) as $asset) {
            $id = (int) $asset->getKey();
            $label = self::optionLabel($asset, $includeDimensions, $visual);
            $options[] = ['value' => (string) $id, 'label' => $label];
            $cache[self::cacheKey($id, $includeDimensions, $visual)] = $label;
        }

        request()->attributes->set(self::OPTION_LABEL_CACHE, $cache);

        return ['options' => $options, 'hasMore' => $hasMore];
    }

    /**
     * @param list<string>|null $allowedMimeTypes
     * @return Builder<MediaAsset>
     */
    private static function optionQuery(
        string $search,
        bool $imagesOnly,
        ?array $allowedMimeTypes,
        ?Closure $modifyQueryUsing,
    ): Builder {
        /** @var Builder<MediaAsset> $query */
        $query = MediaAsset::query();
        self::constrainAvailable($query, $imagesOnly, $allowedMimeTypes, $modifyQueryUsing);

        $term = trim($search);
        if ($term !== '') {
            $query->where(static fn (Builder $query): Builder => $query
                ->where('original_filename', 'ilike', '%'.$term.'%')
                ->orWhere('alt_text', 'ilike', '%'.$term.'%'));
        }

        if ($term === '') {
            $query->orderByDesc('created_at')->orderByDesc('id');
        } else {
            $query->orderBy('original_filename')->orderBy('id');
        }

        return $query;
    }

    private static function cacheOptionLabel(int $id, bool $includeDimensions, bool $visual, string $label): void
    {
        $cache = request()->attributes->get(self::OPTION_LABEL_CACHE, []);
        if (! is_array($cache)) {
            $cache = [];
        }

        $cache[self::cacheKey($id, $includeDimensions, $visual)] = $label;
        request()->attributes->set(self::OPTION_LABEL_CACHE, $cache);
    }

    public static function primeOptionLabel(MediaAsset $asset, bool $includeDimensions = true, bool $visual = false): void
    {
        $asset->loadMissing('variants');
        self::cacheOptionLabel(
            (int) $asset->getKey(),
            $includeDimensions,
            $visual,
            self::optionLabel($asset, $includeDimensions, $visual),
        );
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
        MediaAssetPicker $select,
        string|Closure $label,
        bool $imagesOnly,
        bool $includeDimensions,
        ?array $allowedMimeTypes = null,
        ?Closure $modifyQueryUsing = null,
        bool $visual = false,
    ): MediaAssetPicker {
        if ($visual) {
            $select->extraAlpineAttributes(['class' => 'admin-media-picker--visual'], merge: true);
        }

        return $select
            ->label($label)
            ->placeholder(__('Choose from Storage'))
            ->searchable()
            ->native(false)
            ->mediaPages(fn (int $offset, ?string $search): array => self::pageOptions(
                $offset,
                $search ?? '',
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

        $variant = MediaPreviewVariant::preview($asset);

        if ($visual) {
            $preview = $variant instanceof MediaVariant
                ? '<img src="'.e(route('admin.media.variant', $variant)).'" alt="" width="160" height="120" loading="lazy" decoding="async">'
                : '<span class="admin-media-choice__unavailable">'.e(__('Preview unavailable')).'</span>';

            $details = $includeDimensions && $asset->getAttribute('width') && $asset->getAttribute('height')
                ? e($asset->getAttribute('width').'×'.$asset->getAttribute('height'))
                : '';
            $alt = trim((string) $asset->getAttribute('alt_text'));
            $secondary = implode(', ', array_filter([
                $alt !== '' ? e($alt) : '',
                $details,
            ], static fn (string $value): bool => $value !== ''));

            return '<span class="admin-media-choice">'
                .'<span class="admin-media-choice__preview">'.$preview.'</span>'
                .'<span class="admin-media-choice__meta"><strong>'.$filename.'</strong>'
                .($secondary !== '' ? '<small>'.$secondary.'</small>' : '')
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
