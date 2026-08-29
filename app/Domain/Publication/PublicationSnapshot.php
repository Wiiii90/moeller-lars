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
}
