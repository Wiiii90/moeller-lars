<?php

namespace App\Domain\Artwork;

use Illuminate\Database\Eloquent\Builder;

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

    /** @template T of \Illuminate\Database\Eloquent\Model
     * @param Builder<T> $query
     * @return Builder<T>
     */
    public static function apply(Builder $query, string $rule): Builder
    {
        if ($rule === self::NEWEST_ARTWORK || $rule === self::OLDEST_ARTWORK) {
            return $query->orderByRaw($rule === self::OLDEST_ARTWORK ? self::DATE_ASC_SQL : self::DATE_DESC_SQL);
        }

        if ($rule === self::NEWEST_MEDIA_ADDED || $rule === self::OLDEST_MEDIA_ADDED) {
            $direction = $rule === self::OLDEST_MEDIA_ADDED ? 'ASC' : 'DESC';

            return $query->orderByRaw(
                "(SELECT media_assets.created_at FROM media_assets
                    JOIN artwork_media ON artwork_media.media_asset_id = media_assets.id
                    WHERE artwork_media.artwork_id = artworks.id AND artwork_media.role = 'primary'
                    ORDER BY artwork_media.position, artwork_media.id LIMIT 1)
                    {$direction} NULLS LAST, artworks.id {$direction}",
            );
        }

        throw new \InvalidArgumentException('Unsupported Artwork ordering rule.');
    }
}
