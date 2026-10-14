<?php

use App\Filament\Pages\CustomPageWorkspace;
use App\Models\CustomPageSetting;
use App\Models\SiteSection;
use App\Models\User;
use App\Models\AuditEvent;
use App\Models\AdminNotification;
use Illuminate\Support\Facades\Auth;
use Filament\Facades\Filament;
use Livewire\Livewire;

beforeEach(function (): void {
    $this->actingAs(User::factory()->admin()->create(), 'web');
    config()->set('analytics.matomo.reporting_enabled', false);
    Filament::setCurrentPanel('admin');
    Filament::bootCurrentPanel();
});

it('keeps a changed component safe from a stale mounted editor', function (): void {
    [$section, $settings] = customPageWorkspaceRecord('Concurrent component', 'concurrent-component');
    $settings->update(['blocks' => [['type' => 'text', 'published' => true, 'title' => 'Original', 'body' => 'Original body']]]);
    $component = Livewire::test(CustomPageWorkspace::class, ['section' => $section->id])
        ->mountAction('editComponent', ['componentIndex' => 0, 'componentType' => 'text']);
    app(\App\Domain\Content\CustomPageEditorialService::class)->updateBlock($settings, 0, 'text',
        ['type' => 'text', 'published' => true, 'title' => 'Concurrent', 'body' => 'Concurrent body']);
    app(\App\Domain\Admin\AdminActivityNotificationContext::class)->discard();
    $before = AuditEvent::query()->count();
    $component->fillForm(['title' => 'Stale title', 'body' => 'Stale body'])->callMountedAction()
        ->assertHasFormErrors(['type'])->assertNotDispatched('admin-header-notification');
    expect($settings->fresh()->components()[0]['body'])->toBe('Concurrent body')
        ->and(AuditEvent::query()->count())->toBe($before)->and(AdminNotification::query()->count())->toBe(0);
});

it('saves a component once refreshes its projection and repeats without audit or feedback', function (): void {
    [$section, $settings] = customPageWorkspaceRecord('Feedback component', 'feedback-component');
    $settings->update(['blocks' => [['type' => 'text', 'published' => true, 'title' => 'Original', 'body' => 'Body']]]);
    $before = AuditEvent::query()->count();
    $component = Livewire::test(CustomPageWorkspace::class, ['section' => $section->id])
        ->mountAction('editComponent', ['componentIndex' => 0, 'componentType' => 'text'])
        ->fillForm(['title' => 'Persisted title', 'body' => 'Persisted body'])->callMountedAction()
        ->assertHasNoFormErrors()->assertSee('Persisted title');
    expect($settings->fresh()->components()[0]['title'])->toBe('Persisted title')
        ->and(AuditEvent::query()->count())->toBe($before + 1)->and(AdminNotification::query()->count())->toBe(1)
        ->and(collect($component->effects['dispatches'] ?? [])->where('name', 'admin-header-notification')->count())->toBe(1);
    $component->callMountedAction()->assertNotDispatched('admin-header-notification');
    expect(AuditEvent::query()->count())->toBe($before + 1)->and(AdminNotification::query()->count())->toBe(1);
});

it('rejects Custom Page component requests after administrative access is lost', function (bool $guest): void {
    $actor = Auth::user();
    [$section, $settings] = customPageWorkspaceRecord('Protected component', 'protected-component');
    $component = Livewire::test(CustomPageWorkspace::class, ['section' => $section->id]);
    if ($guest) { Auth::guard('web')->logout(); } else { $actor->forceFill(['is_admin' => false])->save(); }
    $component->mountAction('addComponent')->assertForbidden();
    expect($settings->fresh()->components())->toBe([]);
})->with(['guest' => true, 'revoked admin' => false]);

it('rolls back component edits when auditing fails and allows a clean retry', function (): void {
    [$section, $settings] = customPageWorkspaceRecord('Atomic component', 'atomic-component');
    $settings->update(['blocks' => [['type' => 'text', 'published' => true, 'title' => 'Original', 'body' => 'Original body']]]);
    $before = AuditEvent::query()->count();
    $editor = Livewire::test(CustomPageWorkspace::class, ['section' => $section->id])
        ->mountAction('editComponent', ['componentIndex' => 0, 'componentType' => 'text'])
        ->fillForm(['title' => 'Changed', 'body' => 'Changed body']);
    $realAudit = app(\App\Domain\Admin\AdminAuditService::class);
    $audit = Mockery::mock($realAudit);
    $audit->shouldReceive('record')->once()->andThrow(new RuntimeException('Audit unavailable'));
    app()->instance(\App\Domain\Admin\AdminAuditService::class, $audit);
    app()->forgetInstance(\App\Domain\Content\CustomPageEditorialService::class);
    try {
        expect(fn () => $editor->callMountedAction())->toThrow(RuntimeException::class, 'Audit unavailable');
        expect($settings->fresh()->components()[0]['title'])->toBe('Original')
            ->and($settings->fresh()->components()[0]['body'])->toBe('Original body')
            ->and(AuditEvent::query()->count())->toBe($before)
            ->and(AdminNotification::query()->count())->toBe(0);
    } finally {
        app()->instance(\App\Domain\Admin\AdminAuditService::class, $realAudit);
        app()->forgetInstance(\App\Domain\Content\CustomPageEditorialService::class);
    }
    Livewire::test(CustomPageWorkspace::class, ['section' => $section->id])
        ->mountAction('editComponent', ['componentIndex' => 0, 'componentType' => 'text'])
        ->fillForm(['title' => 'Changed', 'body' => 'Changed body'])->callMountedAction()
        ->assertHasNoFormErrors()->assertSee('Changed')->assertDispatched('admin-header-notification');
    expect($settings->fresh()->components()[0]['body'])->toBe('Changed body')
        ->and(AuditEvent::query()->count())->toBe($before + 1)
        ->and(AdminNotification::query()->count())->toBe(1);
});

require_once __DIR__.'/CustomPageFixtures.php';

it('edits and reorders List entries only through the owning component dialog', function (): void {
    [$section, $settings] = customPageWorkspaceRecord('CV list order', 'cv-list-order');
    $settings->update(['blocks' => [[
        'type' => 'list', 'published' => true, 'title' => 'CV',
        'items' => [
            ['published' => true, 'title' => 'First entry', 'date' => '2022'],
            ['published' => true, 'title' => 'Second entry', 'date' => '2023'],
            ['published' => true, 'title' => 'Third entry', 'date' => '2024'],
        ],
    ]]]);

    $page = Livewire::test(CustomPageWorkspace::class, ['section' => $section->id]);
    expect($page->get('components')[0]['children'])->toBe([]);
    $page->assertDontSee('Add list item')
        ->mountAction('editListComponent', ['componentIndex' => 0, 'componentType' => 'list'])
        ->assertMountedActionModalSee('List entries')
        ->fillForm([
            'type' => 'list',
            'publication_state' => 'published',
            'title' => 'CV',
            'items' => [
                ['published' => true, 'title' => 'Third entry', 'date' => '2024'],
                ['published' => true, 'title' => 'First entry', 'date' => '2022'],
                ['published' => true, 'title' => 'Second entry', 'date' => '2023'],
            ],
        ])
        ->callMountedAction()
        ->assertHasNoFormErrors();

    expect(array_column($settings->fresh()->components()[0]['items'], 'title'))->toBe([
        'Third entry', 'First entry', 'Second entry',
    ]);
});


it('persists List entry details and publication through the component Edit dialog', function (): void {
    [$section, $settings] = customPageWorkspaceRecord('List editor test', 'list-editor-test');
    $settings->update(['blocks' => [[
        'type' => 'list', 'published' => true, 'title' => 'CV', 'media_asset_id' => null,
        'items' => [[
            'published' => true, 'date' => '2024', 'title' => 'First entry',
            'meta' => 'Old context', 'location' => 'Berlin', 'url' => null,
            'body' => 'First details',
        ]],
    ]]]);

    Livewire::test(CustomPageWorkspace::class, ['section' => $section->id])
        ->mountAction('editListComponent', ['componentIndex' => 0, 'componentType' => 'list'])
        ->fillForm([
            'type' => 'list',
            'publication_state' => 'published',
            'title' => 'CV',
            'items' => [[
                'published' => false, 'date' => '2026', 'title' => 'First entry updated',
                'meta' => 'Museum', 'location' => 'Hamburg',
                'url' => 'https://example.com/entry', 'body' => 'Updated details',
            ]],
        ])
        ->callMountedAction()
        ->assertHasNoFormErrors();

    $item = $settings->fresh()->components()[0]['items'][0];
    expect($item['published'])->toBeFalse()
        ->and($item['date'])->toBe('2026')
        ->and($item['title'])->toBe('First entry updated')
        ->and($item['meta'])->toBe('Museum')
        ->and($item['location'])->toBe('Hamburg')
        ->and($item['url'])->toBe('https://example.com/entry')
        ->and($item['body'])->toBe('Updated details');

    $workspace = Livewire::test(CustomPageWorkspace::class, ['section' => $section->id]);
    expect($workspace->get('components')[0]['children'])->toBe([]);
});


it('keeps Change type reachable when filtering makes drag sorting unavailable', function (): void {
    [$section, $settings] = customPageWorkspaceRecord('Filtered type conversion', 'filtered-type-conversion');
    $settings->update(['blocks' => customPageWorkspaceBlocks(3)]);

    Livewire::test(CustomPageWorkspace::class, ['section' => $section->id])
        ->set('componentSearch', 'Component 2')
        ->mountAction('changeComponentType', [
            'componentIndex' => 1,
            'componentType' => 'list',
            'targetType' => 'divider',
        ])
        ->callMountedAction()
        ->assertHasNoErrors();

    $blocks = $settings->fresh()->components();
    expect($blocks[1]['type'])->toBe('divider')
        ->and($blocks[0]['type'])->toBe('list')
        ->and($blocks[2]['type'])->toBe('list');
});


it('persists consecutive component edits without closing the autosaving dialog', function (): void {
    [$section, $settings] = customPageWorkspaceRecord('Edit component', 'edit-component');
    $settings->update(['blocks' => [[
        'type' => 'text',
        'published' => true,
        'title' => 'Original heading',
        'body' => 'Original content',
    ]]]);

    $editor = Livewire::test(CustomPageWorkspace::class, ['section' => $section->id])
        ->mountAction('editComponent', ['componentIndex' => 0, 'componentType' => 'text'])
        ->fillForm([
            'publication_state' => 'unpublished',
            'title' => 'Revised heading',
            'body' => 'Revised content',
        ])
        ->callMountedAction()
        ->assertHasNoFormErrors()
        ->assertSet('mountedActions.0.name', 'editComponent')
        ->assertSee('Edit component');

    $first = $settings->fresh()->components()[0];
    expect($first['published'])->toBeFalse()
        ->and($first['title'])->toBe('Revised heading')
        ->and($first['body'])->toBe('Revised content');

    $editor->fillForm([
        'publication_state' => 'unpublished',
        'title' => 'Revised heading',
        'body' => 'Later rich text',
    ])
        ->callMountedAction()
        ->assertHasNoFormErrors()
        ->assertSet('mountedActions.0.name', 'editComponent')
        ->assertSee('Edit component');

    expect($settings->fresh()->components()[0]['body'])->toBe('Later rich text');
});
