<?php

namespace App\Domain\Artwork;

use Illuminate\Database\Eloquent\Builder;
use App\Models\ArtworkCategory;
use App\Models\MediaAsset;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

/** Shared stable Artwork and primary-Media-added sorting for Gallery and Home. */
final class ArtworkSort
{
    public const NEWEST_ARTWORK = 'newest_artwork';
    public const OLDEST_ARTWORK = 'oldest_artwork';
    public const NEWEST_MEDIA_ADDED = 'newest_media_added';
    public const OLDEST_MEDIA_ADDED = 'oldest_media_added';

    public const SOURCE_RULES = [
        self::NEWEST_MEDIA_ADDED => 'Newest by Media Added',
        self::NEWEST_ARTWORK => 'Newest by Artwork',
        self::OLDEST_MEDIA_ADDED => 'Oldest by Media Added',
        self::OLDEST_ARTWORK => 'Oldest by Artwork',
    ];

    public const DATE_DESC_SQL = 'artworks.year DESC NULLS LAST, artworks.month DESC NULLS LAST, artworks.day DESC NULLS LAST, artworks.created_at DESC, artworks.id DESC';
    public const DATE_ASC_SQL = 'artworks.year ASC NULLS LAST, artworks.month ASC NULLS LAST, artworks.day ASC NULLS LAST, artworks.created_at ASC, artworks.id ASC';

    /**
     * Estimate a draft's insertion rank without writing a provisional Artwork.
     * Actual persistence and public rendering always use apply() in SQL.
     * The new id and created_at are unknown until insertion; ties and
     * concurrent edits can shift this non-authoritative form suggestion.
     */
    public static function suggestedPosition(ArtworkCategory $gallery, ?string $date, ?int $mediaAssetId): int
    {
        $mode = GalleryPresentation::sortMode($gallery);
        if ($mode === GalleryPresentation::SORT_MANUAL) {
            return 1;
        }

        $rule = self::canonicalRule($mode);
        $oldest = in_array($rule, [self::OLDEST_ARTWORK, self::OLDEST_MEDIA_ADDED], true);
        $mediaOrder = in_array($rule, [self::NEWEST_MEDIA_ADDED, self::OLDEST_MEDIA_ADDED], true);
        $existing = GalleryPresentation::orderArtworks(
            $gallery->artworks()->getQuery(), $gallery,
        )->get(['artworks.id', 'artworks.year', 'artworks.month', 'artworks.day', 'artworks.created_at']);

        $createdAt = self::timestamp(CarbonImmutable::now('UTC'));
        $candidateDate = ArtworkDate::normalize($date);
        $mediaTime = null;
        $mediaByArtwork = [];
        if ($mediaOrder) {
            if ($mediaAssetId !== null && $mediaAssetId > 0) {
                $mediaTime = self::timestamp(MediaAsset::query()->whereKey($mediaAssetId)->value('created_at'));
            }
            $artworkIds = $existing->modelKeys();
            if ($artworkIds !== []) {
                $primaryLinks = DB::table('artwork_media')
                    ->join('media_assets', 'media_assets.id', '=', 'artwork_media.media_asset_id')
                    ->whereIn('artwork_media.artwork_id', $artworkIds)
                    ->where('artwork_media.role', 'primary')
                    ->orderBy('artwork_media.position')
                    ->orderBy('artwork_media.id')
                    ->get(['artwork_media.artwork_id', 'media_assets.created_at']);
                foreach ($primaryLinks as $link) {
                    $mediaByArtwork[$link->artwork_id] ??= self::timestamp($link->created_at);
                }
            }
        }

        foreach ($existing->values() as $index => $artwork) {
            if ($mediaOrder) {
                $comparison = self::compare($mediaTime, $mediaByArtwork[$artwork->getKey()] ?? null, $oldest);
                if ($comparison === 0) {
                    $comparison = self::compare(PHP_INT_MAX, (int) $artwork->getKey(), $oldest);
                }
            } else {
                $comparison = 0;
                foreach (['year', 'month', 'day'] as $part) {
                    $comparison = self::compare($candidateDate[$part], $artwork->getAttribute($part), $oldest);
                    if ($comparison !== 0) {
                        break;
                    }
                }
                if ($comparison === 0) {
                    $comparison = self::compare($createdAt, self::timestamp($artwork->getAttribute('created_at')), $oldest);
                }
                if ($comparison === 0) {
                    $comparison = self::compare(PHP_INT_MAX, (int) $artwork->getKey(), $oldest);
                }
            }

            if ($comparison < 0) {
                return $index + 1;
            }
        }

        return $existing->count() + 1;
    }

    /** NULL always sorts last, including Oldest-first ordering. */
    private static function compare(?int $left, ?int $right, bool $oldest): int
    {
        if ($left === null || $right === null) {
            return $left === $right ? 0 : ($left === null ? 1 : -1);
        }

        return $oldest ? ($left <=> $right) : ($right <=> $left);
    }

    private static function timestamp(mixed $value): ?int
    {
        if ($value === null) {
            return null;
        }

        $date = CarbonImmutable::parse($value)->utc();

        return $date->getTimestamp() * 1000000 + (int) $date->format('u');
    }

    /**
     * Gallery persists compact sort keys in varchar(16), whereas Home
     * already stores canonical source-rule keys in JSON. Both resolve here.
     * No second sorting algorithm or compatibility forwarding layer.
     */
    private static function canonicalRule(string $rule): string
    {
        return match ($rule) {
            GalleryPresentation::SORT_NEWEST => self::NEWEST_ARTWORK,
            GalleryPresentation::SORT_NEWEST_MEDIA => self::NEWEST_MEDIA_ADDED,
            GalleryPresentation::SORT_OLDEST_MEDIA => self::OLDEST_MEDIA_ADDED,
            default => $rule,
        };
    }

    /**
     * The single SQL ordering contract shared by ordinary Artwork lists and
     * the per-Gallery representative window. No duplicate date/media rule.
     */
    public static function orderClause(string $rule): string
    {
        $rule = self::canonicalRule($rule);
        if ($rule === self::NEWEST_ARTWORK || $rule === self::OLDEST_ARTWORK) {
            return $rule === self::OLDEST_ARTWORK ? self::DATE_ASC_SQL : self::DATE_DESC_SQL;
        }

        if ($rule === self::NEWEST_MEDIA_ADDED || $rule === self::OLDEST_MEDIA_ADDED) {
            $direction = $rule === self::OLDEST_MEDIA_ADDED ? 'ASC' : 'DESC';

            return "(SELECT media_assets.created_at FROM media_assets
                    JOIN artwork_media ON artwork_media.media_asset_id = media_assets.id
                    WHERE artwork_media.artwork_id = artworks.id AND artwork_media.role = 'primary'
                    ORDER BY artwork_media.position, artwork_media.id LIMIT 1)
                    {$direction} NULLS LAST, artworks.id {$direction}";
        }

        throw new \InvalidArgumentException('Unsupported Artwork ordering rule.');
    }

    /** @template T of \Illuminate\Database\Eloquent\Model
     * @param Builder<T> $query
     * @return Builder<T>
     */
    public static function apply(Builder $query, string $rule): Builder
    {
        return $query->orderByRaw(self::orderClause($rule));
    }
}
