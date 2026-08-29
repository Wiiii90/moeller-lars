<?php

use App\Domain\Admin\AdminActivityFeed;
use App\Domain\Admin\AdminAuditService;
use App\Domain\Admin\AdminSettingsService;
use App\Domain\Media\MediaAssetEditorialService;
use App\Domain\Publication\PublicationMediaCleanupService;
use App\Domain\Publication\PublicationService;
use App\Domain\Publication\PublicationSnapshot;
use App\Http\Middleware\ProtectArtistPreview;
use App\Models\AuditEvent;
use App\Models\MediaAsset;
use App\Models\MediaVariant;
use App\Models\PublicationCheckpoint;
use App\Models\PublicationCheckpointEvent;
use App\Models\PublicContentSetting;
use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Storage;

beforeEach(function (): void {
    $this->actor = User::factory()->admin()->create();
    $this->actingAs($this->actor, 'web');

    Route::middleware('web')->get('/__publication/probe/value', static function () {
        return response((string) PublicContentSetting::general()->getAttribute('legal_disclaimer'));
    });

    Route::middleware(['web', ProtectArtistPreview::class])->get('/preview/__publication/probe/value', static function () {
        return response((string) PublicContentSetting::general()->getAttribute('legal_disclaimer'));
    });
});

it('keeps working state private until one idempotent Commit checkpoint promotes it', function (): void {
    $initialCheckpointCount = PublicationCheckpoint::query()->count();
    $oldCommitted = (string) DB::table('committed.public_content_settings')
        ->where('scope', PublicContentSetting::SCOPE_GENERAL)
        ->value('legal_disclaimer');
    $newWorking = 'Working legal disclaimer '.fake()->uuid();

    app(AdminSettingsService::class)->updatePublicContent(PublicContentSetting::general(), [
        'legal_disclaimer' => $newWorking,
    ]);

    $event = AuditEvent::query()
        ->where('action', 'public_content_setting.updated')
        ->latest('id')
        ->firstOrFail();

    expect((string) DB::table('public.public_content_settings')
        ->where('scope', PublicContentSetting::SCOPE_GENERAL)
        ->value('legal_disclaimer'))->toBe($newWorking)
        ->and((string) DB::table('committed.public_content_settings')
            ->where('scope', PublicContentSetting::SCOPE_GENERAL)
            ->value('legal_disclaimer'))->toBe($oldCommitted)
        ->and(app(PublicationService::class)->hasPendingChanges())->toBeTrue()
        ->and(PublicationCheckpointEvent::query()->where('audit_event_id', $event->getKey())->exists())->toBeFalse();

    $this->get('/__publication/probe/value')
        ->assertOk()
        ->assertContent($oldCommitted);

    $this->get('/preview/__publication/probe/value')
        ->assertOk()
        ->assertContent($newWorking)
        ->assertHeader('X-Robots-Tag', 'noindex, nofollow, noarchive')
        ->assertHeader('Cache-Control', 'private, no-store, max-age=0, must-revalidate')
        ->assertHeader('Pragma', 'no-cache');

    $pendingActivity = collect(app(AdminActivityFeed::class)->recent(20))
        ->firstWhere('id', (int) $event->getKey());
    expect($pendingActivity)->not->toBeNull()
        ->and($pendingActivity['publication_status'])->toBe('pending')
        ->and($pendingActivity['checkpoint_id'])->toBeNull();

    $checkpoint = app(PublicationService::class)->commit($this->actor, 'Publish working changes');

    expect($checkpoint)->toBeInstanceOf(PublicationCheckpoint::class)
        ->and(PublicationCheckpoint::query()->count())->toBe($initialCheckpointCount + 1)
        ->and(app(PublicationService::class)->hasPendingChanges())->toBeFalse()
        ->and((string) DB::table('committed.public_content_settings')
            ->where('scope', PublicContentSetting::SCOPE_GENERAL)
            ->value('legal_disclaimer'))->toBe($newWorking);

    $link = PublicationCheckpointEvent::query()->where('audit_event_id', $event->getKey())->firstOrFail();
    expect((int) $link->getAttribute('publication_checkpoint_id'))->toBe((int) $checkpoint->getKey());

    $this->get('/__publication/probe/value')
        ->assertOk()
        ->assertContent($newWorking);

    $retry = app(PublicationService::class)->commit($this->actor, 'Duplicate submission');
    expect($retry)->toBeNull()
        ->and(PublicationCheckpoint::query()->count())->toBe($initialCheckpointCount + 1);

    $committedActivity = collect(app(AdminActivityFeed::class)->recent(20))
        ->firstWhere('id', (int) $event->getKey());
    expect($committedActivity)->not->toBeNull()
        ->and($committedActivity['publication_status'])->toBe('committed')
        ->and($committedActivity['checkpoint_id'])->toBe((int) $checkpoint->getKey());
});


it('associates only publication-scoped audit events with Commit checkpoints', function (): void {
    $nonPublicationEvent = app(AdminAuditService::class)->record(
        $this->actor,
        'blog_setting.updated',
        'blog_setting',
        1,
    );

    app(AdminSettingsService::class)->updatePublicContent(PublicContentSetting::general(), [
        'legal_disclaimer' => 'Publication scoped '.fake()->uuid(),
    ]);
    $publicationEvent = AuditEvent::query()
        ->where('action', 'public_content_setting.updated')
        ->latest('id')
        ->firstOrFail();

    $checkpoint = app(PublicationService::class)->commit($this->actor, 'Scoped audit mapping');

    expect($checkpoint)->toBeInstanceOf(PublicationCheckpoint::class)
        ->and(PublicationCheckpointEvent::query()->where('audit_event_id', $publicationEvent->getKey())->exists())->toBeTrue()
        ->and(PublicationCheckpointEvent::query()->where('audit_event_id', $nonPublicationEvent->getKey())->exists())->toBeFalse();

    $activity = collect(app(AdminActivityFeed::class)->recent(20));
    $nonPublicationActivity = $activity->firstWhere('id', (int) $nonPublicationEvent->getKey());
    $publicationActivity = $activity->firstWhere('id', (int) $publicationEvent->getKey());

    expect($nonPublicationActivity)->not->toBeNull()
        ->and($nonPublicationActivity['publication_status'])->toBeNull()
        ->and($nonPublicationActivity['checkpoint_id'])->toBeNull()
        ->and($publicationActivity)->not->toBeNull()
        ->and($publicationActivity['publication_status'])->toBe('committed')
        ->and($publicationActivity['checkpoint_id'])->toBe((int) $checkpoint->getKey());
});

it('keeps Preview authentication and private indexing protections unchanged', function (): void {
    Auth::guard('web')->logout();

    $this->get('/preview/__publication/probe/value')->assertNotFound();

    $this->actingAs($this->actor, 'web')
        ->get('/preview/__publication/probe/value')
        ->assertOk()
        ->assertHeader('X-Robots-Tag', 'noindex, nofollow, noarchive')
        ->assertHeader('Cache-Control', 'private, no-store, max-age=0, must-revalidate')
        ->assertHeader('Pragma', 'no-cache');
});

it('defers deletion of files that still belong to the committed snapshot until Commit', function (): void {
    Storage::fake(config('media.disk'));

    $asset = MediaAsset::query()->create([
        'storage_key' => 'originals/committed-delete.jpg',
        'original_filename' => 'committed-delete.jpg',
        'mime_type' => 'image/jpeg',
        'byte_size' => 4,
        'sha256' => hash('sha256', 'orig'),
        'state' => 'available',
        'alt_text' => 'Committed delete',
    ]);
    $variant = MediaVariant::query()->create([
        'media_asset_id' => $asset->getKey(),
        'variant_kind' => 'thumbnail',
        'storage_key' => 'variants/committed-delete.webp',
        'mime_type' => 'image/webp',
        'byte_size' => 7,
        'sha256' => hash('sha256', 'variant'),
        'transform_profile' => 'public-v1',
        'state' => 'available',
    ]);

    Storage::disk(config('media.disk'))->put($asset->getAttribute('storage_key'), 'orig');
    Storage::disk(config('media.disk'))->put($variant->getAttribute('storage_key'), 'variant');

    expect(app(PublicationService::class)->commit($this->actor))->toBeInstanceOf(PublicationCheckpoint::class);

    app(MediaAssetEditorialService::class)->delete($asset);

    Storage::disk(config('media.disk'))->assertExists($asset->getAttribute('storage_key'));
    Storage::disk(config('media.disk'))->assertExists($variant->getAttribute('storage_key'));
    expect(DB::table('publication_media_cleanups')->count())->toBe(2)
        ->and(app(PublicationService::class)->hasPendingChanges())->toBeTrue();

    expect(app(PublicationService::class)->commit($this->actor))->toBeInstanceOf(PublicationCheckpoint::class);

    Storage::disk(config('media.disk'))->assertMissing($asset->getAttribute('storage_key'));
    Storage::disk(config('media.disk'))->assertMissing($variant->getAttribute('storage_key'));
    expect(DB::table('publication_media_cleanups')->count())->toBe(0);
});


it('retains working-only media while a Working reference still needs the file', function (): void {
    Storage::fake(config('media.disk'));

    $asset = MediaAsset::query()->create([
        'storage_key' => 'originals/working-reference.jpg',
        'original_filename' => 'working-reference.jpg',
        'mime_type' => 'image/jpeg',
        'byte_size' => 4,
        'sha256' => hash('sha256', 'orig'),
        'state' => 'available',
        'alt_text' => 'Working reference',
    ]);
    Storage::disk(config('media.disk'))->put($asset->getAttribute('storage_key'), 'orig');

    $settings = PublicContentSetting::general();
    $settings->setAttribute('favicon_media_asset_id', $asset->getKey());
    $settings->save();
    $asset->setAttribute('state', 'deleted');
    $asset->save();

    $cleanup = app(PublicationMediaCleanupService::class);
    $cleanup->queue((int) $asset->getKey(), [(string) $asset->getAttribute('storage_key')]);
    $cleanup->drain();

    Storage::disk(config('media.disk'))->assertExists($asset->getAttribute('storage_key'));
    expect(DB::table('publication_media_cleanups')->count())->toBe(1);

    $settings->setAttribute('favicon_media_asset_id', null);
    $settings->save();
    $cleanup->drain();

    Storage::disk(config('media.disk'))->assertMissing($asset->getAttribute('storage_key'));
    expect(DB::table('publication_media_cleanups')->count())->toBe(0);
});

it('keeps the publication utilities in exact normal sidebar order without a footer hook', function (): void {
    $provider = file_get_contents(app_path('Providers/Filament/AdminPanelProvider.php'));
    $hook = file_get_contents(resource_path('views/filament/partials/publication-commit-dialog.blade.php'));
    $dialogView = file_get_contents(resource_path('views/livewire/admin/publication-commit-dialog.blade.php'));
    $component = file_get_contents(app_path('Livewire/Admin/PublicationCommitDialog.php'));
    $navOrderMatches = preg_match('/\$storageItem,\s*\$previewItem,\s*\$commitItem,/s', $provider);

    expect(PublicationSnapshot::TABLES)->toBe([
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
    ])
        ->and($navOrderMatches)->toBe(1)
        ->and($provider)->toContain("NavigationItem::make('Preview')")
        ->and($provider)->toContain("NavigationItem::make('Commit')")
        ->and($provider)->toContain('Heroicon::OutlinedEye')
        ->and($provider)->toContain('Heroicon::OutlinedCheckCircle')
        ->and($provider)->toContain("->openUrlInNewTab()")
        ->and($provider)->toContain("data-publication-commit' => 'disabled")
        ->and($provider)->toContain('hasPendingChanges()')
        ->and($provider)->toContain('PanelsRenderHook::BODY_END')
        ->and($provider)->not->toContain('PanelsRenderHook::SIDEBAR_FOOTER')
        ->and($provider)->not->toContain('PanelsRenderHook::SIDEBAR_NAV_END')
        ->and(file_exists(resource_path('views/filament/partials/sidebar-preview.blade.php')))->toBeFalse()
        ->and($hook)->toContain('<livewire:admin.publication-commit-dialog />')
        ->and($dialogView)->toContain('publication-commit-open.window')
        ->and($dialogView)->toContain('<x-filament-actions::modals />')
        ->and($component)->toContain('public function openCommit(): void')
        ->and($component)->toContain('hasPendingChanges()')
        ->and($component)->toContain("Action::make('commitPublication')")
        ->and($component)->toContain('->disabled(fn (): bool => ! app(PublicationService::class)->hasPendingChanges())')
        ->and($component)->toContain("SchemaView::make('filament.schemas.components.publication-summary')")
        ->and($component)->toContain("Textarea::make('message')");
});
