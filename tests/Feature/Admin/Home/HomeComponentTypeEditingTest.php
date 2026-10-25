<?php

use App\Domain\Content\HomePresentationEditorialService;
use App\Domain\Content\HomeTemplate;
use App\Models\HomePresentationSetting;
use App\Models\SiteSection;
use App\Models\User;
use Illuminate\Validation\ValidationException;

function homeComponentTypeSettings(HomeTemplate $template, array $components): HomePresentationSetting
{
    $home = SiteSection::query()->where('type', SiteSection::TYPE_HOME)->firstOrFail();
    $settings = HomePresentationSetting::query()->where('site_section_id', $home->getKey())->firstOrFail();
    $configuration = HomePresentationEditorialService::defaults();
    $configuration[$template->value]['components'] = $components;
    $settings->setAttribute('template', $template->value);
    $settings->setAttribute('configuration', $configuration);
    $settings->save();

    return $settings;
}

it('converts Home headings to Rich Text without discarding their text', function (HomeTemplate $template): void {
    $this->actingAs(User::factory()->admin()->create(), 'web');
    $settings = homeComponentTypeSettings($template, [
        ['type' => 'text', 'title' => 'A heading to keep', 'body' => null],
    ]);
    $service = app(HomePresentationEditorialService::class);

    expect($service->conversionLosesContent($settings->configuration[$template->value]['components'][0], 'rich_text'))->toBeFalse()
        ->and($service->convertComponent($settings, $template, 0, 'text', 'heading', 'rich_text'))->toBeTrue();

    $component = $service->configuration($settings->fresh())[$template->value]['components'][0];
    expect($component)->toHaveCount(4)
        ->and($component['published'])->toBeTrue()
        ->and($component['type'])->toBe('text')
        ->and($component['title'])->toBeNull()
        ->and($component['body'])->toBe('A heading to keep');
})->with([HomeTemplate::Custom, HomeTemplate::UnderConstruction]);

it('confirms content loss for Rich Text conversions and rejects stale component targets', function (): void {
    $this->actingAs(User::factory()->admin()->create(), 'web');
    $settings = homeComponentTypeSettings(HomeTemplate::Custom, [
        ['type' => 'text', 'title' => null, 'body' => 'Existing **rich text**'],
    ]);
    $service = app(HomePresentationEditorialService::class);
    $original = $service->configuration($settings)['custom']['components'][0];

    expect($service->conversionLosesContent($original, 'heading'))->toBeTrue()
        ->and($service->conversionLosesContent($original, 'divider'))->toBeTrue()
        ->and($service->convertComponent($settings, HomeTemplate::Custom, 0, 'text', 'rich_text', 'heading'))->toBeTrue();

    $converted = $service->configuration($settings->fresh())['custom']['components'][0];
    expect($converted)->toHaveCount(4)
        ->and($converted['published'])->toBeTrue()
        ->and($converted['type'])->toBe('text')
        ->and($converted['title'])->toBeNull()
        ->and($converted['body'])->toBeNull();

    // Empty text has no persisted Heading/Rich Text discriminator. Change the
    // actual persisted type before submitting a genuinely stale target.
    $service->convertComponent($settings, HomeTemplate::Custom, 0, 'text', 'rich_text', 'divider');
    expect(fn () => $service->convertComponent($settings, HomeTemplate::Custom, 0, 'text', 'rich_text', 'heading'))
        ->toThrow(ValidationException::class);
    expect($service->configuration($settings->fresh())['custom']['components'][0]['type'])->toBe('divider');
});

it('saves a supported Home Divider choice and rejects invalid styles', function (): void {
    $this->actingAs(User::factory()->admin()->create(), 'web');
    $settings = homeComponentTypeSettings(HomeTemplate::Custom, [['type' => 'divider']]);
    $service = app(HomePresentationEditorialService::class);

    expect($service->updateComponent(
        $settings, HomeTemplate::Custom, 0, 'divider',
        ['type' => 'divider', 'variant' => 'dotted'],
    ))->toBeTrue();
    expect($service->configuration($settings->fresh())['custom']['components'][0]['variant'])->toBe('dotted');

    expect(fn () => $service->updateComponent(
        $settings, HomeTemplate::Custom, 0, 'divider',
        ['type' => 'divider', 'variant' => 'diagonal'],
    ))->toThrow(ValidationException::class);
});

it('preserves publication on type conversion and supports List and Contact child editing', function (): void {
    $this->actingAs(User::factory()->admin()->create(), 'web');
    $settings = homeComponentTypeSettings(HomeTemplate::Custom, [
        ['type' => 'divider', 'variant' => 'dotted', 'published' => false],
    ]);
    $service = app(HomePresentationEditorialService::class);

    expect($service->conversionLosesContent(['type' => 'divider', 'variant' => 'dotted'], 'list'))->toBeTrue()
        ->and($service->convertComponent($settings, HomeTemplate::Custom, 0, 'divider', 'divider', 'list'))->toBeTrue();

    $list = $service->configuration($settings->fresh())['custom']['components'][0];
    expect($list['type'])->toBe('list')
        ->and($list['published'])->toBeFalse()
        ->and($list['items'])->toBe([]);

    expect($service->mutateChild($settings, HomeTemplate::Custom, 0, 'list', 'add', null, [
        'title' => 'Exhibition', 'date' => '2026', 'published' => true, 'body' => null,
    ]))->toBeTrue();
    expect($service->mutateChild($settings, HomeTemplate::Custom, 0, 'list', 'publish', 0, [
        'published' => false,
    ]))->toBeTrue();
    expect($service->configuration($settings->fresh())['custom']['components'][0]['items'][0]['published'])->toBeFalse();

    $service->setComponentPublished($settings, HomeTemplate::Custom, 0, 'list', true);
    expect($service->configuration($settings->fresh())['custom']['components'][0]['published'])->toBeTrue();

    expect(fn () => $service->mutateChild($settings, HomeTemplate::Custom, 0, 'list', 'update', 2, [
        'title' => 'Stale target',
    ]))->toThrow(ValidationException::class);
});

it('rejects duplicate Contact children and duplicate Legal Disclaimer components', function (): void {
    $this->actingAs(User::factory()->admin()->create(), 'web');
    $settings = homeComponentTypeSettings(HomeTemplate::Custom, [
        ['type' => 'contact', 'children' => [['type' => 'public_email', 'published' => true]]],
    ]);
    $service = app(HomePresentationEditorialService::class);

    expect(fn () => $service->mutateChild($settings, HomeTemplate::Custom, 0, 'contact', 'add', null,
        ['type' => 'public_email', 'published' => true]))->toThrow(ValidationException::class);

    expect($service->mutateChild($settings, HomeTemplate::Custom, 0, 'contact', 'add', null,
        ['type' => 'contact_form', 'published' => true, 'form_state' => 'enabled', 'status_text' => null]))->toBeTrue();

    $service->addComponent($settings, HomeTemplate::Custom, ['type' => 'legal_disclaimer']);
    expect(fn () => $service->addComponent($settings, HomeTemplate::Custom, ['type' => 'legal_disclaimer']))
        ->toThrow(ValidationException::class);
});

it('updates mixed Home parent and child selection atomically', function (): void {
    $this->actingAs(User::factory()->admin()->create(), 'web');
    $settings = homeComponentTypeSettings(HomeTemplate::Custom, [
        ['type' => 'list', 'published' => true, 'title' => 'CV', 'items' => [
            ['title' => 'First', 'published' => true],
            ['title' => 'Second', 'published' => true],
        ]],
        ['type' => 'divider', 'published' => true, 'variant' => 'dotted'],
    ]);
    $service = app(HomePresentationEditorialService::class);

    $parents = [['index' => 1, 'type' => 'divider']];
    $children = [['index' => 0, 'type' => 'list', 'child' => 1]];
    expect($service->changeSelection($settings, HomeTemplate::Custom, $parents, $children, 'unpublish'))->toBeTrue();

    $rows = $service->configuration($settings->fresh())['custom']['components'];
    expect($rows[0]['items'][0]['published'])->toBeTrue()
        ->and($rows[0]['items'][1]['published'])->toBeFalse()
        ->and($rows[1]['published'])->toBeFalse();

    expect(fn () => $service->changeSelection($settings, HomeTemplate::Custom, $parents, [
        ['index' => 0, 'type' => 'list', 'child' => 19],
    ], 'delete'))->toThrow(ValidationException::class);

    expect($service->configuration($settings->fresh())['custom']['components'])->toBe($rows);

    expect($service->changeSelection($settings, HomeTemplate::Custom, $parents, $children, 'delete'))->toBeTrue();
    $remaining = $service->configuration($settings->fresh())['custom']['components'];
    expect(count($remaining))->toBe(1)
        ->and($remaining[0]['items'])->toHaveCount(1)
        ->and($remaining[0]['items'][0]['title'])->toBe('First');
});

it('creates and converts a Home heading through the real editor without losing its content', function (HomeTemplate $template): void {
    $this->actingAs(User::factory()->admin()->create(), 'web');
    \Filament\Facades\Filament::setCurrentPanel('admin');
    \Filament\Facades\Filament::bootCurrentPanel();
    $settings = homeComponentTypeSettings($template, []);
    $editor = \Livewire\Livewire::test(\App\Filament\Pages\HomePresentation::class)
        ->mountAction('addComponent')->fillForm(['kind' => 'heading', 'title' => 'Heading to preserve'])
        ->callMountedAction()->assertHasNoFormErrors();
    expect(app(HomePresentationEditorialService::class)->configuration($settings->fresh())[$template->value]['components'][0]['title'])->toBe('Heading to preserve');
    $before = \App\Models\AuditEvent::query()->count();
    $notifications = \App\Models\AdminNotification::query()->count();
    $editor->mountAction('editComponent', ['index' => 0, 'type' => 'text'])
        ->set('mountedActions.0.data.editor_kind', 'rich_text')->assertHasNoErrors();
    $converted = app(HomePresentationEditorialService::class)->configuration($settings->fresh())[$template->value]['components'][0];
    expect($converted['body'])->toBe('Heading to preserve')->and($converted['title'])->toBeNull()
        ->and(\App\Models\AuditEvent::query()->count())->toBe($before + 1)
        ->and(\App\Models\AdminNotification::query()->count())->toBe($notifications + 1)
        ->and(collect($editor->effects['dispatches'] ?? [])->where('name', 'admin-header-notification')->count())->toBe(1);
})->with([HomeTemplate::Custom, HomeTemplate::UnderConstruction]);
