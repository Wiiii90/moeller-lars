<?php

use App\Domain\Content\HomePresentationResolver;
use App\Domain\Content\HomeRoutingSettingsService;
use App\Domain\Content\SiteSectionEditorialService;
use App\Filament\Pages\SitePages;
use App\Models\AuditEvent;
use App\Models\User;
use Livewire\Livewire;

beforeEach(function (): void {
    \Filament\Facades\Filament::setCurrentPanel('admin');
    \Filament\Facades\Filament::bootCurrentPanel();
});

it('toggles Skip Home directly without mounting an Action dialog and records both changes', function (): void {
    $this->actingAs(User::factory()->admin()->create(), 'web');

    $pages = app(SiteSectionEditorialService::class);
    $next = $pages->createCustomPage('Direct Skip Target', 'direct-skip-target');
    $pages->updatePlacement($next, 'published', null);
    $routing = app(HomeRoutingSettingsService::class);
    $resolver = app(HomePresentationResolver::class);
    $settings = $resolver->settings();

    $component = Livewire::test(SitePages::class);
    expect($routing->enabled($settings))->toBeFalse();

    $before = AuditEvent::query()->where('action', 'site_section.updated')->count();
    $component->call('toggleSkipHome');
    expect($routing->enabled($settings->fresh()))->toBeTrue()
        ->and($routing->configuredTargetId($settings->fresh()))->toBeNull()
        ->and($resolver->skipTarget()?->getKey())->toBe($next->getKey())
        ->and(AuditEvent::query()->where('action', 'site_section.updated')->count())->toBe($before + 1);

    $component->call('toggleSkipHome');
    expect($routing->enabled($settings->fresh()))->toBeFalse()
        ->and(AuditEvent::query()->where('action', 'site_section.updated')->count())->toBe($before + 2);
});

it('rejects Skip Home without an eligible destination, leaving routing and audit unchanged', function (): void {
    $this->actingAs(User::factory()->admin()->create(), 'web');

    $resolver = app(HomePresentationResolver::class);
    $routing = app(HomeRoutingSettingsService::class);
    $settings = $resolver->settings();
    $before = AuditEvent::query()->count();

    Livewire::test(SitePages::class)->call('toggleSkipHome');

    expect($routing->enabled($settings->fresh()))->toBeFalse()
        ->and(AuditEvent::query()->count())->toBe($before);
});


it('selects and clears a Skip Home destination directly from Pages without any dialog', function (): void {
    $this->actingAs(User::factory()->admin()->create(), 'web');
    $pages = app(SiteSectionEditorialService::class);
    $first = $pages->createCustomPage('Skip Destination One', 'skip-destination-one');
    $second = $pages->createCustomPage('Skip Destination Two', 'skip-destination-two');
    $pages->updatePlacement($first, 'published', null);
    $pages->updatePlacement($second, 'published', null);
    $routing = app(HomeRoutingSettingsService::class);
    $settings = app(HomePresentationResolver::class)->settings();

    $component = Livewire::test(SitePages::class)->call('toggleSkipHome');
    $before = AuditEvent::query()->where('action', 'site_section.updated')->count();

    $component->call('changeSkipHomeTarget', (string) $second->getKey());
    $notificationsAfterChange = \App\Models\AdminNotification::query()->count();
    expect($routing->configuredTargetId($settings->fresh()))->toBe((int) $second->getKey())
        ->and(AuditEvent::query()->where('action', 'site_section.updated')->count())->toBe($before + 1);

    // A repeated selection is a true no-op, with no additional audit mutation.
    $component->call('changeSkipHomeTarget', (string) $second->getKey())->assertNotDispatched('admin-header-notification');
    expect(AuditEvent::query()->where('action', 'site_section.updated')->count())->toBe($before + 1)
        ->and(\App\Models\AdminNotification::query()->count())->toBe($notificationsAfterChange);

    $component->call('changeSkipHomeTarget', '');
    expect($routing->configuredTargetId($settings->fresh()))->toBeNull()
        ->and($routing->enabled($settings->fresh()))->toBeTrue()
        ->and(AuditEvent::query()->where('action', 'site_section.updated')->count())->toBe($before + 2);
});

it('rejects invalid or unpublished direct destinations without mutating routing', function (): void {
    $this->actingAs(User::factory()->admin()->create(), 'web');
    $pages = app(SiteSectionEditorialService::class);
    $valid = $pages->createCustomPage('Valid Target', 'valid-target');
    $invalid = $pages->createCustomPage('Unpublished Target', 'unpublished-target');
    $pages->updatePlacement($valid, 'published', null);

    $routing = app(HomeRoutingSettingsService::class);
    $settings = app(HomePresentationResolver::class)->settings();
    $component = Livewire::test(SitePages::class)->call('toggleSkipHome');
    $before = AuditEvent::query()->count();

    $component->call('changeSkipHomeTarget', (string) $invalid->getKey());
    $component->call('changeSkipHomeTarget', 'not-an-id');
    expect($routing->configuredTargetId($settings->fresh()))->toBeNull()
        ->and(AuditEvent::query()->count())->toBe($before);
});
