<?php

use App\Domain\Admin\AdminAuditService;
use App\Domain\Admin\AdminSettingsService;
use App\Domain\Media\MediaAssetEditorialService;
use App\Domain\Publication\PublicationMediaCleanupService;
use App\Domain\Publication\PublicationService;
use App\Domain\Publication\PublicationSnapshot;
use App\Filament\Support\AdminActivityFeed;
use App\Http\Middleware\ProtectArtistPreview;
use App\Livewire\Admin\PublicationCommitDialog;
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
use Illuminate\Testing\TestResponse;
use Livewire\Livewire;

function publicationWorkflowSetGradientBaseline(string $end = '#C9C3C3'): void
{
    $appearance = [
        'background_mode' => 'gradient',
        'background_gradient_start' => '#555555',
        'background_gradient_end' => $end,
        'background_gradient_angle' => 2,
    ];

    foreach (['public', 'committed'] as $schema) {
        DB::table($schema.'.public_content_settings')
            ->where('scope', PublicContentSetting::SCOPE_GENERAL)
            ->update($appearance);
    }
}

function publicationWorkflowUseRenderableHome(): void
{
    foreach (['public', 'committed'] as $schema) {
        DB::table($schema.'.home_presentation_settings')->update([
            'template' => 'custom',
        ]);
    }
}

function publicationWorkflowAssertPrivateNoCache(TestResponse $response): void
{
    $directives = array_map('trim', explode(',', (string) $response->headers->get('Cache-Control')));
    $expected = ['private', 'no-store', 'max-age=0', 'must-revalidate'];
    sort($directives);
    sort($expected);

    expect($directives)->toBe($expected);
}

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

it('keeps the real public layout on committed appearance until one idempotent Commit promotes Working', function (): void {
    publicationWorkflowUseRenderableHome();
    publicationWorkflowSetGradientBaseline();

    $initialCheckpointCount = PublicationCheckpoint::query()->count();
    $oldCommitted = '#C9C3C3';
    $newWorking = '#0F19FA';

    expect(app(PublicationService::class)->hasPendingChanges())->toBeFalse();

    app(AdminSettingsService::class)->updatePublicContent(PublicContentSetting::general(), [
        'background_gradient_end' => $newWorking,
    ]);

    $event = AuditEvent::query()
        ->where('action', 'public_content_setting.updated')
        ->latest('id')
        ->firstOrFail();

    expect((string) DB::table('public.public_content_settings')
        ->where('scope', PublicContentSetting::SCOPE_GENERAL)
        ->value('background_gradient_end'))->toBe($newWorking)
        ->and((string) DB::table('committed.public_content_settings')
            ->where('scope', PublicContentSetting::SCOPE_GENERAL)
            ->value('background_gradient_end'))->toBe($oldCommitted)
        ->and(app(PublicationService::class)->hasPendingChanges())->toBeTrue()
        ->and(PublicationCheckpointEvent::query()->where('audit_event_id', $event->getKey())->exists())->toBeFalse();

    $this->get('/')
        ->assertOk()
        ->assertSee($oldCommitted, false)
        ->assertDontSee($newWorking, false);

    $preview = $this->get('/preview');
    $preview
        ->assertOk()
        ->assertSee($newWorking, false)
        ->assertDontSee($oldCommitted, false)
        ->assertHeader('X-Robots-Tag', 'noindex, nofollow, noarchive')
        ->assertHeader('Pragma', 'no-cache');
    publicationWorkflowAssertPrivateNoCache($preview);

    $pendingActivity = collect(app(AdminActivityFeed::class)->recent(20))
        ->firstWhere('id', (int) $event->getKey());
    expect($pendingActivity)->not->toBeNull()
        ->and($pendingActivity['publication_status'])->toBe('pending')
        ->and($pendingActivity['checkpoint_id'])->toBeNull();

    $checkpoint = app(PublicationService::class)->commit($this->actor);

    expect($checkpoint)->toBeInstanceOf(PublicationCheckpoint::class)
        ->and(PublicationCheckpoint::query()->count())->toBe($initialCheckpointCount + 1)
        ->and(app(PublicationService::class)->hasPendingChanges())->toBeFalse()
        ->and((string) DB::table('committed.public_content_settings')
            ->where('scope', PublicContentSetting::SCOPE_GENERAL)
            ->value('background_gradient_end'))->toBe($newWorking);

    $link = PublicationCheckpointEvent::query()->where('audit_event_id', $event->getKey())->firstOrFail();
    expect((int) $link->getAttribute('publication_checkpoint_id'))->toBe((int) $checkpoint->getKey());

    $this->get('/')
        ->assertOk()
        ->assertSee($newWorking, false)
        ->assertDontSee($oldCommitted, false);

    $retry = app(PublicationService::class)->commit($this->actor);
    expect($retry)->toBeNull()
        ->and(PublicationCheckpoint::query()->count())->toBe($initialCheckpointCount + 1);

    $committedActivity = collect(app(AdminActivityFeed::class)->recent(20))
        ->firstWhere('id', (int) $event->getKey());
    expect($committedActivity)->not->toBeNull()
        ->and($committedActivity['publication_status'])->toBe('committed')
        ->and($committedActivity['checkpoint_id'])->toBe((int) $checkpoint->getKey());
});

it('refreshes Commit state centrally for a Working mutation and a net-zero revert', function (): void {
    publicationWorkflowSetGradientBaseline();

    $control = Livewire::test(PublicationCommitDialog::class)
        ->assertSet('hasPendingChanges', false);

    app(AdminSettingsService::class)->updatePublicContent(PublicContentSetting::general(), [
        'background_gradient_end' => '#0F19FA',
    ]);

    $control->call('refreshState')
        ->assertSet('hasPendingChanges', true);

    app(AdminSettingsService::class)->updatePublicContent(PublicContentSetting::general(), [
        'background_gradient_end' => '#C9C3C3',
    ]);

    $control->call('refreshState')
        ->assertSet('hasPendingChanges', false);

    expect(app(PublicationService::class)->hasPendingChanges())->toBeFalse();
});

it('commits current Pending state in one component action and repeated clicks stay idempotent', function (): void {
    publicationWorkflowSetGradientBaseline();

    app(AdminSettingsService::class)->updatePublicContent(PublicContentSetting::general(), [
        'background_gradient_end' => '#0F19FA',
    ]);

    $initialCheckpointCount = PublicationCheckpoint::query()->count();
    $control = Livewire::test(PublicationCommitDialog::class)
        ->assertSet('hasPendingChanges', true)
        ->call('commitPublication')
        ->assertSet('hasPendingChanges', false);

    expect(PublicationCheckpoint::query()->count())->toBe($initialCheckpointCount + 1)
        ->and(app(PublicationService::class)->hasPendingChanges())->toBeFalse()
        ->and((string) DB::table('committed.public_content_settings')
            ->where('scope', PublicContentSetting::SCOPE_GENERAL)
            ->value('background_gradient_end'))->toBe('#0F19FA');

    $control->call('commitPublication');

    expect(PublicationCheckpoint::query()->count())->toBe($initialCheckpointCount + 1);
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

    $preview = $this->actingAs($this->actor, 'web')
        ->get('/preview/__publication/probe/value');
    $preview
        ->assertOk()
        ->assertHeader('X-Robots-Tag', 'noindex, nofollow, noarchive')
        ->assertHeader('Pragma', 'no-cache');
    publicationWorkflowAssertPrivateNoCache($preview);
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

it('tracks the publication-owned tables copied into committed snapshots', function (): void {
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
    ]);
});
