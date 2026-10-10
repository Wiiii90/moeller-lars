<?php

use App\Filament\Pages\Dashboard;
use App\Models\AdminNotification;
use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;

uses(RefreshDatabase::class);

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
    $original = $user->getAttribute('dashboard_notification_filter');

    Livewire::test(Dashboard::class)
        ->call('mountAction', 'dashboardSettings')
        ->set('mountedActions.0.data.notification_filter', 'success')
        ->call('unmountAction')
        ->assertSet('mountedActions', []);

    expect($user->fresh()->getAttribute('dashboard_notification_filter'))->toBe($original);
});

it('keeps an invalid edit mounted without persisting the incomplete form', function (): void {
    $user = auth()->user();
    $original = $user->getAttribute('dashboard_notification_filter');

    Livewire::test(Dashboard::class)
        ->call('mountAction', 'dashboardSettings')
        ->set('mountedActions.0.data.notification_filter', null)
        ->call('callMountedAction')
        ->assertHasErrors()
        ->assertSet('mountedActions.0.name', 'dashboardSettings');

    expect($user->fresh()->getAttribute('dashboard_notification_filter'))->toBe($original);
});

it('uses one native edit persistence path without parallel autosave or save-on-close', function (): void {
    $adapter = file_get_contents(app_path('Filament/Support/Dialogs/AdminDialog.php'));
    $homeSettings = file_get_contents(app_path('Filament/Support/HomeSettingsDialog.php'));

    expect($adapter)->toContain("'wire:change' => 'callMountedAction'")
        ->and($adapter)->toContain('$action->halt();')
        ->and($adapter)->toContain('->modalSubmitAction(false)')
        ->and($adapter)->toContain('->modalCancelAction(false)')
        ->and($adapter)->not->toContain('persistMountedAdminEdit')
        ->and($adapter)->not->toContain('unmountAction(')
        ->and($adapter)->not->toContain('focusout')
        ->and($adapter)->not->toContain('setTimeout')
        ->and($homeSettings)->not->toContain('persistMountedAdminEdit')
        ->and(file_exists(app_path('Filament/Support/Dialogs/InteractsWithAdminEditDialogAutosave.php')))->toBeFalse();
});
