<?php

use App\Filament\Pages\Dashboard;
use App\Domain\Admin\DashboardNotificationRetention;
use App\Models\AdminNotification;
use App\Models\User;
use Filament\Facades\Filament;
use Livewire\Livewire;

it('autosaves the Dashboard delete toggle using the shared Filament control', function (): void {
    $user = User::factory()->admin()->create();
    $this->actingAs($user, 'web');
    Filament::setCurrentPanel('admin');
    Filament::bootCurrentPanel();

    $component = Livewire::test(Dashboard::class)->mountAction('dashboardSettings')
        ->assertSet('mountedActions.0.data.delete_without_confirmation', false);

    $notificationCount = AdminNotification::query()->count();
    $component->set('mountedActions.0.data.delete_without_confirmation', true)
        ->assertHasNoFormErrors()
        ->assertDispatched('admin-header-notification')
        ->assertSet('mountedActions.0.name', 'dashboardSettings');

    expect((bool) $user->fresh()->getAttribute('dashboard_delete_without_confirmation'))->toBeTrue()
        ->and(AdminNotification::query()->count())->toBe($notificationCount + 1);

    $notificationCount = AdminNotification::query()->count();
    $component->call('callMountedAction')->assertHasNoFormErrors();
    expect(AdminNotification::query()->count())->toBe($notificationCount);

    $component->call('unmountAction');
    Livewire::test(Dashboard::class)->mountAction('dashboardSettings')
        ->assertSet('mountedActions.0.data.delete_without_confirmation', true)
        ->set('mountedActions.0.data.delete_without_confirmation', false)
        ->assertHasNoFormErrors()
        ->assertDispatched('admin-header-notification');

    expect((bool) $user->fresh()->getAttribute('dashboard_delete_without_confirmation'))->toBeFalse();
});

it('persists combined preferences prunes only own unpinned notifications and reopens current values', function (): void {
    $user = User::factory()->admin()->create(['dashboard_notification_retention' => 25]);
    $other = User::factory()->admin()->create();
    $this->actingAs($user, 'web');
    Filament::setCurrentPanel('admin');
    Filament::bootCurrentPanel();
    $oldest = null;
    foreach (range(1, 15) as $index) {
        $row = AdminNotification::query()->create([
            'user_id' => $user->id, 'source_id' => 'retention:'.$index, 'type' => 'system',
            'status' => 'info', 'title' => 'History '.$index,
        ]);
        $oldest ??= $row;
    }
    app(\App\Domain\Admin\DashboardFeedPins::class)->toggle('notification:'.$oldest->id);
    $foreign = AdminNotification::query()->create([
        'user_id' => $other->id, 'source_id' => 'retention:foreign', 'type' => 'system',
        'status' => 'danger', 'title' => 'Other history',
    ]);
    $component = Livewire::test(Dashboard::class)->mountAction('dashboardSettings')
        ->set('mountedActions.0.data.notification_filter', 'danger')
        ->set('mountedActions.0.data.notification_retention', 10)
        ->set('mountedActions.0.data.delete_without_confirmation', true)
        ->assertHasNoFormErrors()->assertDispatched('admin-header-notification');
    expect(collect($component->effects['dispatches'] ?? [])->where('name', 'admin-header-notification')->count())->toBe(1);
    $component->callMountedAction()->assertHasNoFormErrors()->assertNotDispatched('admin-header-notification');
    expect($user->fresh()->dashboard_notification_filter)->toBe('danger')
        ->and($user->fresh()->dashboard_notification_retention)->toBe(10)
        ->and($user->fresh()->dashboard_delete_without_confirmation)->toBeTrue()
        ->and(AdminNotification::query()->where('user_id', $user->id)->where('status', 'success')->count())->toBe(1)
        ->and(AdminNotification::query()->where('user_id', $user->id)->count())->toBe(10)
        ->and($oldest->fresh())->not->toBeNull()
        ->and($foreign->fresh())->not->toBeNull();
    Livewire::test(Dashboard::class)->mountAction('dashboardSettings')
        ->assertActionDataSet(['notification_filter' => 'danger', 'notification_retention' => 10, 'delete_without_confirmation' => true])
        ->callMountedAction()->assertNotDispatched('admin-header-notification');
});

it('rejects invalid preferences without changing saved settings or sending success', function (): void {
    $user = User::factory()->admin()->create();
    $this->actingAs($user, 'web');
    Filament::setCurrentPanel('admin');
    Filament::bootCurrentPanel();
    Livewire::test(Dashboard::class)->mountAction('dashboardSettings')
        ->fillForm(['notification_filter' => 'invalid', 'notification_retention' => 3])
        ->callMountedAction()->assertHasFormErrors(['notification_filter', 'notification_retention'])
        ->assertNotDispatched('admin-header-notification');
    expect($user->fresh()->dashboard_notification_filter)->toBe('all')
        ->and($user->fresh()->dashboard_notification_retention)->toBe(10)
        ->and(AdminNotification::query()->count())->toBe(0);
});

it('rolls back preferences when notification retention fails', function (): void {
    $user = User::factory()->admin()->create();
    $this->actingAs($user, 'web');
    Filament::setCurrentPanel('admin');
    Filament::bootCurrentPanel();
    $retention = Mockery::mock(app(DashboardNotificationRetention::class));
    $retention->shouldReceive('pruneFor')->once()->andThrow(new RuntimeException('Retention unavailable'));
    app()->instance(DashboardNotificationRetention::class, $retention);
    $component = Livewire::test(Dashboard::class)->mountAction('dashboardSettings');
    expect(fn () => $component->set('mountedActions.0.data.delete_without_confirmation', true))
        ->toThrow(RuntimeException::class, 'Retention unavailable');
    expect($user->fresh()->dashboard_delete_without_confirmation)->toBeFalse()
        ->and(AdminNotification::query()->count())->toBe(0);
});
