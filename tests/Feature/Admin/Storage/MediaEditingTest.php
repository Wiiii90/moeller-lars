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

beforeEach(function (): void {
    $this->actingAs(User::factory()->admin()->create(), 'web');
});

require_once __DIR__.'/MediaReferenceFixtures.php';

it('opens Preview and Edit as workspace actions and saves canonical metadata in place', function (): void {
    $asset = workspaceReferenceAsset('dialog-image.jpg');

    Livewire::test(ListMediaAssets::class)
        ->mountAction('preview', ['asset' => $asset->id])
        ->assertMountedActionModalSee('dialog-image.jpg')
        ->assertMountedActionModalSee('Metadata')
        ->assertMountedActionModalSee('Used in');

    Livewire::test(ListMediaAssets::class)
        ->mountAction('edit', ['asset' => $asset->id])
        ->fillForm([
            'alt_text' => 'Updated canonical ALT',
            'credit' => 'Studio credit',
            'copyright_notice_mode' => MediaAsset::COPYRIGHT_OVERRIDE,
            'copyright_notice' => 'All rights reserved',
        ])
        ->callMountedAction()
        ->assertHasNoFormErrors();

    $asset->refresh();
    expect($asset->getAttribute('alt_text'))->toBe('Updated canonical ALT')
        ->and($asset->getAttribute('credit'))->toBe('Studio credit')
        ->and($asset->getAttribute('copyright_notice_mode'))->toBe(MediaAsset::COPYRIGHT_OVERRIDE)
        ->and($asset->getAttribute('copyright_notice'))->toBe('All rights reserved')
        ->and($asset->getAttribute('state'))->toBe('available');
});
