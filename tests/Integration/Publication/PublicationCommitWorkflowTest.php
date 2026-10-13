<?php

use App\Domain\Admin\AdminSettingsService;
use App\Domain\Publication\PublicationService;
use App\Filament\Support\AdminActivityFeed;
use App\Livewire\Admin\PublicationStateControl;
use App\Models\AdminNotification;
use App\Models\AuditEvent;
use App\Models\PublicationCheckpoint;
use App\Models\PublicationCheckpointEvent;
use App\Models\PublicContentSetting;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;

require_once __DIR__.'/PublicationWorkflowFixtures.php';

beforeEach(function (): void {
    $this->actor = User::factory()->admin()->create();
    $this->actingAs($this->actor, 'web');
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
        ->and($pendingActivity['publication_status'])->toBe('staged')
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

});


it('keeps Commit truth exact for a Working mutation and a net-zero revert', function (): void {
    publicationWorkflowSetGradientBaseline();

    expect(app(PublicationService::class)->hasPendingChanges())->toBeFalse();

    app(AdminSettingsService::class)->updatePublicContent(PublicContentSetting::general(), [
        'background_gradient_end' => '#0F19FA',
    ]);

    expect(app(PublicationService::class)->hasPendingChanges())->toBeTrue();

    app(AdminSettingsService::class)->updatePublicContent(PublicContentSetting::general(), [
        'background_gradient_end' => '#C9C3C3',
    ]);

    expect(app(PublicationService::class)->hasPendingChanges())->toBeFalse();
});


it('commits current Pending state in one component action and repeated clicks stay idempotent', function (): void {
    publicationWorkflowSetGradientBaseline();

    app(AdminSettingsService::class)->updatePublicContent(PublicContentSetting::general(), [
        'background_gradient_end' => '#0F19FA',
    ]);

    $initialCheckpointCount = PublicationCheckpoint::query()->count();
    $initialNotificationCount = AdminNotification::query()
        ->where('user_id', $this->actor->getKey())
        ->count();

    $control = Livewire::test(PublicationStateControl::class)
        ->call('commitPublication')
        ->assertDispatched('admin-header-notification');

    expect(AdminNotification::query()->where('user_id', $this->actor->getKey())->count())
        ->toBe($initialNotificationCount + 1)
        ->and(PublicationCheckpoint::query()->count())->toBe($initialCheckpointCount + 1)
        ->and(app(PublicationService::class)->hasPendingChanges())->toBeFalse()
        ->and((string) DB::table('committed.public_content_settings')
            ->where('scope', PublicContentSetting::SCOPE_GENERAL)
            ->value('background_gradient_end'))->toBe('#0F19FA');

    $notificationCountAfterCommit = AdminNotification::query()
        ->where('user_id', $this->actor->getKey())
        ->count();
    $control->call('commitPublication');

    expect(PublicationCheckpoint::query()->count())->toBe($initialCheckpointCount + 1)
        ->and(AdminNotification::query()->where('user_id', $this->actor->getKey())->count())
        ->toBe($notificationCountAfterCommit);
});
