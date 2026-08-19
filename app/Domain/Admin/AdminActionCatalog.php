<?php

namespace App\Domain\Admin;

final class AdminActionCatalog
{
    /** @var array<string, array{label: string, area: string, family: string}> */
    private const DEFINITIONS = [
        'artwork.created' => ['label' => 'Created artwork', 'area' => 'Artwork', 'family' => 'create'],
        'artwork.updated' => ['label' => 'Edited artwork', 'area' => 'Artwork', 'family' => 'edit'],
        'artwork.published' => ['label' => 'Published artwork', 'area' => 'Artwork', 'family' => 'publish'],
        'artwork.unpublished' => ['label' => 'Unpublished artwork', 'area' => 'Artwork', 'family' => 'publish'],
        'artwork.primary_media_attached' => ['label' => 'Attached primary image', 'area' => 'Artwork', 'family' => 'media'],
        'artwork.primary_media_replaced' => ['label' => 'Replaced primary image', 'area' => 'Artwork', 'family' => 'media'],
        'artwork.primary_media_alt_updated' => ['label' => 'Edited primary image ALT text', 'area' => 'Artwork', 'family' => 'media'],
        'artwork.additional_media_attached' => ['label' => 'Added gallery image', 'area' => 'Artwork', 'family' => 'media'],
        'artwork.additional_media_detached' => ['label' => 'Removed gallery image', 'area' => 'Artwork', 'family' => 'media'],
        'artwork.additional_media_reordered' => ['label' => 'Reordered gallery images', 'area' => 'Artwork', 'family' => 'ordering'],
        'artwork_category.created' => ['label' => 'Created Gallery', 'area' => 'Galleries', 'family' => 'create'],
        'artwork_category.updated' => ['label' => 'Edited Gallery', 'area' => 'Galleries', 'family' => 'edit'],
        'artwork_category.published' => ['label' => 'Published Gallery', 'area' => 'Galleries', 'family' => 'publish'],
        'artwork_category.hidden' => ['label' => 'Hidden Gallery', 'area' => 'Galleries', 'family' => 'publish'],
        'artwork_category.slug_changed' => ['label' => 'Changed Gallery URL', 'area' => 'Galleries', 'family' => 'edit'],
        'artwork_category.deleted' => ['label' => 'Deleted Gallery', 'area' => 'Galleries', 'family' => 'lifecycle'],
        'artwork_category.gallery_reordered' => ['label' => 'Reordered artworks', 'area' => 'Galleries', 'family' => 'ordering'],
        'site_section.updated' => ['label' => 'Edited public page placement', 'area' => 'Website', 'family' => 'settings'],
        'site_section.reordered' => ['label' => 'Reordered public navigation', 'area' => 'Website', 'family' => 'ordering'],
        'media.ingested' => ['label' => 'Uploaded media', 'area' => 'Media', 'family' => 'media'],
        'media.metadata_updated' => ['label' => 'Edited media details', 'area' => 'Media', 'family' => 'edit'],
        'media.deleted' => ['label' => 'Deleted media', 'area' => 'Media', 'family' => 'lifecycle'],
        'cv_entry.created' => ['label' => 'Created Vita entry', 'area' => 'Vita', 'family' => 'create'],
        'cv_entry.updated' => ['label' => 'Edited Vita entry', 'area' => 'Vita', 'family' => 'edit'],
        'cv_entry.published' => ['label' => 'Published Vita entry', 'area' => 'Vita', 'family' => 'publish'],
        'cv_entry.unpublished' => ['label' => 'Unpublished Vita entry', 'area' => 'Vita', 'family' => 'publish'],
        'cv_entry.archived' => ['label' => 'Archived Vita entry', 'area' => 'Vita', 'family' => 'lifecycle'],
        'cv_entry.restored_to_draft' => ['label' => 'Restored Vita entry to draft', 'area' => 'Vita', 'family' => 'lifecycle'],
        'cv_entry.reordered' => ['label' => 'Reordered Vita entries', 'area' => 'Vita', 'family' => 'ordering'],
        'exhibition.created' => ['label' => 'Created exhibition', 'area' => 'Exhibitions', 'family' => 'create'],
        'exhibition.updated' => ['label' => 'Edited exhibition', 'area' => 'Exhibitions', 'family' => 'edit'],
        'exhibition.published' => ['label' => 'Published exhibition', 'area' => 'Exhibitions', 'family' => 'publish'],
        'exhibition.unpublished' => ['label' => 'Unpublished exhibition', 'area' => 'Exhibitions', 'family' => 'publish'],
        'exhibition.archived' => ['label' => 'Archived exhibition', 'area' => 'Exhibitions', 'family' => 'lifecycle'],
        'exhibition.restored_to_draft' => ['label' => 'Restored exhibition to draft', 'area' => 'Exhibitions', 'family' => 'lifecycle'],
        'exhibition.reordered' => ['label' => 'Reordered exhibitions', 'area' => 'Exhibitions', 'family' => 'ordering'],
        'blog_post.created' => ['label' => 'Created blog post', 'area' => 'Blog', 'family' => 'create'],
        'blog_post.updated' => ['label' => 'Edited blog post', 'area' => 'Blog', 'family' => 'edit'],
        'blog_post.published' => ['label' => 'Published blog post', 'area' => 'Blog', 'family' => 'publish'],
        'blog_post.scheduled' => ['label' => 'Scheduled blog post', 'area' => 'Blog', 'family' => 'publish'],
        'blog_post.unpublished' => ['label' => 'Unpublished blog post', 'area' => 'Blog', 'family' => 'publish'],
        'blog_post.archived' => ['label' => 'Archived blog post', 'area' => 'Blog', 'family' => 'lifecycle'],
        'blog_post.restored_to_draft' => ['label' => 'Restored blog post to draft', 'area' => 'Blog', 'family' => 'lifecycle'],
        'blog_post.reordered' => ['label' => 'Reordered blog posts', 'area' => 'Blog', 'family' => 'ordering'],
        'blog_setting.updated' => ['label' => 'Edited blog settings', 'area' => 'Blog', 'family' => 'settings'],
        'public_content_setting.updated' => ['label' => 'Edited website settings', 'area' => 'Website', 'family' => 'settings'],
    ];

    /** @return array<int, string> */
    public static function keys(): array
    {
        return array_keys(self::DEFINITIONS);
    }

    public static function has(string $key): bool
    {
        return array_key_exists($key, self::DEFINITIONS);
    }

    /** @return array{label: string, area: string, family: string} */
    public static function definition(string $key): array
    {
        return self::DEFINITIONS[$key] ?? [
            'label' => 'Administrative change',
            'area' => 'Website',
            'family' => 'edit',
        ];
    }

    /** @return array<int, string> */
    public static function keysForArea(string $area): array
    {
        return array_keys(array_filter(
            self::DEFINITIONS,
            static fn (array $definition): bool => $definition['area'] === $area,
        ));
    }

    /** @return array<int, string> */
    public static function keysForFamily(string $family): array
    {
        return array_keys(array_filter(
            self::DEFINITIONS,
            static fn (array $definition): bool => $definition['family'] === $family,
        ));
    }

    /** @return array<string, string> */
    public static function areaOptions(): array
    {
        $areas = array_values(array_unique(array_column(self::DEFINITIONS, 'area')));
        sort($areas);

        return array_combine($areas, $areas);
    }

    /** @return array<string, string> */
    public static function familyOptions(): array
    {
        return [
            'create' => 'Created',
            'edit' => 'Edited',
            'publish' => 'Publication',
            'media' => 'Media',
            'ordering' => 'Ordering',
            'settings' => 'Settings',
            'lifecycle' => 'Archive / delete',
        ];
    }
}
