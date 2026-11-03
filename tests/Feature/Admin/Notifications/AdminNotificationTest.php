<?php

use App\Domain\Admin\AdminNotifier;
use App\Domain\Admin\DashboardFeed;
use App\Models\AdminNotification;
use App\Models\AuditEvent;
use App\Models\PublicContentSetting;
use App\Models\User;
use Livewire\Livewire;
use Tests\Feature\Admin\Notifications\AdminHeaderNotificationProbe;


it('persists an authenticated admin Notification and projects it into the header', function (): void {
    $user = User::factory()->admin()->create();
    $this->actingAs($user);

    Livewire::test(AdminHeaderNotificationProbe::class)
        ->call('emitNotification')
        ->assertDispatched('admin-header-notification', function (string $event, array $params): bool {
            $message = $params['notification'] ?? [];

            return $event === 'admin-header-notification'
                && is_string($message['id'] ?? null)
                && ($message['notification_id'] ?? null) !== null
                && ($message['title'] ?? null) === 'Changes saved'
                && ($message['body'] ?? null) === 'Background gradient updated.'
                && ($message['status'] ?? null) === 'success';
        });

    $notification = AdminNotification::query()->where('user_id', $user->getKey())->sole();

    expect($notification->getAttribute('title'))->toBe('Changes saved')
        ->and($notification->getAttribute('body'))->toBe('Background gradient updated.')
        ->and($notification->getAttribute('status'))->toBe('success')
        ->and((string) $notification->getAttribute('source_id'))->toStartWith('notification:');
});


it('sends one action Notification for two persisted setting changes while retaining both audits', function (): void {
    $actor = User::factory()->admin()->create();
    $this->actingAs($actor, 'web');
    $auditsBefore = AuditEvent::query()->count();

    Livewire::test(AdminHeaderNotificationProbe::class)
        ->call('emitCombinedSettings')
        ->assertDispatched('admin-header-notification', function (string $event, array $params): bool {
            return $event === 'admin-header-notification'
                && ($params['notification']['title'] ?? null) === 'Contact settings saved'
                && ($params['notification']['status'] ?? null) === 'success';
        });

    $changes = AuditEvent::query()->where('action', 'public_content_setting.updated')
        ->orderBy('id')->pluck('id')->map(static fn ($id): int => (int) $id)->all();
    $notice = AdminNotification::query()->where('user_id', $actor->getKey())->sole();

    expect(PublicContentSetting::general()->getAttribute('public_email'))->toBe('studio@example.invalid')
        ->and(PublicContentSetting::general()->getAttribute('contact_recipient_email'))->toBe('private@example.invalid')
        ->and(AuditEvent::query()->count())->toBe($auditsBefore + 2)
        ->and(count($changes))->toBe(2)
        ->and($notice->getAttribute('title'))->toBe('Contact settings saved')
        ->and($notice->getAttribute('status'))->toBe('success')
        ->and($notice->getAttribute('source_id'))->toBe('activity:'.$changes[1])
        ->and((int) $notice->getAttribute('audit_event_id'))->toBe($changes[1])
        ->and($notice->getAttribute('metadata'))->toBe(['audit_event_ids' => $changes]);
});

it('reports one durable upload result with correct saved, reused and failed semantics', function (
    string $destination, int $ready, int $reused, int $failed, array $errors,
    string $expectedTitle, string $expectedStatus,
): void {
    $actor = User::factory()->admin()->create();
    $this->actingAs($actor, 'web');

    $summary = app(\App\Domain\Admin\AdminMediaUploadFeedback::class)
        ->batch($ready, $reused, $failed, $errors, $destination);

    $notification = AdminNotification::query()->where('user_id', $actor->id)->sole();
    expect($notification->title)->toBe($expectedTitle)
        ->and($notification->status)->toBe($expectedStatus)
        ->and(AdminNotification::query()->count())->toBe(1)
        ->and($summary)->not->toBeEmpty();

    if ($ready > 0) {
        expect($notification->body)->toContain($destination === 'gallery' ? 'ready for Artwork creation' : 'saved to Storage');
    }
    if ($reused > 0) {
        expect($notification->body)->toContain('reused from Storage');
    }
    if ($errors !== []) {
        expect($notification->body)->toContain($errors[0])
            ->and($summary)->toContain($errors[0]);
    }
})->with([
    'new Storage original' => ['storage', 1, 0, 0, [], 'Media uploaded', 'success'],
    'reused Storage original' => ['storage', 0, 1, 0, [], 'Already in Storage', 'info'],
    'mixed Storage batch' => ['storage', 1, 0, 1, ['large.jpg — re-export as baseline JPEG'], 'Upload completed with issues', 'warning'],
    'failed upload' => ['storage', 0, 0, 1, ['invalid.mp4 — unsupported stream'], 'Upload failed', 'danger'],
    'mixed Gallery queue' => ['gallery', 2, 1, 1, ['portrait.png — incomplete transfer'], 'Artwork media ready with issues', 'warning'],
]);

it('does not issue a successful upload notification for an empty batch', function (): void {
    $actor = User::factory()->admin()->create();
    $this->actingAs($actor, 'web');

    expect(app(\App\Domain\Admin\AdminMediaUploadFeedback::class)
        ->batch(0, 0, 0, [], 'storage'))->toBe('No media files selected.')
        ->and(AdminNotification::query()->where('user_id', $actor->id)->count())->toBe(0);
});

it('persists structured Notifications without requiring a browser session', function (): void {
    $user = User::factory()->admin()->create();

    app(AdminNotifier::class)->notification(
        user: $user,
        sourceId: 'media-processing:asset-72:job-938',
        title: '<strong>Media processing failed</strong>',
        body: '<p>The preview could not be generated.</p>',
        status: 'danger',
        context: [
            'type' => 'media.processing_failure',
            'action_url' => '/admin/media-assets/72/edit',
            'action_label' => 'Open file',
            'entity_type' => 'media_asset',
            'entity_id' => 72,
            'metadata' => ['variant' => 'preview'],
        ],
    );

    $notification = AdminNotification::query()
        ->where('user_id', $user->getKey())
        ->where('source_id', 'media-processing:asset-72:job-938')
        ->sole();

    expect($notification->getAttribute('type'))->toBe('media.processing_failure')
        ->and($notification->getAttribute('status'))->toBe('danger')
        ->and($notification->getAttribute('title'))->toBe('Media processing failed')
        ->and($notification->getAttribute('body'))->toBe('The preview could not be generated.')
        ->and($notification->getAttribute('action_url'))->toBe('/admin/media-assets/72/edit')
        ->and($notification->getAttribute('action_label'))->toBe('Open file')
        ->and($notification->getAttribute('entity_type'))->toBe('media_asset')
        ->and($notification->getAttribute('entity_id'))->toBe(72)
        ->and($notification->getAttribute('metadata'))->toBe(['variant' => 'preview']);
});

it('still projects a repeated idempotent source immediately without duplicating the mailbox row', function (): void {
    $user = User::factory()->admin()->create();
    $this->actingAs($user);

    $component = Livewire::test(AdminHeaderNotificationProbe::class);
    $component->call('emitIdempotent', (int) $user->getKey())
        ->assertDispatched('admin-header-notification');

    $component->call('emitIdempotent', (int) $user->getKey())
        ->assertDispatched('admin-header-notification');

    expect(AdminNotification::query()
        ->where('user_id', $user->getKey())
        ->where('source_id', 'media-cleanup:asset-72')
        ->count())->toBe(1);
});

it('deduplicates an explicit Notification source for one recipient', function (): void {
    $user = User::factory()->admin()->create();
    $notifier = app(AdminNotifier::class);

    $notifier->notification(
        user: $user,
        sourceId: 'storage-capacity:critical:2026-09-14',
        title: 'Storage capacity critical',
        status: 'warning',
    );
    $notifier->notification(
        user: $user,
        sourceId: 'storage-capacity:critical:2026-09-14',
        title: 'This duplicate must not create another row',
        status: 'danger',
    );

    $notification = AdminNotification::query()->where('user_id', $user->getKey())->sole();

    expect($notification->getAttribute('title'))->toBe('Storage capacity critical')
        ->and($notification->getAttribute('status'))->toBe('warning');
});

it('keeps the same Notification source independent between recipients', function (): void {
    $firstUser = User::factory()->admin()->create();
    $secondUser = User::factory()->admin()->create();
    $notifier = app(AdminNotifier::class);

    $notifier->notification(user: $firstUser, sourceId: 'system:shared-condition', title: 'First recipient');
    $notifier->notification(user: $secondUser, sourceId: 'system:shared-condition', title: 'Second recipient');

    expect(AdminNotification::query()->count())->toBe(2)
        ->and(AdminNotification::query()->where('user_id', $firstUser->getKey())->value('title'))->toBe('First recipient')
        ->and(AdminNotification::query()->where('user_id', $secondUser->getKey())->value('title'))->toBe('Second recipient');
});

it('keeps Dashboard Notification reads isolated to the authenticated user', function (): void {
    $firstUser = User::factory()->admin()->create();
    $secondUser = User::factory()->admin()->create();
    $notifier = app(AdminNotifier::class);

    $notifier->notification(user: $firstUser, sourceId: 'system:first', title: 'First notification');
    $notifier->notification(user: $secondUser, sourceId: 'system:second', title: 'Second notification');

    $first = AdminNotification::query()->where('user_id', $firstUser->getKey())->sole();
    $second = AdminNotification::query()->where('user_id', $secondUser->getKey())->sole();

    $this->actingAs($firstUser);
    $page = app(DashboardFeed::class)->paginate('', 'notification');

    expect($page['total'])->toBe(1)
        ->and($page['items'][0]['notification_id'])->toBe($first->getKey())
        ->and(app(DashboardFeed::class)->entry('notification:'.$second->getKey()))->toBeNull();
});
