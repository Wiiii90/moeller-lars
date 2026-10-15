<?php

use App\Filament\Pages\Dashboard;
use App\Models\AdminNotification;
use App\Models\User;
use Filament\Facades\Filament;
use Livewire\Livewire;

beforeEach(function (): void {
    $this->actingAs(User::factory()->admin()->create(), 'web');
    Filament::setCurrentPanel('admin');
    Filament::bootCurrentPanel();
});

it('persists successive edits through Filament while keeping the dialog open', function (): void {
    $user = auth()->user();

    $component = Livewire::test(Dashboard::class)
        ->call('mountAction', 'dashboardSettings')
        ->assertSet('mountedActions.0.name', 'dashboardSettings')
        ->set('mountedActions.0.data.notification_filter', 'success')
        ->call('callMountedAction')
        ->assertSet('mountedActions.0.name', 'dashboardSettings');

    expect($user->fresh()->dashboard_notification_filter)->toBe('success');

    $component
        ->set('mountedActions.0.data.notification_filter', 'warning')
        ->call('callMountedAction')
        ->assertSet('mountedActions.0.name', 'dashboardSettings');

    expect($user->fresh()->dashboard_notification_filter)->toBe('warning');

    $notificationsAfterSave = AdminNotification::query()->count();
    expect($notificationsAfterSave)->toBeGreaterThan(0);

    $component->call('unmountAction')->assertSet('mountedActions', []);

    expect($user->fresh()->dashboard_notification_filter)->toBe('warning')
        ->and(AdminNotification::query()->count())->toBe($notificationsAfterSave);
});

it('closes the native edit dialog without executing its action', function (): void {
    $user = auth()->user();
    $original = $user->fresh()->getAttribute('dashboard_notification_filter');

    Livewire::test(Dashboard::class)
        ->call('mountAction', 'dashboardSettings')
        ->set('mountedActions.0.data.notification_filter', 'success')
        ->call('unmountAction')
        ->assertSet('mountedActions', []);

    expect($user->fresh()->getAttribute('dashboard_notification_filter'))->toBe($original);
});

it('keeps an invalid edit mounted without persisting the incomplete form', function (): void {
    $user = auth()->user();
    $original = $user->fresh()->getAttribute('dashboard_notification_filter');

    Livewire::test(Dashboard::class)
        ->call('mountAction', 'dashboardSettings')
        ->set('mountedActions.0.data.notification_filter', null)
        ->call('callMountedAction')
        ->assertHasErrors()
        ->assertSet('mountedActions.0.name', 'dashboardSettings');

    expect($user->fresh()->getAttribute('dashboard_notification_filter'))->toBe($original);
});
