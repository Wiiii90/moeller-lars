<?php

use App\Domain\Content\JournalTemplate;
use App\Domain\Content\SiteSectionType;
use App\Domain\Media\MediaAssetEditorialService;
use App\Domain\Media\MediaReferenceQuery;
use App\Filament\Resources\MediaAssets\Pages\ListMediaAssets;
use App\Filament\Support\MediaReferenceCatalog;
use App\Filament\Support\SiteNodePresentation;
use App\Models\AdminActionReceipt;
use App\Models\Artwork;
use App\Models\ArtworkCategory;
use App\Models\ArtworkMedia;
use App\Models\AuditEvent;
use App\Models\BlogPost;
use App\Models\JournalEntryMedia;
use App\Models\MediaAsset;
use App\Models\SiteSection;
use App\Models\User;
use Livewire\Livewire;

function workspaceReferenceAsset(
    string $filename,
    string $mime = 'image/jpeg',
    string $state = 'available',
    ?string $alt = 'Test image',
    int $bytes = 4,
): MediaAsset {
    return MediaAsset::query()->create([
        'storage_key' => 'originals/'.$filename,
        'original_filename' => $filename,
        'mime_type' => $mime,
        'byte_size' => $bytes,
        'sha256' => hash('sha256', $filename),
        'state' => $state,
        'alt_text' => $alt,
    ]);
}

function workspaceReferenceNode(string $type, string $title, ?string $template = null, ?int $categoryId = null): SiteSection
{
    static $sequence = 0;

    $sequence++;

    return SiteSection::query()->create([
        'type' => $type,
        'template' => $template,
        'title' => $title,
        'navigation_label' => $title,
        'slug' => str($title)->slug()->append('-workspace-test-'.$sequence)->toString(),
        'state' => 'hidden',
        'position' => 900 + $sequence,
        'parent_id' => null,
        'artwork_category_id' => $categoryId,
    ]);
}
