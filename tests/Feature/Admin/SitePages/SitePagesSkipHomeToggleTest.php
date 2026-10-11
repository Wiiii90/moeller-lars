<?php

use App\Domain\Content\HomePresentationResolver;
use App\Domain\Content\HomeRoutingSettingsService;
use App\Domain\Content\SiteSectionEditorialService;
use App\Filament\Pages\SitePages;
use App\Models\AuditEvent;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;

uses(RefreshDatabase::class);

it('toggles Skip Home directly without mounting an Action dialog and records both changes', function (): void {
    $this->actingAs(User::factory()->admin()->create(), 'web');

    $pages = app(SiteSectionEditorialService::class);
    $next = $pages->createCustomPage('Direct Skip Target', 'direct-skip-target');
    $pages->updatePlacement($next, 'published', true, null);
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
