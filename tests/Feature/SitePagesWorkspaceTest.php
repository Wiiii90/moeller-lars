<?php

use App\Domain\Content\SiteSectionEditorialService;
use App\Domain\Content\SiteSectionOrderService;
use App\Filament\Pages\SitePages;
use App\Models\AuditEvent;
use App\Models\SiteSection;
use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    Filament::setCurrentPanel('admin');
    Filament::bootCurrentPanel();
});

it('serves the concise Pages placement workspace to an admin', function (): void {
    $admin = User::factory()->admin()->create();

    $this->actingAs($admin, 'web')
        ->get(SitePages::getUrl())
        ->assertSuccessful()
        ->assertSee('Pages')
        ->assertSee('Site structure')
        ->assertSee('Preview site')
        ->assertSee('Add page/section')
        ->assertDontSee('Galleries, Journals, Custom Pages and navigation-only nodes')
        ->assertDontSee('Save editorial work first');
});

it('keeps Home pinned outside normal page reordering', function (): void {
    $this->actingAs(User::factory()->admin()->create(), 'web');
    $home = SiteSection::query()->where('type', SiteSection::TYPE_HOME)->firstOrFail();
    $order = app(SiteSectionOrderService::class);

    expect($order->canMove($home, 'up'))->toBeFalse()
        ->and($order->canMove($home, 'down'))->toBeFalse()
        ->and($order->move($home, 'down'))->toBeFalse();
});

it('reorders configurable sibling pages and records the editorial action', function (): void {
    $this->actingAs(User::factory()->admin()->create(), 'web');
    $sections = app(SiteSectionEditorialService::class);
    $first = $sections->createCustomPage('First page', 'first-workspace-page');
    $second = $sections->createCustomPage('Second page', 'second-workspace-page');
    $firstPosition = (int) $first->position;
    $secondPosition = (int) $second->position;

    expect(app(SiteSectionOrderService::class)->move($second, 'up'))->toBeTrue()
        ->and((int) $second->fresh()->position)->toBe($firstPosition)
        ->and((int) $first->fresh()->position)->toBe($secondPosition)
        ->and(AuditEvent::query()->where('action', 'site_section.reordered')->where('entity_id', $second->id)->exists())->toBeTrue();
});
