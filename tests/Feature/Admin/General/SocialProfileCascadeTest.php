<?php

use App\Domain\Admin\AdminSettingsService;
use App\Domain\Content\HomePresentationEditorialService;
use App\Domain\Content\HomeTemplate;
use App\Domain\Content\SiteSectionEditorialService;
use App\Filament\Pages\General;
use App\Models\AuditEvent;
use App\Models\CustomPageSetting;
use App\Models\HomePresentationSetting;
use App\Models\PublicContentSetting;
use App\Models\SiteSection;
use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    $this->actingAs(User::factory()->admin()->create(), 'web');
    Filament::setCurrentPanel('admin');
    Filament::bootCurrentPanel();
});

it('cascades a General social profile deletion through Custom Pages and both stored Home templates', function (): void {
    $settingsService = app(AdminSettingsService::class);
    $links = [
        ['platform' => 'instagram', 'url' => 'https://example.invalid/instagram'],
        ['platform' => 'facebook', 'url' => 'https://example.invalid/facebook'],
        ['platform' => 'youtube', 'url' => 'https://example.invalid/youtube'],
    ];
    $settingsService->updatePublicContent(PublicContentSetting::general(), ['social_links' => $links]);

    $section = app(SiteSectionEditorialService::class)->createCustomPage('Contact cascade', 'contact-cascade');
    $page = CustomPageSetting::query()->where('site_section_id', $section->getKey())->sole();
    $socialChild = static fn (array $platforms): array => [
        'type' => 'social_links', 'published' => true, 'social_platforms' => $platforms,
    ];
    $page->update(['blocks' => [
        ['type' => 'contact', 'published' => true, 'title' => 'Main contact', 'children' => [
            ['type' => 'public_email', 'published' => true],
            $socialChild(['instagram', 'facebook', 'youtube']),
        ]],
        ['type' => 'image', 'published' => false, 'media_asset_id' => null, 'image_decorative' => true,
            'children' => [['type' => 'contact', 'published' => true, 'title' => 'Image contact',
                'children' => [$socialChild(['facebook'])]]]],
    ]]);

    $homeSection = SiteSection::query()->where('type', SiteSection::TYPE_HOME)->firstOrFail();
    $home = HomePresentationSetting::query()->where('site_section_id', $homeSection->getKey())->sole();
    $configuration = HomePresentationEditorialService::defaults();
    $configuration[HomeTemplate::Custom->value]['components'] = [
        ['type' => 'contact', 'published' => true, 'children' => [$socialChild(['facebook', 'youtube'])]],
    ];
    $configuration[HomeTemplate::UnderConstruction->value]['components'] = [
        ['type' => 'contact', 'published' => false, 'children' => [$socialChild(['instagram', 'facebook'])]],
    ];
    $home->update(['configuration' => $configuration]);

    $auditBefore = AuditEvent::query()->count();
    Livewire::test(General::class)->call('deleteSocialLink', 1)->assertHasNoErrors();

    expect(array_column(PublicContentSetting::general()->getAttribute('social_links'), 'platform'))
        ->toBe(['instagram', 'youtube']);
    $components = $page->fresh()->components();
    expect($components[0]['title'])->toBe('Main contact')
        ->and($components[0]['children'][0]['type'])->toBe('public_email')
        ->and($components[0]['children'][1]['social_platforms'])->toBe(['instagram', 'youtube'])
        ->and($components[1]['children'][0]['children'][0]['social_platforms'])->toBe([]);

    $home = $home->fresh();
    expect($home->components(HomeTemplate::Custom)[0]['children'][0]['social_platforms'])->toBe(['youtube'])
        ->and($home->components(HomeTemplate::UnderConstruction)[0]['children'][0]['social_platforms'])->toBe(['instagram'])
        ->and(AuditEvent::query()->count())->toBe($auditBefore + 3);

    // Re-adding the deleted General profile must not restore old choices.
    $settingsService->updatePublicContent(PublicContentSetting::general(), ['social_links' => $links]);
    expect($page->fresh()->components()[0]['children'][1]['social_platforms'])->toBe(['instagram', 'youtube'])
        ->and($home->fresh()->components(HomeTemplate::Custom)[0]['children'][0]['social_platforms'])->toBe(['youtube']);
});

it('does not cascade a profile URL or ordering change', function (): void {
    $service = app(AdminSettingsService::class);
    $instagram = ['platform' => 'instagram', 'url' => 'https://example.invalid/old'];
    $facebook = ['platform' => 'facebook', 'url' => 'https://example.invalid/facebook'];
    $service->updatePublicContent(PublicContentSetting::general(), [
        'social_links' => [$instagram, $facebook],
    ]);
    $section = app(SiteSectionEditorialService::class)->createCustomPage('Preserved contact', 'preserved-contact');
    $page = CustomPageSetting::query()->where('site_section_id', $section->getKey())->sole();
    $page->update(['blocks' => [['type' => 'contact', 'children' => [
        ['type' => 'social_links', 'published' => false, 'social_platforms' => ['facebook']],
    ]]]]);

    $before = AuditEvent::query()->count();
    $service->updatePublicContent(PublicContentSetting::general(), ['social_links' => [
        $facebook, ['platform' => 'instagram', 'url' => 'https://example.invalid/new'],
    ]]);

    expect($page->fresh()->components()[0]['children'][0]['social_platforms'])->toBe(['facebook'])
        ->and(AuditEvent::query()->count())->toBe($before + 1);
});
it('does not recreate an explicitly deleted last Instagram profile from legacy fields', function (): void {
    $service = app(AdminSettingsService::class);
    $general = PublicContentSetting::general();
    $general->update(['instagram_handle' => 'historical_artist', 'social_links' => [
        ['platform' => 'instagram', 'url' => 'https://example.invalid/instagram'],
    ]]);

    $section = app(SiteSectionEditorialService::class)->createCustomPage('Last social link', 'last-social-link');
    $page = CustomPageSetting::query()->where('site_section_id', $section->getKey())->sole();
    $page->update(['blocks' => [['type' => 'contact', 'children' => [[
        'type' => 'social_links', 'published' => true, 'social_platforms' => ['instagram'],
    ]]]]]);

    $service->updatePublicContent($general, ['social_links' => []]);

    expect(PublicContentSetting::general()->getAttribute('social_links'))->toBe([])
        ->and($page->fresh()->components()[0]['children'][0]['social_platforms'])->toBe([]);
});

