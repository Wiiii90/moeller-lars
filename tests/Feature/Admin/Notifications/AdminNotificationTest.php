<?php

use App\Domain\Admin\AdminNotifier;
use App\Domain\Admin\DashboardFeed;
use App\Models\AdminNotification;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;
use Livewire\Component;
use Livewire\Livewire;

uses(RefreshDatabase::class);

final class AdminHeaderNotificationProbe extends Component
{
    public function emitNotification(): void
    {
        app(AdminNotifier::class)->notification(
            title: 'Changes saved',
            body: 'Background gradient updated.',
            status: 'success',
        );
    }

    public function emitIdempotent(int $userId): void
    {
        app(AdminNotifier::class)->notification(
            user: $userId,
            sourceId: 'media-cleanup:asset-72',
            title: 'File cleanup failed',
            body: 'Stored file cleanup could not be completed.',
            status: 'danger',
            context: [
                'type' => 'media.cleanup_failure',
                'entity_type' => 'media_asset',
                'entity_id' => 72,
            ],
        );
    }

    public function render(): string
    {
        return '<div></div>';
    }
}

it('persists an authenticated admin Notification and projects it into the header', function (): void {
    $user = User::factory()->admin()->create();
    $this->actingAs($user);

    Livewire::test(AdminHeaderNotificationProbe::class)
        ->call('emitNotification')
        ->assertDispatched('admin-header-notification', function (string $event, array $params): bool {
            $message = $params['notification'] ?? [];

            return $event === 'admin-header-notification'
                && str_starts_with((string) ($message['id'] ?? ''), 'notification:')
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

it('owns Notification delivery without framework notification objects or parallel notifier APIs', function (): void {
    $provider = file_get_contents(app_path('Providers/Filament/AdminPanelProvider.php'));
    $notifier = file_get_contents(app_path('Domain/Admin/AdminNotifier.php'));

    expect($provider)
        ->toContain('PanelsRenderHook::BODY_START')
        ->not->toContain('PanelsRenderHook::TOPBAR_START')
        ->and($notifier)
        ->toContain("dispatch('admin-header-notification'")
        ->not->toContain('Filament\\Notifications')
        ->not->toContain('public function inbox(')
        ->not->toContain('public function both(');

    foreach (File::allFiles(app_path()) as $file) {
        $source = file_get_contents($file->getRealPath());

        expect($source)
            ->not->toContain('Notification::make()')
            ->not->toContain('->transient()');
    }
});
