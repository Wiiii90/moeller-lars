<?php

use App\Domain\Content\HomeHeroConfigurationService;
use App\Domain\Content\HomePresentationEditorialService;
use App\Domain\Content\HomePresentationResolver;
use App\Filament\Pages\HomePresentation;
use App\Models\AdminNotification;
use App\Models\AuditEvent;
use App\Models\User;
use Filament\Facades\Filament;
use Livewire\Livewire;

beforeEach(function (): void {
    $this->actingAs(User::factory()->admin()->create(), 'web');
    Filament::setCurrentPanel('admin');
    Filament::bootCurrentPanel();

    $settings = app(HomePresentationResolver::class)->settings();
    $settings->setAttribute('template', 'artwork');
    $settings->setAttribute('configuration', HomePresentationEditorialService::defaults());
    $settings->save();
});

it('persists every Hero display toggle from the real live Edit dialog and restores it on reopen', function (): void {
    $settings = app(HomePresentationResolver::class)->settings();
    $component = Livewire::test(HomePresentation::class)->mountAction('settings');

    $values = [
        'show_details' => false,
        'show_gallery_link' => true,
        'show_navigation' => true,
        'enable_viewer' => false,
    ];

    foreach ($values as $field => $value) {
        $beforeNotifications = AdminNotification::query()->count();
        $beforeAudits = AuditEvent::query()
            ->where('action', 'site_section.updated')
            ->where('entity_type', 'site_section')
            ->where('entity_id', (int) $settings->getAttribute('site_section_id'))
            ->count();

        $component->set("mountedActions.0.data.{$field}", $value)
            ->assertDispatched('admin-header-notification');

        $persisted = app(HomeHeroConfigurationService::class)->configuration($settings->fresh());
        expect($persisted[$field])->toBe($value)
            ->and(AdminNotification::query()->count())->toBe($beforeNotifications + 1)
            ->and(AuditEvent::query()
                ->where('action', 'site_section.updated')
                ->where('entity_type', 'site_section')
                ->where('entity_id', (int) $settings->getAttribute('site_section_id'))
                ->count())->toBe($beforeAudits + 1);
    }

    $component->call('unmountAction')->mountAction('settings');
    foreach ($values as $field => $value) {
        $component->assertSet("mountedActions.0.data.{$field}", $value);
    }
});

it('preserves the stored Home menu choice without duplicate feedback when a Hero autosave commits again', function (bool $visible): void {
    $settings = app(HomePresentationResolver::class)->settings();
    $section = $settings->siteSection()->firstOrFail();
    app(\App\Domain\Content\SiteSectionEditorialService::class)->updateHomeNavigationVisibility($section, $visible);
    app(\App\Domain\Admin\AdminActivityNotificationContext::class)->discard();
    $component = Livewire::test(HomePresentation::class)->mountAction('settings')
        ->assertActionDataSet(['show_in_navigation' => $visible])
        ->set('mountedActions.0.data.show_gallery_link', true)
        ->assertDispatched('admin-header-notification');

    $beforeNotifications = AdminNotification::query()->count();
    $beforeAudits = AuditEvent::query()
        ->where('action', 'site_section.updated')
        ->where('entity_type', 'site_section')
        ->where('entity_id', (int) $settings->getAttribute('site_section_id'))
        ->count();

    $component->call('callMountedAction')->assertHasNoFormErrors()
        ->assertNotDispatched('admin-header-notification');

    expect(app(HomeHeroConfigurationService::class)->configuration($settings->fresh())['show_gallery_link'])->toBeTrue()
        ->and($section->fresh()->show_in_navigation)->toBe($visible)
        ->and(AdminNotification::query()->count())->toBe($beforeNotifications)
        ->and(AuditEvent::query()
            ->where('action', 'site_section.updated')
            ->where('entity_type', 'site_section')
            ->where('entity_id', (int) $settings->getAttribute('site_section_id'))
            ->count())->toBe($beforeAudits);
})->with(['visible in menu' => true, 'hidden from menu' => false]);

it('defaults Gallery link off while retaining previously saved on values', function (): void {
    $service = app(HomeHeroConfigurationService::class);
    $settings = app(HomePresentationResolver::class)->settings();

    expect($service->configuration($settings)['show_gallery_link'])->toBeFalse();

    expect($service->updateArtworkSettings($settings, ['show_gallery_link' => true]))->toBeTrue();
    expect($service->configuration($settings->fresh())['show_gallery_link'])->toBeTrue();
});

it('rejects a mounted Hero update after admin access is lost', function (bool $guest): void {
    $actor = auth()->user();
    $settings = app(HomePresentationResolver::class)->settings();
    $editor = Livewire::test(HomePresentation::class)->mountAction('settings');
    $before = AuditEvent::query()->count();
    if ($guest) { \Illuminate\Support\Facades\Auth::guard('web')->logout(); }
    else { $actor->forceFill(['is_admin' => false])->save(); }
    $editor->set('mountedActions.0.data.show_gallery_link', true)->assertForbidden();
    expect(app(HomeHeroConfigurationService::class)->configuration($settings->fresh())['show_gallery_link'])->toBeFalse()
        ->and(AuditEvent::query()->count())->toBe($before)->and(AdminNotification::query()->count())->toBe(0);
})->with(['guest' => true, 'revoked admin' => false]);

it('rolls back Hero autosave when its audit fails and permits a successful retry', function (): void {
    $settings = app(HomePresentationResolver::class)->settings();
    $editor = Livewire::test(HomePresentation::class)->mountAction('settings');
    $before = AuditEvent::query()->count();
    $realAudit = app(\App\Domain\Admin\AdminAuditService::class);
    $audit = Mockery::mock($realAudit);
    $audit->shouldReceive('record')->once()->andThrow(new RuntimeException('Audit unavailable'));
    app()->instance(\App\Domain\Admin\AdminAuditService::class, $audit);
    app()->forgetInstance(HomeHeroConfigurationService::class);
    try {
        expect(fn () => $editor->set('mountedActions.0.data.show_gallery_link', true))->toThrow(RuntimeException::class, 'Audit unavailable');
        expect(app(HomeHeroConfigurationService::class)->configuration($settings->fresh())['show_gallery_link'])->toBeFalse()
            ->and(AuditEvent::query()->count())->toBe($before)->and(AdminNotification::query()->count())->toBe(0);
    } finally {
        app()->instance(\App\Domain\Admin\AdminAuditService::class, $realAudit);
        app()->forgetInstance(HomeHeroConfigurationService::class);
    }
    Livewire::test(HomePresentation::class)->mountAction('settings')
        ->set('mountedActions.0.data.show_gallery_link', true)->assertDispatched('admin-header-notification');
    expect(app(HomeHeroConfigurationService::class)->configuration($settings->fresh())['show_gallery_link'])->toBeTrue()
        ->and(AuditEvent::query()->count())->toBe($before + 1)->and(AdminNotification::query()->count())->toBe(1);
});
