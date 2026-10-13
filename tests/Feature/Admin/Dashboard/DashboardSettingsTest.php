<?php

use App\Filament\Pages\Dashboard;
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
