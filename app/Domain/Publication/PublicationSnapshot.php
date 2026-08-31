<?php

namespace App\Domain\Publication;

final class PublicationSnapshot
{
    public const LOCK_KEY = 16520260829;

    /** @var list<string> */
    public const TABLES = [
        'artwork_categories',
        'artworks',
        'artwork_media',
        'media_assets',
        'media_variants',
        'site_sections',
        'custom_page_settings',
        'journal_settings',
        'journal_entry_media',
        'home_presentation_settings',
        'cv_entries',
        'exhibitions',
        'exhibition_media',
        'blog_posts',
        'public_content_settings',
        'redirects',
    ];

    /** @var list<string> */
    public const AUDIT_ENTITY_TYPES = [
        'artwork_category',
        'artwork',
        'artwork_media',
        'media_asset',
        'media_variant',
        'site_section',
        'custom_page_setting',
        'journal_setting',
        'journal_entry_media',
        'home_presentation_setting',
        'cv_entry',
        'exhibition',
        'exhibition_media',
        'blog_post',
        'public_content_setting',
        'redirect',
    ];

    /** @var array<string, array{area:string, entity:string}> */
    public const GROUPS = [
        'site_sections' => ['area' => 'Website', 'entity' => 'Pages and navigation'],
        'redirects' => ['area' => 'Website', 'entity' => 'Redirects'],
        'public_content_settings' => ['area' => 'Website', 'entity' => 'General'],
        'home_presentation_settings' => ['area' => 'Website', 'entity' => 'Home'],
        'custom_page_settings' => ['area' => 'Website', 'entity' => 'Custom Pages'],
        'journal_settings' => ['area' => 'Journal', 'entity' => 'Journal settings'],
        'blog_posts' => ['area' => 'Journal', 'entity' => 'Blog'],
        'exhibitions' => ['area' => 'Journal', 'entity' => 'Exhibitions'],
        'exhibition_media' => ['area' => 'Journal', 'entity' => 'Exhibition media'],
        'journal_entry_media' => ['area' => 'Journal', 'entity' => 'Journal media'],
        'cv_entries' => ['area' => 'Website', 'entity' => 'CV entries'],
        'artwork_categories' => ['area' => 'Gallery', 'entity' => 'Galleries'],
        'artworks' => ['area' => 'Gallery', 'entity' => 'Artworks'],
        'artwork_media' => ['area' => 'Gallery', 'entity' => 'Artwork media'],
        'media_assets' => ['area' => 'Files', 'entity' => 'Media files'],
        'media_variants' => ['area' => 'Files', 'entity' => 'Media variants'],
    ];

    public static function tracksAuditEntityType(string $entityType): bool
    {
        return in_array($entityType, self::AUDIT_ENTITY_TYPES, true);
    }
}
