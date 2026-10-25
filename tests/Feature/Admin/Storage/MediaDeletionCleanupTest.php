<?php

use App\Domain\Content\JournalTemplate;
use App\Domain\Content\SiteSectionType;
use App\Domain\Media\MediaAssetEditorialService;
use App\Domain\Media\MediaReferenceQuery;
use App\Filament\Support\MediaReferenceCatalog;
use App\Models\AdminActionReceipt;
use App\Models\AuditEvent;
use App\Models\BlogPost;
use App\Models\MediaAsset;
use App\Models\User;
use Illuminate\Support\Facades\Storage;

beforeEach(function (): void {
    Storage::fake(config('media.disk'));
    $this->actingAs(User::factory()->admin()->create(), 'web');
});

require_once __DIR__.'/MediaReferenceFixtures.php';

it('removes Custom Page image references before deleting the asset', function (): void {
    $asset = workspaceReferenceAsset('custom-page.jpg');
    $custom = workspaceReferenceNode(SiteSectionType::CustomPage->value, 'CV');
    $custom->customPageSetting()->create([
        'blocks' => [[
            'type' => 'image',
            'media_asset_id' => $asset->id,
            'image_decorative' => false,
        ]],
    ]);

    expect(app(MediaReferenceQuery::class)->isReferenced($asset))->toBeTrue();
    expect(app(MediaAssetEditorialService::class)->delete($asset))->toBeTrue();

    $deletedAsset = $asset->fresh();
    $settings = $custom->customPageSetting()->firstOrFail();
    $afterDeleteCatalog = app(MediaReferenceCatalog::class);
    $afterDeleteCatalog->loadAssetReferences($deletedAsset);

    $cascadeEvent = AuditEvent::query()
        ->where('action', 'site_section.updated')
        ->where('entity_type', 'site_section')
        ->where('entity_id', $custom->getKey())
        ->latest('id')
        ->firstOrFail();

    expect($deletedAsset->getAttribute('state'))->toBe('deleted')
        ->and($settings->components())->toBe([])
        ->and(app(MediaReferenceQuery::class)->isReferenced($deletedAsset))->toBeFalse()
        ->and($afterDeleteCatalog->references($deletedAsset))->toBe([])
        ->and(AdminActionReceipt::query()->where('audit_event_id', $cascadeEvent->getKey())->exists())->toBeFalse();
});


it('records draft Journal rich-text cleanup when referenced media is deleted without exposing unsafe Undo', function (): void {
    $asset = workspaceReferenceAsset('journal-rich-text-delete.jpg');
    $journal = workspaceReferenceNode(SiteSectionType::Journal->value, 'Deletion Journal', JournalTemplate::Blog->value);
    $post = BlogPost::query()->create([
        'site_section_id' => $journal->id,
        'slug' => 'media-deletion-note',
        'title' => 'Media deletion note',
        'body' => "Before\n\n![](media:{$asset->id})",
        'state' => 'draft',
        'position' => 0,
    ]);

    expect(app(MediaReferenceQuery::class)->isReferenced($asset))->toBeTrue();
    expect(app(MediaAssetEditorialService::class)->delete($asset))->toBeTrue();

    $post->refresh();
    $event = AuditEvent::query()
        ->where('action', 'blog_post.media_reference_removed')
        ->where('entity_type', 'blog_post')
        ->where('entity_id', $post->getKey())
        ->latest('id')
        ->firstOrFail();

    expect((string) $post->getAttribute('body'))->not->toContain('media:'.$asset->id)
        ->and($asset->fresh()->state)->toBe('deleted')
        ->and(AuditEvent::query()->where('action', 'media.deleted')->where('entity_id', $asset->id)->count())->toBe(1)
        ->and(AdminActionReceipt::query()->where('audit_event_id', $event->getKey())->exists())->toBeFalse();
});
