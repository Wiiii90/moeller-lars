<?php

use App\Filament\Pages\CustomPageWorkspace;
use App\Models\CustomPageSetting;
use App\Models\SiteSection;
use App\Models\User;
use Filament\Facades\Filament;
use Livewire\Livewire;

beforeEach(function (): void {
    $this->actingAs(User::factory()->admin()->create(), 'web');
    config()->set('analytics.matomo.reporting_enabled', false);
    Filament::setCurrentPanel('admin');
    Filament::bootCurrentPanel();
});

/** @return array{0:SiteSection,1:CustomPageSetting} */

require_once __DIR__.'/CustomPageFixtures.php';

it('moves a component into and out of an Image through native sort groups without losing content', function (): void {
    [$section, $settings] = customPageWorkspaceRecord('Native hierarchy', 'native-hierarchy');
    $asset = customPageWorkspaceImage('native-hierarchy');
    $settings->update(['blocks' => [
        ['type' => 'text', 'title' => 'Statement', 'body' => 'Keep this content'],
        ['type' => 'image', 'media_asset_id' => $asset->id, 'image_decorative' => true],
    ]]);
    $before = $settings->fresh()->components();
    $audits = \App\Models\AuditEvent::query()->count();
    $component = Livewire::test(CustomPageWorkspace::class, ['section' => $section->id]);
    $component->call('sortComponent', '0:text', 0, '1')->assertHasNoErrors()
        ->assertDispatched('admin-header-notification');
    expect($settings->fresh()->components()[0]['children'])->toBe([$before[0]])
        ->and(\App\Models\AdminNotification::query()->count())->toBe(1)
        ->and(\App\Models\AuditEvent::query()->count())->toBe($audits + 1);
    $component->call('sortImageChild', '0.0:text', 0, 'root')->assertHasNoErrors();
    expect($settings->fresh()->components())->toBe($before)
        ->and(\App\Models\AdminNotification::query()->count())->toBe(2)
        ->and(\App\Models\AuditEvent::query()->count())->toBe($audits + 2);
    $component->call('sortComponent', '0:text', 0, 'root')
        ->assertNotDispatched('admin-header-notification');
    expect(\App\Models\AdminNotification::query()->count())->toBe(2)
        ->and(\App\Models\AuditEvent::query()->count())->toBe($audits + 2);
});

it('allows canonical reorder only for a neutral complete parent sequence', function (): void {
    [$section, $settings] = customPageWorkspaceRecord('Reorder test', 'reorder-test');
    $settings->update(['blocks' => customPageWorkspaceBlocks(3)]);

    Livewire::test(CustomPageWorkspace::class, ['section' => $section->id])
        ->call('reorderComponents', ['2:list', '1:list', '0:list']);

    expect(array_column($settings->fresh()->components(), 'title'))->toBe([
        'Component 3',
        'Component 2',
        'Component 1',
    ]);

    $filtered = Livewire::test(CustomPageWorkspace::class, ['section' => $section->id])
        ->set('componentSearch', 'Component 2');
    $filtered->call('reorderComponents', ['1:list']);
    expect(array_column($settings->fresh()->components(), 'title'))->toBe([
        'Component 3',
        'Component 2',
        'Component 1',
    ]);

    [$largeSection, $largeSettings] = customPageWorkspaceRecord('Paged reorder test', 'paged-reorder-test');
    $largeSettings->update(['blocks' => customPageWorkspaceBlocks(30)]);

    Livewire::test(CustomPageWorkspace::class, ['section' => $largeSection->id])
        ->call('reorderComponents', array_map(static fn (int $index): string => $index.':list', range(24, 0)));

    expect($largeSettings->fresh()->components()[0]['title'])->toBe('Component 1');
});


it('persists native parent drag sorting through the canonical component order service', function (): void {
    [$section, $settings] = customPageWorkspaceRecord('CV parent order', 'cv-parent-order');
    $settings->update(['blocks' => customPageWorkspaceBlocks(3)]);

    Livewire::test(CustomPageWorkspace::class, ['section' => $section->id])
        ->call('sortComponent', '2:list', 0)
        ->assertHasNoErrors();

    expect(array_column($settings->fresh()->components(), 'title'))->toBe([
        'Component 3',
        'Component 1',
        'Component 2',
    ]);
});

it('audits and notifies a real component reorder once and stays silent for repetition', function (): void {
    [$section, $settings] = customPageWorkspaceRecord('Order feedback', 'order-feedback');
    $settings->update(['blocks' => customPageWorkspaceBlocks(3)]);
    $before = \App\Models\AuditEvent::query()->count();
    $editor = Livewire::test(CustomPageWorkspace::class, ['section' => $section->id])
        ->call('sortComponent', '2:list', 0)->assertHasNoErrors()->assertSee('Component 3');
    expect(array_column($settings->fresh()->components(), 'title'))->toBe(['Component 3', 'Component 1', 'Component 2'])
        ->and(\App\Models\AuditEvent::query()->count())->toBe($before + 1)
        ->and(\App\Models\AdminNotification::query()->count())->toBe(1)
        ->and(collect($editor->effects['dispatches'] ?? [])->where('name', 'admin-header-notification')->count())->toBe(1);
    $editor->call('sortComponent', '0:list', 0)->assertNotDispatched('admin-header-notification');
    expect(\App\Models\AuditEvent::query()->count())->toBe($before + 1)
        ->and(\App\Models\AdminNotification::query()->count())->toBe(1);
});

it('rejects invalid component order sequences without partial changes or feedback', function (array $targets): void {
    [$section, $settings] = customPageWorkspaceRecord('Invalid order', 'invalid-order');
    $settings->update(['blocks' => customPageWorkspaceBlocks(3)]);
    $before = \App\Models\AuditEvent::query()->count();
    Livewire::test(CustomPageWorkspace::class, ['section' => $section->id])
        ->call('reorderComponents', $targets)->assertHasErrors(['component'])->assertNotDispatched('admin-header-notification');
    expect(array_column($settings->fresh()->components(), 'title'))->toBe(['Component 1', 'Component 2', 'Component 3'])
        ->and(\App\Models\AuditEvent::query()->count())->toBe($before)
        ->and(\App\Models\AdminNotification::query()->count())->toBe(0);
})->with([
    'missing component' => [['0:list', '1:list']],
    'duplicate component' => [['0:list', '0:list', '2:list']],
    'wrong type' => [['0:list', '1:text', '2:list']],
]);

it('rolls back a reordered component sequence if its audit fails', function (): void {
    [$section, $settings] = customPageWorkspaceRecord('Atomic order', 'atomic-order');
    $settings->update(['blocks' => customPageWorkspaceBlocks(3)]);
    $before = \App\Models\AuditEvent::query()->count();
    $audit = Mockery::mock(app(\App\Domain\Admin\AdminAuditService::class));
    $audit->shouldReceive('recordOrdering')->once()->andThrow(new RuntimeException('Audit unavailable'));
    app()->instance(\App\Domain\Admin\AdminAuditService::class, $audit);
    app()->forgetInstance(\App\Domain\Content\CustomPageEditorialService::class);
    expect(fn () => Livewire::test(CustomPageWorkspace::class, ['section' => $section->id])->call('sortComponent', '2:list', 0))
        ->toThrow(RuntimeException::class, 'Audit unavailable');
    expect(array_column($settings->fresh()->components(), 'title'))->toBe(['Component 1', 'Component 2', 'Component 3'])
        ->and(\App\Models\AuditEvent::query()->count())->toBe($before)
        ->and(\App\Models\AdminNotification::query()->count())->toBe(0);
});
