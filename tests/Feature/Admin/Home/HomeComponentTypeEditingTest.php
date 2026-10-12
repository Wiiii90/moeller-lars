<?php

use App\Domain\Content\HomePresentationEditorialService;
use App\Domain\Content\HomeTemplate;
use App\Filament\Pages\HomePresentation;
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

it('reuses the Custom Page component hierarchy without hiding the type selector or repeating type metadata', function (): void {
    $home = file_get_contents(resource_path('views/filament/pages/home-presentation.blade.php'));
    $rows = file_get_contents(resource_path('views/filament/pages/partials/home-component-sequence.blade.php'));
    $styles = file_get_contents(resource_path('css/admin/home.css'));
    $responsive = file_get_contents(resource_path('css/admin/responsive.css'));

    expect($home)->toContain("partials.home-component-sequence")
        ->and($home)->not->toContain('home-components-table')
        ->and($rows)->toContain('custom-page-component-sequence home-component-sequence admin-hierarchy')
        ->and($rows)->toContain('class="admin-inline-select custom-page-component__type-select"')
        ->and($rows)->toContain("mountAction('changeComponentType'")
        ->and($rows)->toContain('wire:sort="sortComponent"')
        ->and($rows)->not->toContain('admin-responsive-meta')
        ->and($styles)->toContain('--admin-row-actions-move-edit-delete-width')
        ->and($responsive)->not->toContain('home-components-table');
});

it('converts Home headings to Rich Text without discarding their text', function (HomeTemplate $template): void {
    $this->actingAs(User::factory()->admin()->create(), 'web');
    $settings = homeComponentTypeSettings($template, [
        ['type' => 'text', 'title' => 'A heading to keep', 'body' => null],
    ]);
    $service = app(HomePresentationEditorialService::class);

    expect($service->conversionLosesContent($settings->configuration[$template->value]['components'][0], 'rich_text'))->toBeFalse()
        ->and($service->convertComponent($settings, $template, 0, 'text', 'heading', 'rich_text'))->toBeTrue();

    $component = $service->configuration($settings->fresh())[$template->value]['components'][0];
    expect($component)->toBe([
        'type' => 'text',
        'title' => null,
        'body' => 'A heading to keep',
    ]);
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

    expect($service->configuration($settings->fresh())['custom']['components'][0])->toBe([
        'type' => 'text',
        'title' => null,
        'body' => null,
    ]);

    expect(fn () => $service->convertComponent($settings, HomeTemplate::Custom, 0, 'text', 'rich_text', 'divider'))
        ->toThrow(ValidationException::class);
});

it('only offers Home-supported editable types without expanding the public component data model', function (): void {
    $page = new HomePresentation;

    expect($page->componentTypeOptions)->toBe([
        'image' => 'Image',
        'heading' => 'Heading',
        'rich_text' => 'Rich Text',
        'divider' => 'Divider',
    ])->and($page->newComponentOptions)->toHaveKey('heading');
});

it('accepts the four shared Divider variants for Home and rejects invalid styles', function (): void {
    $this->actingAs(User::factory()->admin()->create(), 'web');
    $settings = homeComponentTypeSettings(HomeTemplate::Custom, [['type' => 'divider']]);
    $service = app(HomePresentationEditorialService::class);

    foreach (\App\Domain\Content\DividerVariant::VALUES as $variant) {
        expect($service->updateComponent(
            $settings, HomeTemplate::Custom, 0, 'divider',
            ['type' => 'divider', 'variant' => $variant],
        ))->toBeBool();
        expect($service->configuration($settings->fresh())['custom']['components'][0]['variant'])->toBe($variant);
    }

    expect(\App\Models\CustomPageSetting::DIVIDER_VARIANTS)->toBe(\App\Domain\Content\DividerVariant::VALUES);
});
