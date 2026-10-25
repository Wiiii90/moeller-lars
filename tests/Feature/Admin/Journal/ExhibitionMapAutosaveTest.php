<?php

use App\Domain\Content\ExhibitionEditorialService;
use App\Domain\Content\JournalTemplate;
use App\Domain\Content\SiteSectionEditorialService;
use App\Filament\Pages\JournalWorkspace;
use App\Models\AdminNotification;
use App\Models\AuditEvent;
use App\Models\Exhibition;
use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Livewire\Livewire;

beforeEach(function (): void {
    $this->actingAs(User::factory()->admin()->create(), 'web');
    Filament::setCurrentPanel('admin');
    Filament::bootCurrentPanel();
    config([
        'cache.default' => 'array',
        'services.nominatim.endpoint' => 'https://nominatim.example.test/search',
        'services.nominatim.user_agent' => 'moeller-lars map autosave test',
        'services.nominatim.email' => null,
        'services.nominatim.min_interval_ms' => 1000,
        'services.nominatim.lock_wait_seconds' => 0,
    ]);
    Cache::flush();
});

function journalMapExhibition(array $overrides = []): array
{
    $section = app(SiteSectionEditorialService::class)->createJournal(
        'Map autosave Journal', 'map-autosave-journal', JournalTemplate::Exhibitions->value,
    );
    $entry = app(ExhibitionEditorialService::class)->createDraft([
        'site_section_id' => $section->getKey(),
        'title' => 'Map autosave exhibition',
        'slug' => 'map-autosave-exhibition',
        'city' => 'Hamburg',
        'country' => 'Germany',
        'map_enabled' => false,
        ...$overrides,
    ]);
    app(\App\Domain\Admin\AdminActivityNotificationContext::class)->discard();

    return [$section, $entry];
}

it('persists map switch on/off through normal Edit and hydrates coordinates without closing', function (): void {
    [$section, $entry] = journalMapExhibition();
    Http::fake(fn () => Http::response([[
        'lat' => '53.550341', 'lon' => '9.992477',
        'display_name' => 'Hamburg, Germany',
    ]], 200));
    $beforeAudit = AuditEvent::query()->count();
    $beforeNotifications = AdminNotification::query()->count();

    $editor = Livewire::test(JournalWorkspace::class, ['section' => $section->getKey()])
        ->mountAction('editExhibition', ['exhibition' => $entry->getKey()])
        ->set('mountedActions.0.data.map_enabled', true)
        ->call('callMountedAction')
        ->assertHasNoFormErrors()
        ->assertDispatched('admin-header-notification')
        ->assertSet('mountedActions.0.data.latitude', 53.550341)
        ->assertSet('mountedActions.0.data.longitude', 9.992477);

    expect($entry->fresh()->getAttribute('map_enabled'))->toBeTrue()
        ->and($entry->fresh()->hasCoordinates())->toBeTrue()
        ->and(AuditEvent::query()->count())->toBe($beforeAudit + 1)
        ->and(AdminNotification::query()->count())->toBe($beforeNotifications + 1);

    $editor->set('mountedActions.0.data.map_enabled', false)
        ->call('callMountedAction')
        ->assertHasNoFormErrors();
    expect($entry->fresh()->getAttribute('map_enabled'))->toBeFalse();
    Http::assertSentCount(1);

    Livewire::test(JournalWorkspace::class, ['section' => $section->getKey()])
        ->mountAction('editExhibition', ['exhibition' => $entry->getKey()])
        ->assertSet('mountedActions.0.data.map_enabled', false);
});

it('enables an exhibition map after adding missing Country within the same open dialog', function (): void {
    [$section, $entry] = journalMapExhibition(['country' => null]);
    Http::fake(fn () => Http::response([[
        'lat' => '53.550341', 'lon' => '9.992477',
        'display_name' => 'Hamburg, Deutschland',
    ]], 200));
    $initialNotifications = AdminNotification::query()->count();

    $editor = Livewire::test(JournalWorkspace::class, ['section' => $section->getKey()])
        ->mountAction('editExhibition', ['exhibition' => $entry->getKey()])
        ->set('mountedActions.0.data.country', 'Deutschland')
        ->call('callMountedAction')
        ->assertHasNoFormErrors()
        ->assertDispatched('admin-header-notification')
        ->assertSet('mountedActions.0.data.map_enabled', false);
    expect($entry->fresh()->getAttribute('country'))->toBe('Deutschland')
        ->and(AdminNotification::query()->count())->toBe($initialNotifications + 1);

    $editor->set('mountedActions.0.data.map_enabled', true)
        ->call('callMountedAction')
        ->assertHasNoFormErrors()
        ->assertSet('mountedActions.0.data.map_enabled', true)
        ->assertSet('mountedActions.0.data.latitude', 53.550341);

    expect($entry->fresh()->getAttribute('map_enabled'))->toBeTrue()
        ->and(AdminNotification::query()->count())->toBe($initialNotifications + 2);
    Http::assertSentCount(1);
});

it('re-geocodes changed addresses once and updates the mounted map preview state', function (): void {
    [$section, $entry] = journalMapExhibition();
    Http::fakeSequence()
        ->push([['lat' => '53.550341', 'lon' => '9.992477', 'display_name' => 'Hamburg, Germany']], 200)
        ->push([['lat' => '52.520008', 'lon' => '13.404954', 'display_name' => 'Berlin, Germany']], 200);

    $editor = Livewire::test(JournalWorkspace::class, ['section' => $section->getKey()])
        ->mountAction('editExhibition', ['exhibition' => $entry->getKey()])
        ->set('mountedActions.0.data.map_enabled', true)
        ->call('callMountedAction')
        ->assertHasNoFormErrors()
        ->set('mountedActions.0.data.city', 'Berlin')
        ->call('callMountedAction')
        ->assertHasNoFormErrors()
        ->assertSet('mountedActions.0.data.latitude', 52.520008)
        ->assertSet('mountedActions.0.data.longitude', 13.404954);

    expect($entry->fresh()->getAttribute('city'))->toBe('Berlin')
        ->and((float) $entry->fresh()->getAttribute('latitude'))->toBe(52.520008);
    Http::assertSentCount(2);
    $notificationCount = AdminNotification::query()->count();
    $auditCount = AuditEvent::query()->count();

    $editor->call('callMountedAction')->assertHasNoFormErrors();

    Http::assertSentCount(2);
    expect(AdminNotification::query()->count())->toBe($notificationCount)
        ->and(AuditEvent::query()->count())->toBe($auditCount);
});

it('rejects an incomplete map address without persisting a fake enabled state', function (): void {
    [$section, $entry] = journalMapExhibition(['city' => null, 'postal_code' => null]);
    Http::fake();
    $auditCount = AuditEvent::query()->count();

    Livewire::test(JournalWorkspace::class, ['section' => $section->getKey()])
        ->mountAction('editExhibition', ['exhibition' => $entry->getKey()])
        ->set('mountedActions.0.data.map_enabled', true)
        ->call('callMountedAction')
        ->assertHasFormErrors(['map_enabled'])
        ->assertSet('mountedActions.0.data.map_enabled', false);

    expect($entry->fresh()->getAttribute('map_enabled'))->toBeFalse()
        ->and(AuditEvent::query()->count())->toBe($auditCount);
    Http::assertSentCount(0);
});

it('shows the current Exhibition projection after a combined title address and map edit', function (): void {
    [$section, $entry] = journalMapExhibition();
    Http::fake(fn () => Http::response([['lat' => '52.520008', 'lon' => '13.404954', 'display_name' => 'Berlin, Germany']], 200));
    $before = AuditEvent::query()->count(); $notifications = AdminNotification::query()->count();
    $editor = Livewire::test(JournalWorkspace::class, ['section' => $section->id])
        ->mountAction('editExhibition', ['exhibition' => $entry->id])
        ->set('mountedActions.0.data.title', 'Current Exhibition title')->set('mountedActions.0.data.city', 'Berlin')
        ->set('mountedActions.0.data.map_enabled', true)->callMountedAction()->assertHasNoFormErrors()->assertSee('Current Exhibition title');
    expect($entry->fresh()->title)->toBe('Current Exhibition title')->and($entry->fresh()->city)->toBe('Berlin')
        ->and((float) $entry->fresh()->latitude)->toBe(52.520008)
        ->and(AuditEvent::query()->count())->toBe($before + 1)->and(AdminNotification::query()->count())->toBe($notifications + 1)
        ->and(collect($editor->effects['dispatches'] ?? [])->where('name', 'admin-header-notification')->count())->toBe(1);
    $editor->callMountedAction()->assertNotDispatched('admin-header-notification');
});

it('refuses stale Exhibition editors without overwriting concurrent changes', function (): void {
    [$section, $entry] = journalMapExhibition();
    $editor = Livewire::test(JournalWorkspace::class, ['section' => $section->id])->mountAction('editExhibition', ['exhibition' => $entry->id]);
    app(ExhibitionEditorialService::class)->update($entry, ['site_section_id' => $section->id, 'slug' => $entry->slug, 'title' => 'Concurrent Exhibition title']);
    app(\App\Domain\Admin\AdminActivityNotificationContext::class)->discard();
    $before = AuditEvent::query()->count();
    $editor->set('mountedActions.0.data.title', 'Stale Exhibition title')->callMountedAction()->assertHasFormErrors(['title']);
    expect($entry->fresh()->title)->toBe('Concurrent Exhibition title')->and(AuditEvent::query()->count())->toBe($before)
        ->and(collect($editor->effects['dispatches'] ?? [])->where('name', 'admin-header-notification')->where('params.notification.status', 'success')->count())->toBe(0);
});

it('rolls back geocoded coordinates when Exhibition audit persistence fails', function (): void {
    [$section, $entry] = journalMapExhibition();
    Http::fake(fn () => Http::response([['lat' => '53.550341', 'lon' => '9.992477', 'display_name' => 'Hamburg, Germany']], 200));
    $before = AuditEvent::query()->count();
    $audit = Mockery::mock(app(\App\Domain\Admin\AdminAuditService::class));
    $audit->shouldReceive('record')->once()->andThrow(new RuntimeException('Audit unavailable'));
    app()->instance(\App\Domain\Admin\AdminAuditService::class, $audit);
    app()->forgetInstance(ExhibitionEditorialService::class);
    expect(fn () => Livewire::test(JournalWorkspace::class, ['section' => $section->id])
        ->mountAction('editExhibition', ['exhibition' => $entry->id])->set('mountedActions.0.data.map_enabled', true)->callMountedAction())
        ->toThrow(RuntimeException::class, 'Audit unavailable');
    expect($entry->fresh()->map_enabled)->toBeFalse()->and($entry->fresh()->hasCoordinates())->toBeFalse()
        ->and(AuditEvent::query()->count())->toBe($before)->and(AdminNotification::query()->count())->toBe(0);
});

it('rejects invalid provider coordinates without persisting title address or map changes', function (): void {
    [$section, $entry] = journalMapExhibition();
    Http::fakeSequence()
        ->push([['lat' => '999', 'lon' => '999', 'display_name' => 'Invalid provider result']], 200)
        ->push([['lat' => '52.520008', 'lon' => '13.404954', 'display_name' => 'Berlin, Germany']], 200);
    $before = AuditEvent::query()->count();
    $editor = Livewire::test(JournalWorkspace::class, ['section' => $section->id])->mountAction('editExhibition', ['exhibition' => $entry->id])
        ->set('mountedActions.0.data.title', 'Rejected title')->set('mountedActions.0.data.city', 'Berlin')
        ->set('mountedActions.0.data.map_enabled', true)->callMountedAction()->assertHasFormErrors(['map_enabled']);
    expect($entry->fresh()->title)->toBe('Map autosave exhibition')->and($entry->fresh()->city)->toBe('Hamburg')
        ->and($entry->fresh()->map_enabled)->toBeFalse()->and($entry->fresh()->hasCoordinates())->toBeFalse()
        ->and(AuditEvent::query()->count())->toBe($before)
        ->and(collect($editor->effects['dispatches'] ?? [])->where('name', 'admin-header-notification')->where('params.notification.status', 'success')->count())->toBe(0);
    $editor->set('mountedActions.0.data.map_enabled', true)->callMountedAction()->assertHasNoFormErrors();
    expect($entry->fresh()->map_enabled)->toBeTrue()->and($entry->fresh()->city)->toBe('Berlin')
        ->and((float) $entry->fresh()->latitude)->toBe(52.520008);
});

it('rolls back a failed geocoding attempt instead of saving an invalid enabled map', function (): void {
    [$section, $entry] = journalMapExhibition();
    Http::fake(fn () => Http::response([], 503));
    $auditCount = AuditEvent::query()->count();

    Livewire::test(JournalWorkspace::class, ['section' => $section->getKey()])
        ->mountAction('editExhibition', ['exhibition' => $entry->getKey()])
        ->set('mountedActions.0.data.map_enabled', true)
        ->call('callMountedAction');

    expect($entry->fresh()->getAttribute('map_enabled'))->toBeFalse()
        ->and($entry->fresh()->hasCoordinates())->toBeFalse()
        ->and(AuditEvent::query()->count())->toBe($auditCount);
});
