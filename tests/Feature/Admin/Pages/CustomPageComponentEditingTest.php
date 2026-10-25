<?php

use App\Filament\Pages\CustomPageWorkspace;
use App\Models\CustomPageSetting;
use App\Models\PublicContentSetting;
use App\Models\MediaAsset;
use App\Models\SiteSection;
use App\Models\User;
use App\Models\AuditEvent;
use App\Models\AdminNotification;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;
use Filament\Facades\Filament;
use Livewire\Livewire;

require_once __DIR__.'/CustomPageFixtures.php';

it('preserves a nested component changed after its own editor was mounted', function (): void {
    [$section, $settings] = customPageWorkspaceRecord('Child concurrency', 'child-concurrency');
    $asset = customPageWorkspaceImage('child-concurrency');
    $settings->update(['blocks' => [[
        'type' => 'image', 'media_asset_id' => $asset->id, 'image_decorative' => true,
        'children' => [['type' => 'text', 'title' => 'Original', 'body' => 'Original body']],
    ]]]);
    $editor = Livewire::test(CustomPageWorkspace::class, ['section' => $section->id])
        ->mountAction('editImageChild', ['imageIndex' => 0, 'childIndex' => 0, 'componentType' => 'text']);
    $child = $settings->fresh()->components()[0]['children'][0];
    $child['body'] = 'Concurrent body';
    app(\App\Domain\Content\CustomPageEditorialService::class)->updateImageChild($settings, 0, 0, 'text', $child);
    app(\App\Domain\Admin\AdminActivityNotificationContext::class)->discard();
    $before = $settings->fresh()->components();
    $audits = AuditEvent::query()->count();

    $editor->fillForm(['body' => 'Stale body'])->callMountedAction()
        ->assertHasFormErrors(['type'])->assertNotDispatched('admin-header-notification');
    expect($settings->fresh()->components())->toBe($before)
        ->and(AuditEvent::query()->count())->toBe($audits)
        ->and(AdminNotification::query()->count())->toBe(0);
});

it('saves a nested component and returns it to page level when Parent is cleared', function (): void {
    [$section, $settings] = customPageWorkspaceRecord('Child parent', 'child-parent');
    $asset = customPageWorkspaceImage('child-parent');
    $settings->update(['blocks' => [[
        'type' => 'image', 'media_asset_id' => $asset->id, 'image_decorative' => true,
        'children' => [['type' => 'text', 'title' => 'Original', 'body' => 'Original body']],
    ]]]);
    $audits = AuditEvent::query()->count();
    $editor = Livewire::test(CustomPageWorkspace::class, ['section' => $section->id])
        ->mountAction('editImageChild', ['imageIndex' => 0, 'childIndex' => 0, 'componentType' => 'text']);
    $editor->fillForm(['body' => 'Edited body', 'parent_image_index' => null])->callMountedAction()
        ->assertHasNoFormErrors()->assertDispatched('admin-header-notification');
    $blocks = $settings->fresh()->components();
    expect($blocks)->toHaveCount(2)
        ->and($blocks[0]['children'] ?? [])->toBeEmpty()
        ->and($blocks[1]['body'])->toBe('Edited body')
        ->and(AuditEvent::query()->count())->toBe($audits + 2)
        ->and(AdminNotification::query()->count())->toBe(1)
        ->and(collect($editor->effects['dispatches'] ?? [])->where('name', 'admin-header-notification')->count())->toBe(1);
});

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
            ['published' => true, 'title' => 'First entry', 'context' => '2022'],
            ['published' => true, 'title' => 'Second entry', 'context' => '2023'],
            ['published' => true, 'title' => 'Third entry', 'context' => '2024'],
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
                ['published' => true, 'title' => 'Third entry', 'context' => '2024'],
                ['published' => true, 'title' => 'First entry', 'context' => '2022'],
                ['published' => true, 'title' => 'Second entry', 'context' => '2023'],
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
            'published' => true, 'context' => '2024', 'title' => 'First entry',
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
                'published' => false, 'context' => 'Museum · 2026', 'title' => 'First entry updated',
                'body' => 'Updated details',
            ]],
        ])
        ->callMountedAction()
        ->assertHasNoFormErrors();

    $item = $settings->fresh()->components()[0]['items'][0];
    expect($item['published'])->toBeFalse()
        ->and($item['context'])->toBe('Museum · 2026')
        ->and($item['title'])->toBe('First entry updated')
        ->and($item['body'])->toBe('Updated details')
        ->and($item)->not->toHaveKeys(['date', 'meta', 'location', 'url']);

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


it('opens and saves the third Rich Text component from its absolute row target', function (): void {
    [$section, $settings] = customPageWorkspaceRecord('Targeted editor', 'targeted-editor');
    $settings->update(['blocks' => [
        ['type' => 'text', 'published' => true, 'title' => null, 'body' => 'First text'],
        ['type' => 'divider', 'published' => true, 'variant' => 'dotted'],
        ['type' => 'text', 'published' => true, 'title' => null, 'body' => 'Third text'],
    ]]);

    $editor = Livewire::test(CustomPageWorkspace::class, ['section' => $section->getKey()])
        ->mountAction('editComponent', ['componentIndex' => 2, 'componentType' => 'text'])
        ->assertMountedActionModalSee('Edit component')
        ->fillForm([
            'type' => 'text',
            'publication_state' => 'published',
            'title' => 'Third heading',
            'body' => 'Updated third text',
        ])
        ->callMountedAction()
        ->assertHasNoFormErrors()
        ->assertSet('mountedActions.0.name', 'editComponent')
        ->assertSee('Edit component');

    $components = $settings->fresh()->components();
    expect($components)->toHaveCount(3)
        ->and($components[0]['body'])->toBe('First text')
        ->and($components[1]['type'])->toBe('divider')
        ->and($components[1]['variant'])->toBe('dotted')
        ->and($components[2]['title'])->toBe('Third heading')
        ->and($components[2]['body'])->toBe('Updated third text');
});


it('edits the Contact heading and all Contact settings in the owning component without child rows', function (): void {
    [$section, $settings] = customPageWorkspaceRecord('Contact editing', 'contact-editing');
    $settings->update(['blocks' => [[
        'type' => 'contact',
        'published' => true,
        'children' => [
            ['type' => 'public_email', 'published' => true],
            ['type' => 'social_links', 'published' => true, 'social_platforms' => []],
            ['type' => 'contact_form', 'published' => true, 'form_state' => 'enabled'],
        ],
    ]]]);

    $editor = Livewire::test(CustomPageWorkspace::class, ['section' => $section->getKey()])
        ->assertDontSee('Contact item')
        ->mountAction('editComponent', ['componentIndex' => 0, 'componentType' => 'contact'])
        ->fillForm([
            'type' => 'contact',
            'publication_state' => 'published',
            'title' => 'Get in touch',
            'contact_email_enabled' => false,
            'contact_social_enabled' => true,
            'contact_social_platforms' => [],
            'contact_form_enabled' => true,
            'contact_form_state' => 'under_construction',
            'contact_status_text' => 'Coming soon',
        ])
        ->call('callMountedAction')
        ->assertHasNoFormErrors();

    $contact = $settings->fresh()->components()[0];
    $children = collect($settings->fresh()->contactChildren($contact))->keyBy('type');
    expect($contact['title'])->toBe('Get in touch')
        ->and($children['public_email']['published'])->toBeFalse()
        ->and($children['social_links']['published'])->toBeTrue()
        ->and($children['contact_form']['published'])->toBeTrue()
        ->and($children['contact_form']['form_state'])->toBe('under_construction')
        ->and($children['contact_form']['status_text'])->toBe('Coming soon');

    $preview = Livewire::test(CustomPageWorkspace::class, ['section' => $section->getKey()]);
    expect($preview->get('components')[0]['content'])->toMatchArray([
        'primary' => 'Get in touch',
        'secondary' => 'Social Media Links · Contact Form',
        'meta' => '',
    ]);
    $preview->assertDontSeeHtml('custom-page-child-row');
});

it('retains a selected Contact platform only while its General profile still exists', function (): void {
    [$section, $settings] = customPageWorkspaceRecord('Hidden social profile', 'hidden-social-profile');
    \App\Models\PublicContentSetting::general()->update(['social_links' => [
        ['platform' => 'instagram', 'url' => 'https://example.invalid/instagram'],
        ['platform' => 'facebook', 'url' => 'https://example.invalid/facebook', 'visible' => false],
    ]]);
    $settings->update(['blocks' => [[
        'type' => 'contact', 'published' => true, 'title' => 'Original',
        'children' => [
            ['type' => 'public_email', 'published' => true],
            ['type' => 'social_links', 'published' => true, 'social_platforms' => ['instagram', 'facebook']],
            ['type' => 'contact_form', 'published' => true, 'form_state' => 'enabled'],
        ],
    ]]]);

    $editor = Livewire::test(CustomPageWorkspace::class, ['section' => $section->id])
        ->assertSet('availableSocialPlatforms', ['instagram' => 'Instagram'])
        ->mountAction('editComponent', ['componentIndex' => 0, 'componentType' => 'contact']);

    $editor->set('mountedActions.0.data.title', 'Updated contact')
        ->call('callMountedAction')
        ->assertHasNoFormErrors()
        ->assertDispatched('admin-header-notification')
        ->assertSet('mountedActions.0.name', 'editComponent')
        ->assertSee('Edit component');

    $contact = $settings->fresh()->components()[0];
    $social = collect($settings->fresh()->contactChildren($contact))->firstWhere('type', 'social_links');
    expect($contact['title'])->toBe('Updated contact')
        ->and($social['social_platforms'])->toBe(['instagram', 'facebook'])
        ->and(AdminNotification::query()->count())->toBe(1);

    $editor->set('mountedActions.0.data.contact_social_selected.instagram', false)
        ->call('callMountedAction')
        ->assertHasNoFormErrors();

    $contact = $settings->fresh()->components()[0];
    $social = collect($settings->fresh()->contactChildren($contact))->firstWhere('type', 'social_links');
    expect($contact['title'])->toBe('Updated contact')
        ->and($social['social_platforms'])->toBe(['facebook']);
});

it('preserves legacy List metadata as Context and links in Details without duplicate conversions', function (): void {
    [$section, $settings] = customPageWorkspaceRecord('Historical list', 'historical-list');
    $settings->update(['blocks' => [[
        'type' => 'list', 'title' => 'CV',
        'items' => [[
            'title' => 'Exhibition', 'date' => '2024', 'meta' => 'Museum', 'location' => 'Hamburg',
            'url' => 'https://example.com/details', 'body' => 'Original details',
        ]],
    ]]]);
    $item = $settings->fresh()->components()[0]['items'][0];
    expect($item['context'])->toBe('2024 · Museum · Hamburg')
        ->and($item['body'])->toContain('Original details')
        ->toContain('[More information](https://example.com/details)')
        ->and($item)->not->toHaveKeys(['date', 'meta', 'location', 'url']);

    $settings->update(['blocks' => $settings->fresh()->components()]);
    $again = $settings->fresh()->components()[0]['items'][0];
    expect(substr_count($again['body'], '[More information]'))->toBe(1);
});


it('retains disabled Contact settings for later re-enabling', function (): void {
    \App\Models\PublicContentSetting::general()->update(['social_links' => [
        ['platform' => 'instagram', 'url' => 'https://example.test/artist'],
    ]]);
    [$section, $settings] = customPageWorkspaceRecord('Hidden Contact settings', 'hidden-contact-settings');
    $settings->update(['blocks' => [[
        'type' => 'contact',
        'title' => 'Reach us',
        'children' => [
            ['type' => 'public_email', 'published' => true],
            ['type' => 'social_links', 'published' => true, 'social_platforms' => ['instagram']],
            ['type' => 'contact_form', 'published' => true, 'form_state' => 'under_construction', 'status_text' => 'Soon'],
        ],
    ]]]);
    // A hidden form field must not reset previously authored child settings.
    Livewire::test(CustomPageWorkspace::class, ['section' => $section->id])
        ->mountAction('editComponent', ['componentIndex' => 0, 'componentType' => 'contact'])
        ->fillForm([
            'type' => 'contact',
            'publication_state' => 'published',
            'title' => 'Reach us',
            'contact_email_enabled' => true,
            'contact_social_enabled' => false,
            'contact_form_enabled' => false,
        ])
        ->call('callMountedAction')
        ->assertHasNoFormErrors();

    $children = collect($settings->fresh()->components()[0]['children'])->keyBy('type');
    expect($children['social_links']['published'])->toBeFalse()
        ->and($children['social_links']['social_platforms'])->toBe(['instagram'])
        ->and($children['contact_form']['published'])->toBeFalse()
        ->and($children['contact_form']['form_state'])->toBe('under_construction')
        ->and($children['contact_form']['status_text'])->toBe('Soon');
});


it('creates Contact with all three options enabled by default and an editable heading', function (): void {
    [$section, $settings] = customPageWorkspaceRecord('Default Contact', 'default-contact');

    Livewire::test(CustomPageWorkspace::class, ['section' => $section->id])
        ->mountAction('addComponent')
        ->fillForm([
            'type' => 'contact',
            'publication_state' => 'published',
            'title' => 'Contact Lars',
            'contact_email_enabled' => true,
            'contact_social_enabled' => true,
            'contact_social_platforms' => [],
            'contact_form_enabled' => true,
            'contact_form_state' => 'enabled',
        ])
        ->callMountedAction()
        ->assertHasNoFormErrors();

    $contact = $settings->fresh()->components()[0];
    expect($contact['title'])->toBe('Contact Lars')
        ->and(array_column($contact['children'], 'type'))->toBe(['public_email', 'social_links', 'contact_form'])
        ->and(array_column($contact['children'], 'published'))->toBe([true, true, true]);

    $workspace = Livewire::test(CustomPageWorkspace::class, ['section' => $section->id]);
    expect($workspace->get('components')[0]['content'])->toMatchArray([
        'primary' => 'Contact Lars',
        'secondary' => 'Public Email · Social Media Links · Contact Form',
        'meta' => '',
    ]);
});


it('edits image size in the owning component and keeps legacy 100% implicit', function (): void {
    [$section, $settings] = customPageWorkspaceRecord('Image sizing', 'image-sizing');
    $image = [
        'type' => 'image',
        'published' => false,
        'media_asset_id' => null,
        'image_decorative' => false,
    ];
    $settings->update(['blocks' => [$image]]);
    expect($settings->fresh()->components()[0])->not->toHaveKey('image_scale_percent');

    Livewire::test(CustomPageWorkspace::class, ['section' => $section->id])
        ->mountAction('editComponent', ['componentIndex' => 0, 'componentType' => 'image'])
        ->assertMountedActionModalSee('Image size')
        ->assertActionDataSet(['image_scale_percent' => CustomPageSetting::DEFAULT_IMAGE_SCALE_PERCENT]);

    $service = app(\App\Domain\Content\CustomPageEditorialService::class);
    expect($service->updateBlock($settings, 0, 'image', $image))->toBeFalse();

    $scaled = [...$image, 'image_scale_percent' => 150];
    expect($service->updateBlock($settings, 0, 'image', $scaled))->toBeTrue();
    expect($settings->fresh()->components()[0]['image_scale_percent'])->toBe(150);
    expect(CustomPageSetting::imageScalePercent($settings->fresh()->components()[0]))->toBe(150);

    expect($service->updateBlock($settings, 0, 'image', $scaled))->toBeFalse();
    expect($service->updateBlock($settings, 0, 'image', $image))->toBeTrue();
    expect($settings->fresh()->components()[0])->not->toHaveKey('image_scale_percent');
    expect(CustomPageSetting::imageScalePercent($settings->fresh()->components()[0]))->toBe(100);
});


it('rejects out-of-range and noninteger image sizes without changing the saved component', function (): void {
    [$section, $settings] = customPageWorkspaceRecord('Image sizing bounds', 'image-sizing-bounds');
    $image = ['type' => 'image', 'published' => false, 'media_asset_id' => null, 'image_decorative' => false];
    $settings->update(['blocks' => [$image]]);
    $before = $settings->fresh()->components();

    foreach ([24, 201, '80.5', 'invalid'] as $invalid) {
        expect(fn () => $settings->update(['blocks' => [[...$image, 'image_scale_percent' => $invalid]]]))
            ->toThrow(ValidationException::class);
        expect($settings->fresh()->components())->toBe($before);
    }

    $settings->update(['blocks' => [[...$image, 'image_scale_percent' => '175']]]);
    expect($settings->fresh()->components()[0]['image_scale_percent'])->toBe(175);

});


it('renders a scaled image and derives move permissions after filtering and pagination', function (): void {
    [$section, $settings] = customPageWorkspaceRecord('Image projection', 'image-projection');
    $settings->update(['blocks' => [
        [
            'type' => 'image',
            'published' => false,
            'media_asset_id' => null,
            'image_decorative' => false,
            'image_scale_percent' => 150,
        ],
        ['type' => 'text', 'published' => false, 'title' => 'Second component', 'body' => 'Text'],
        ['type' => 'divider', 'published' => false, 'variant' => 'thin'],
    ]]);

    // Exercise the real GET that previously threw before the editor could open.
    $this->get('/admin/pages/custom/'.$section->getKey())->assertOk();

    $page = Livewire::test(CustomPageWorkspace::class, ['section' => $section->getKey()])
        ->assertSet('total', 3);

    $rows = $page->get('components');
    expect($rows)->toHaveCount(3)
        ->and($rows[0]['can_move_up'])->toBeFalse()
        ->and($rows[0]['can_move_down'])->toBeTrue()
        ->and($rows[1]['can_move_up'])->toBeTrue()
        ->and($rows[1]['can_move_down'])->toBeTrue()
        ->and($rows[2]['can_move_up'])->toBeTrue()
        ->and($rows[2]['can_move_down'])->toBeFalse();

    $page->set('componentType', 'image')->assertSet('total', 1);
    $filtered = $page->get('components');
    expect($filtered[0]['can_move_up'])->toBeFalse()
        ->and($filtered[0]['can_move_down'])->toBeFalse();

    $page->set('componentType', 'any')
        ->set('componentSearch', 'Second component')
        ->assertSet('total', 1);
    $searched = $page->get('components');
    expect($searched[0]['can_move_up'])->toBeFalse()
        ->and($searched[0]['can_move_down'])->toBeFalse();
});


it('autosaves an image selection without closing the editor and notifies once per real change', function (): void {
    [$section, $settings] = customPageWorkspaceRecord('Image replacement', 'image-replacement');

    $createImage = static fn (string $filename): \App\Models\MediaAsset => \App\Models\MediaAsset::query()->create([
        'storage_key' => 'originals/'.$filename,
        'original_filename' => $filename,
        'mime_type' => 'image/jpeg',
        'byte_size' => 4,
        'sha256' => hash('sha256', $filename),
        'state' => 'available',
        'alt_text' => 'Artist image',
    ]);
    $first = $createImage('first-image.jpg');
    $replacement = $createImage('replacement-image.jpg');
    $settings->update(['blocks' => [[
        'type' => 'image',
        'published' => true,
        'media_asset_id' => $first->getKey(),
        'image_decorative' => false,
        'image_scale_percent' => 50,
    ]]]);

    $notificationsBefore = \App\Models\AdminNotification::query()->count();
    $auditsBefore = \App\Models\AuditEvent::query()
        ->where('action', 'site_section.updated')
        ->where('entity_type', 'site_section')
        ->where('entity_id', $section->getKey())
        ->count();

    $editor = Livewire::test(CustomPageWorkspace::class, ['section' => $section->getKey()])
        ->mountAction('editComponent', ['componentIndex' => 0, 'componentType' => 'image'])
        ->assertSet('mountedActions.0.data.media_asset_id', $first->getKey())
        ->assertSet('mountedActions.0.data.image_scale_percent', 50)
        ->set('mountedActions.0.data.media_asset_id', (string) $replacement->getKey())
        ->assertHasNoFormErrors()
        ->assertSet('mountedActions.0.name', 'editComponent')
        ->assertSee('replacement-image.jpg')
        ->assertDispatched('admin-header-notification');

    $updated = $settings->fresh()->components()[0];
    expect($updated['media_asset_id'])->toBe((int) $replacement->getKey())
        ->and($updated['image_scale_percent'])->toBe(50)
        ->and($updated['image_decorative'])->toBeFalse()
        ->and(\App\Models\AdminNotification::query()->count())->toBe($notificationsBefore + 1)
        ->and(\App\Models\AuditEvent::query()
            ->where('action', 'site_section.updated')
            ->where('entity_type', 'site_section')
            ->where('entity_id', $section->getKey())
            ->count())->toBe($auditsBefore + 1);

    // A second form-level autosave must not duplicate the persisted mutation.
    $editor->call('callMountedAction')->assertHasNoFormErrors();
    expect(\App\Models\AdminNotification::query()->count())->toBe($notificationsBefore + 1);

    Livewire::test(CustomPageWorkspace::class, ['section' => $section->getKey()])
        ->mountAction('editComponent', ['componentIndex' => 0, 'componentType' => 'image'])
        ->assertSet('mountedActions.0.data.media_asset_id', $replacement->getKey())
        ->assertSet('mountedActions.0.data.image_scale_percent', 50);
});

it('offers a visible Parent selector in CV List Edit and persists the nesting', function (): void {
    [$section, $settings] = customPageWorkspaceRecord('Parent chooser CV', 'parent-chooser-cv');
    $asset = MediaAsset::query()->create([
        'storage_key' => 'originals/parent-chooser-cv.jpg',
        'original_filename' => 'parent-chooser-cv.jpg',
        'mime_type' => 'image/jpeg',
        'byte_size' => 4,
        'sha256' => hash('sha256', 'parent-chooser-cv'),
        'state' => 'available',
        'alt_text' => 'CV portrait',
    ]);
    $settings->update(['blocks' => [
        ['type' => 'image', 'image_decorative' => true, 'media_asset_id' => $asset->getKey()],
        ['type' => 'list', 'title' => 'Curriculum Vitae', 'items' => [
            ['title' => 'Born in Hamburg', 'context' => '1989'],
            ['title' => 'Lives and works in Hamburg', 'context' => null],
        ]],
    ]]);

    Livewire::test(CustomPageWorkspace::class, ['section' => $section->getKey()])
        ->mountAction('editListComponent', ['componentIndex' => 1, 'componentType' => 'list'])
        ->assertMountedActionModalSee('Parent')
        ->assertSet('mountedActions.0.data.type', 'list')
        ->set('mountedActions.0.data.parent_image_index', '0')
        ->call('callMountedAction')
        ->assertHasNoFormErrors()
        ->assertSet('mountedActions.0.name', 'editImageChild')
        ->assertSet('mountedActions.0.data.type', 'list');

    $blocks = $settings->fresh()->components();
    expect($blocks)->toHaveCount(1)
        ->and($blocks[0]['children'][0]['type'])->toBe('list')
        ->and($blocks[0]['children'][0]['items'][1]['title'])->toBe('Lives and works in Hamburg')
        ->and($blocks[0]['children'][0]['items'][1]['context'])->toBeNull();
});

it('exposes the same Parent field above the content of a Text Edit', function (): void {
    [$section, $settings] = customPageWorkspaceRecord('Text parent chooser', 'text-parent-chooser');
    $settings->update(['blocks' => [
        ['type' => 'text', 'title' => 'Artist statement', 'body' => 'Words'],
    ]]);
    Livewire::test(CustomPageWorkspace::class, ['section' => $section->getKey()])
        ->mountAction('editComponent', ['componentIndex' => 0, 'componentType' => 'text'])
        ->assertMountedActionModalSee('Parent')
        ->assertHasNoFormErrors();
});


it('protects stale image autosave and nesting edits through the mounted validation owner', function (string $operation): void {
    [$section, $settings] = customPageWorkspaceRecord('Protected image', 'protected-image');
    $asset = customPageWorkspaceImage('protected-image');
    $replacement = customPageWorkspaceImage('replacement');
    $settings->update(['blocks' => [
        ['type' => 'image', 'media_asset_id' => $asset->id, 'image_decorative' => true],
        ['type' => 'text', 'title' => 'Original', 'body' => 'Original body'],
    ]]);
    $index = $operation === 'parent' ? 1 : 0;
    $type = $index === 1 ? 'text' : 'image';
    $editor = Livewire::test(CustomPageWorkspace::class, ['section' => $section->id])
        ->mountAction('editComponent', ['componentIndex' => $index, 'componentType' => $type]);
    $block = $settings->fresh()->components()[$index];
    $block[$type === 'text' ? 'body' : 'image_scale_percent'] = $type === 'text' ? 'Concurrent content' : 75;
    app(\App\Domain\Content\CustomPageEditorialService::class)->updateBlock($settings, $index, $type, $block);
    app(\App\Domain\Admin\AdminActivityNotificationContext::class)->discard();
    $before = $settings->fresh()->components();
    $audits = AuditEvent::query()->count();
    if ($operation === 'autosave') {
        $editor->set('mountedActions.0.data.media_asset_id', (string) $replacement->id);
    } else {
        $editor->fillForm($operation === 'parent' ? ['parent_image_index' => '0'] : ['image_scale_percent' => 50])
            ->callMountedAction();
    }
    $editor->assertHasFormErrors(['type'])->assertNotDispatched('admin-header-notification');
    expect($settings->fresh()->components())->toBe($before)
        ->and(AuditEvent::query()->count())->toBe($audits)
        ->and(AdminNotification::query()->count())->toBe(0);
})->with(['autosave', 'save', 'parent']);

it('rolls back edited content and nesting together if the second audit fails and retries once', function (): void {
    [$section, $settings] = customPageWorkspaceRecord('Atomic nesting', 'atomic-nesting');
    $asset = customPageWorkspaceImage('atomic-nesting');
    $settings->update(['blocks' => [
        ['type' => 'image', 'media_asset_id' => $asset->id, 'image_decorative' => true],
        ['type' => 'text', 'title' => 'Original', 'body' => 'Original body'],
    ]]);
    $before = $settings->fresh()->components();
    $audits = AuditEvent::query()->count();
    $realAudit = app(\App\Domain\Admin\AdminAuditService::class);
    $audit = Mockery::mock($realAudit);
    $recordings = 0;
    $audit->shouldReceive('record')->twice()->andReturnUsing(function (...$parameters) use ($realAudit, &$recordings): AuditEvent {
        if (++$recordings === 2) {
            throw new RuntimeException('Nesting audit failed');
        }
        return $realAudit->record(...$parameters);
    });
    app()->instance(\App\Domain\Admin\AdminAuditService::class, $audit);
    try {
        $editor = Livewire::test(CustomPageWorkspace::class, ['section' => $section->id])
            ->mountAction('editComponent', ['componentIndex' => 1, 'componentType' => 'text'])
            ->fillForm(['body' => 'Edited content', 'parent_image_index' => '0']);
        expect(fn () => $editor->callMountedAction())->toThrow(RuntimeException::class, 'Nesting audit failed');
        expect($settings->fresh()->components())->toBe($before)
            ->and(AuditEvent::query()->count())->toBe($audits)
            ->and(AdminNotification::query()->count())->toBe(0);
    } finally {
        app()->instance(\App\Domain\Admin\AdminAuditService::class, $realAudit);
    }
    $editor = Livewire::test(CustomPageWorkspace::class, ['section' => $section->id])
        ->mountAction('editComponent', ['componentIndex' => 1, 'componentType' => 'text'])
        ->fillForm(['body' => 'Edited content', 'parent_image_index' => '0'])->callMountedAction()
        ->assertHasNoFormErrors()->assertDispatched('admin-header-notification');
    expect($settings->fresh()->components())->toHaveCount(1)
        ->and($settings->fresh()->components()[0]['children'][0]['body'])->toBe('Edited content')
        ->and(AuditEvent::query()->count())->toBe($audits + 2)
        ->and(AdminNotification::query()->count())->toBe(1)
        ->and(collect($editor->effects['dispatches'] ?? [])->where('name', 'admin-header-notification')->count())->toBe(1);
    $editor->callMountedAction()->assertHasNoFormErrors()->assertNotDispatched('admin-header-notification');
    expect(AuditEvent::query()->count())->toBe($audits + 2)
        ->and(AdminNotification::query()->count())->toBe(1);
});


it('rejects an invalid image replacement at the mounted field without mutation or feedback', function (): void {
    [$section, $settings] = customPageWorkspaceRecord('Invalid replacement', 'invalid-replacement');
    $asset = customPageWorkspaceImage('invalid-replacement');
    $settings->update(['blocks' => [['type' => 'image', 'media_asset_id' => $asset->id, 'image_decorative' => true]]]);
    $before = $settings->fresh()->components();
    $audits = AuditEvent::query()->count();
    Livewire::test(CustomPageWorkspace::class, ['section' => $section->id])
        ->mountAction('editComponent', ['componentIndex' => 0, 'componentType' => 'image'])
        ->set('mountedActions.0.data.media_asset_id', '0')
        ->assertHasFormErrors(['media_asset_id'])->assertNotDispatched('admin-header-notification');
    expect($settings->fresh()->components())->toBe($before)
        ->and(AuditEvent::query()->count())->toBe($audits)
        ->and(AdminNotification::query()->count())->toBe(0);
});

it('reorders and publishes Custom Page components in the editor', function (): void {
    $this->actingAs(User::factory()->admin()->create(), 'web');
    Filament::setCurrentPanel('admin');
    Filament::bootCurrentPanel();
    [$section, $settings] = customPageWorkspaceRecord('Dialog reorder', 'dialog-reorder');
    $settings->update(['blocks' => [
        ['type' => 'text', 'published' => true, 'title' => 'First', 'body' => 'One'],
        ['type' => 'text', 'published' => true, 'title' => 'Second', 'body' => 'Two'],
    ]]);

    $dialog = Livewire::test(CustomPageWorkspace::class, ['section' => $section->id])
        ->mountAction('editComponent', ['componentIndex' => 1, 'componentType' => 'text'])
        ->assertSet('mountedActions.0.name', 'editComponent');
    $dialog->mountAction('dialogMoveUp')
        ->assertSet('mountedActions.0.name', 'editComponent')
        ->assertSet('mountedActions.0.arguments.componentIndex', 0);
    expect(array_column($settings->fresh()->components(), 'title'))->toBe(['Second', 'First']);
    expect($dialog->instance()->getMountedAction()->getModalAction('dialogMoveUp')->isDisabled())->toBeTrue();

    $dialog->mountAction('dialogTogglePublication')
        ->assertSet('mountedActions.0.name', 'editComponent')
        ->assertSet('mountedActions.0.arguments.componentIndex', 0);
    expect($settings->fresh()->components()[0]['published'])->toBeFalse();

    $dialog->mountAction('dialogMoveDown')
        ->assertSet('mountedActions.0.name', 'editComponent')
        ->assertSet('mountedActions.0.arguments.componentIndex', 1);
    expect(array_column($settings->fresh()->components(), 'title'))->toBe(['First', 'Second']);
    expect($dialog->instance()->getMountedAction()->getModalAction('dialogMoveDown')->isDisabled())->toBeTrue();
});

it('persists image alignment and video playback preferences in the shared component editor', function (): void {
    [$section, $settings] = customPageWorkspaceRecord('Image presentation', 'image-presentation');
    $settings->update(['blocks' => [[
        'type' => 'image', 'published' => false, 'media_asset_id' => null,
        'image_decorative' => false, 'image_alignment' => 'center',
        'video_controls_enabled' => false,
    ]]]);

    $image = $settings->fresh()->components()[0];
    expect(CustomPageSetting::imageAlignment($image))->toBe('center')
        ->and(CustomPageSetting::videoControls($image))->toBeFalse();

    Livewire::test(CustomPageWorkspace::class, ['section' => $section->getKey()])
        ->mountAction('editComponent', ['componentIndex' => 0, 'componentType' => 'image'])
        ->assertActionDataSet([
            'image_alignment' => 'center',
            'video_controls_enabled' => false,
        ]);

    $settings->update(['blocks' => [[
        'type' => 'image', 'published' => false, 'media_asset_id' => null,
        'image_decorative' => false,
    ]]]);
    $defaultImage = $settings->fresh()->components()[0];
    expect($defaultImage)->not->toHaveKey('video_controls_enabled')
        ->and(CustomPageSetting::videoControls($defaultImage))->toBeTrue();
});
