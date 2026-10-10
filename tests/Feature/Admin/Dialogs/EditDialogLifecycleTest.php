<?php

use App\Filament\Pages\Dashboard;
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

it('persists an edit through the native action lifecycle without unmounting the dialog', function (): void {
    $user = auth()->user();

    $component = Livewire::test(Dashboard::class)
        ->call('mountAction', 'dashboardSettings')
        ->assertSet('mountedActions.0.name', 'dashboardSettings')
        ->set('mountedActions.0.data.notification_filter', 'unread')
        ->call('callMountedAction')
        ->assertSet('mountedActions.0.name', 'dashboardSettings');

    expect($user->fresh()->dashboard_notification_filter)->toBe('unread');

    $component->call('unmountAction')->assertSet('mountedActions', []);
});

it('closes the native dialog without executing the edit action', function (): void {
    $user = auth()->user();

    Livewire::test(Dashboard::class)
        ->call('mountAction', 'dashboardSettings')
        ->set('mountedActions.0.data.notification_filter', 'unread')
        ->call('unmountAction')
        ->assertSet('mountedActions', []);

    expect($user->fresh()->dashboard_notification_filter)->not->toBe('unread');
});

it('uses one native edit commit entrypoint and no template-specific second autosave call', function (): void {
    $adapter = file_get_contents(app_path('Filament/Support/Dialogs/AdminDialog.php'));
    $homeSettings = file_get_contents(app_path('Filament/Support/HomeSettingsDialog.php'));

    expect($adapter)->toContain("'wire:change' => 'callMountedAction'")
        ->and($adapter)->toContain('$action->halt();')
        ->and($adapter)->not->toContain('persistMountedAdminEdit')
        ->and($homeSettings)->not->toContain('persistMountedAdminEdit')
        ->and(file_exists(app_path('Filament/Support/Dialogs/InteractsWithAdminEditDialogAutosave.php')))->toBeFalse();
});
