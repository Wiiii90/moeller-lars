<?php

use App\Domain\Admin\AdminNotifier;
use App\Domain\Admin\DashboardFeed;
use App\Models\AdminNotification;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('keeps immediate toast feedback ephemeral', function (): void {
    $user = User::factory()->admin()->create();
    $this->actingAs($user);

    app(AdminNotifier::class)->toast(
        title: 'Artwork saved',
        body: 'The editorial change was stored.',
        status: 'success',
    );

    expect(AdminNotification::query()->count())->toBe(0);
});

it('persists structured inbox notifications without rendered browser markup', function (): void {
    $user = User::factory()->admin()->create();

    $notification = app(AdminNotifier::class)->inbox(
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

    expect($notification->getAttribute('user_id'))->toBe($user->getKey())
        ->and($notification->getAttribute('source_id'))->toBe('media-processing:asset-72:job-938')
        ->and($notification->getAttribute('type'))->toBe('media.processing_failure')
        ->and($notification->getAttribute('status'))->toBe('danger')
        ->and($notification->getAttribute('title'))->toBe('Media processing failed')
        ->and($notification->getAttribute('body'))->toBe('The preview could not be generated.')
        ->and($notification->getAttribute('action_url'))->toBe('/admin/media-assets/72/edit')
        ->and($notification->getAttribute('action_label'))->toBe('Open file')
        ->and($notification->getAttribute('entity_type'))->toBe('media_asset')
        ->and($notification->getAttribute('entity_id'))->toBe(72)
        ->and($notification->getAttribute('metadata'))->toBe(['variant' => 'preview']);
});

it('deduplicates persistent notifications by recipient and source id', function (): void {
    $user = User::factory()->admin()->create();
    $notifier = app(AdminNotifier::class);

    $first = $notifier->inbox(
        user: $user,
        sourceId: 'storage-capacity:critical:2026-09-14',
        title: 'Storage capacity critical',
        status: 'warning',
    );
    $second = $notifier->inbox(
        user: $user,
        sourceId: 'storage-capacity:critical:2026-09-14',
        title: 'This duplicate must not create another row',
        status: 'danger',
    );

    expect($second->getKey())->toBe($first->getKey())
        ->and(AdminNotification::query()->where('user_id', $user->getKey())->count())->toBe(1)
        ->and($second->getAttribute('title'))->toBe('Storage capacity critical')
        ->and($second->getAttribute('status'))->toBe('warning');
});

it('keeps the same source id independent between recipients', function (): void {
    $firstUser = User::factory()->admin()->create();
    $secondUser = User::factory()->admin()->create();
    $notifier = app(AdminNotifier::class);

    $notifier->inbox($firstUser, 'system:shared-condition', 'First recipient');
    $notifier->inbox($secondUser, 'system:shared-condition', 'Second recipient');

    expect(AdminNotification::query()->count())->toBe(2)
        ->and(AdminNotification::query()->where('user_id', $firstUser->getKey())->value('title'))->toBe('First recipient')
        ->and(AdminNotification::query()->where('user_id', $secondUser->getKey())->value('title'))->toBe('Second recipient');
});

it('keeps dashboard notification reads isolated to the authenticated user', function (): void {
    $firstUser = User::factory()->admin()->create();
    $secondUser = User::factory()->admin()->create();
    $notifier = app(AdminNotifier::class);

    $first = $notifier->inbox($firstUser, 'system:first', 'First notification');
    $second = $notifier->inbox($secondUser, 'system:second', 'Second notification');

    $this->actingAs($firstUser);
    $page = app(DashboardFeed::class)->paginate('', 'notification');

    expect($page['total'])->toBe(1)
        ->and($page['items'][0]['notification_id'])->toBe($first->getKey())
        ->and(app(DashboardFeed::class)->entry('notification:'.$second->getKey()))->toBeNull();
});

it('has no DOM recorder in the admin panel notification path', function (): void {
    $provider = file_get_contents(app_path('Providers/Filament/AdminPanelProvider.php'));

    expect($provider)->not->toContain('admin-notification-history')
        ->and(file_exists(app_path('Livewire/Admin/AdminNotificationRecorder.php')))->toBeFalse()
        ->and(file_exists(resource_path('views/livewire/admin/admin-notification-recorder.blade.php')))->toBeFalse()
        ->and(file_exists(resource_path('views/filament/partials/admin-notification-history.blade.php')))->toBeFalse();
});
