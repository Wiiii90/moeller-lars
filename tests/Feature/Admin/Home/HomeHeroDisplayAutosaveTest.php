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

it('does not produce duplicate notifications or audit records when the autosaving dialog commits again', function (): void {
    $settings = app(HomePresentationResolver::class)->settings();
    $component = Livewire::test(HomePresentation::class)->mountAction('settings')
        ->set('mountedActions.0.data.show_gallery_link', true)
        ->assertDispatched('admin-header-notification');

    $beforeNotifications = AdminNotification::query()->count();
    $beforeAudits = AuditEvent::query()
        ->where('action', 'site_section.updated')
        ->where('entity_type', 'site_section')
        ->where('entity_id', (int) $settings->getAttribute('site_section_id'))
        ->count();

    $component->call('callMountedAction');

    expect(app(HomeHeroConfigurationService::class)->configuration($settings->fresh())['show_gallery_link'])->toBeTrue()
        ->and(AdminNotification::query()->count())->toBe($beforeNotifications)
        ->and(AuditEvent::query()
            ->where('action', 'site_section.updated')
            ->where('entity_type', 'site_section')
            ->where('entity_id', (int) $settings->getAttribute('site_section_id'))
            ->count())->toBe($beforeAudits);
});

it('defaults Gallery link off while retaining previously saved on values', function (): void {
    $service = app(HomeHeroConfigurationService::class);
    $settings = app(HomePresentationResolver::class)->settings();

    expect($service->configuration($settings)['show_gallery_link'])->toBeFalse();

    expect($service->updateArtworkSettings($settings, ['show_gallery_link' => true]))->toBeTrue();
    expect($service->configuration($settings->fresh())['show_gallery_link'])->toBeTrue();
});
