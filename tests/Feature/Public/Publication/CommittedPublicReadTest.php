<?php

use App\Domain\Admin\AdminSettingsService;
use App\Domain\Content\SiteSectionEditorialService;
use App\Domain\Publication\PublicationService;
use App\Filament\Pages\General;
use App\Models\PublicContentSetting;
use App\Models\SiteSection;
use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;

require_once __DIR__.'/../../Admin/Gallery/GalleryFixtures.php';

beforeEach(function (): void {
    $this->actor = User::factory()->admin()->create();
    $this->actingAs($this->actor, 'web');
    Filament::setCurrentPanel('admin');
    Filament::bootCurrentPanel();
});

function committedPublicReadSetAppearance(string $end = '#C9C3C3'): void
{
    $appearance = [
        'background_mode' => 'gradient',
        'background_gradient_start' => '#555555',
        'background_gradient_end' => $end,
        'background_gradient_angle' => 2,
    ];

    foreach (['public', 'committed'] as $schema) {
        DB::table($schema.'.public_content_settings')
            ->where('scope', PublicContentSetting::SCOPE_GENERAL)
            ->update($appearance);

        DB::table($schema.'.home_presentation_settings')->update([
            'template' => 'custom',
        ]);
    }
}

it('keeps public on Committed while Preview and admin use Working until Commit', function (): void {
    committedPublicReadSetAppearance();

    $committed = '#C9C3C3';
    $working = '#0F19FA';
    expect(app(PublicationService::class)->hasPendingChanges())->toBeFalse();

    app(AdminSettingsService::class)->updatePublicContent(PublicContentSetting::general(), [
        'background_gradient_end' => $working,
    ]);

    expect((string) DB::table('public.public_content_settings')
        ->where('scope', PublicContentSetting::SCOPE_GENERAL)
        ->value('background_gradient_end'))->toBe($working)
        ->and((string) DB::table('committed.public_content_settings')
            ->where('scope', PublicContentSetting::SCOPE_GENERAL)
            ->value('background_gradient_end'))->toBe($committed)
        ->and((string) PublicContentSetting::general()->getAttribute('background_gradient_end'))->toBe($working)
        ->and(app(PublicationService::class)->hasPendingChanges())->toBeTrue();

    Auth::guard('web')->logout();
    $this->get('/')
        ->assertOk()
        ->assertSee($committed, false)
        ->assertDontSee($working, false);

    $this->actingAs($this->actor, 'web');
    $this->get('/preview')
        ->assertOk()
        ->assertSee($working, false)
        ->assertDontSee($committed, false)
        ->assertHeader('X-Robots-Tag', 'noindex, nofollow, noarchive');

    $nextWorking = '#123ABC';
    app(AdminSettingsService::class)->updatePublicContent(PublicContentSetting::general(), [
        'background_gradient_end' => $nextWorking,
    ]);

    Livewire::test(General::class)
        ->assertSet('data.background_secondary_color', $nextWorking);

    Auth::guard('web')->logout();
    $this->get('/')
        ->assertOk()
        ->assertSee($committed, false)
        ->assertDontSee($nextWorking, false);

    $this->actingAs($this->actor, 'web');
    $checkpoint = app(PublicationService::class)->commit($this->actor);

    expect($checkpoint)->not->toBeNull()
        ->and(app(PublicationService::class)->hasPendingChanges())->toBeFalse()
        ->and((string) DB::table('committed.public_content_settings')
            ->where('scope', PublicContentSetting::SCOPE_GENERAL)
            ->value('background_gradient_end'))->toBe($nextWorking);

    Auth::guard('web')->logout();
    $this->get('/')
        ->assertOk()
        ->assertSee($nextWorking, false)
        ->assertDontSee($committed, false);
});

it('publishes the artist title independently of navigation', function (): void {
    committedPublicReadSetAppearance();

    app(AdminSettingsService::class)->updatePublicContent(PublicContentSetting::general(), [
        'header_name_visible' => true,
        'header_name_text' => 'Atelier Neue',
        'header_name_position' => 'outside',
    ]);

    $this->get('/preview')->assertOk()->assertSee('Atelier Neue');
    Auth::guard('web')->logout();
    $this->get('/')->assertOk()->assertDontSee('Atelier Neue');

    $this->actingAs($this->actor, 'web');
    app(PublicationService::class)->commit($this->actor, 'Publish artist title');
    Auth::guard('web')->logout();
    $this->get('/')->assertOk()->assertSee('Atelier Neue');

    $this->actingAs($this->actor, 'web');
    app(AdminSettingsService::class)->updatePublicContent(PublicContentSetting::general(), [
        'header_name_visible' => false,
    ]);
    $this->get('/preview')->assertOk()->assertDontSee('Atelier Neue')->assertSee('data-site-navigation');
    app(PublicationService::class)->commit($this->actor, 'Hide artist title');

    Auth::guard('web')->logout();
    $this->get('/')->assertOk()->assertDontSee('Atelier Neue')->assertSee('data-site-navigation');
});

it('publishes a Gallery parent as a page even when it has child navigation', function (): void {
    committedPublicReadSetAppearance();
    $parent = galleryFixtureCategory();
    $child = galleryFixtureCategory();
    $parentSection = SiteSection::query()->where('artwork_category_id', $parent->getKey())->firstOrFail();
    $childSection = SiteSection::query()->where('artwork_category_id', $child->getKey())->firstOrFail();
    app(SiteSectionEditorialService::class)->updatePlacement(
        $childSection, 'published', (int) $parentSection->getKey(),
    );

    $this->get('/preview/'.$parent->slug)->assertOk();
    app(PublicationService::class)->commit($this->actor, 'Publish nested Gallery');
    Auth::guard('web')->logout();
    $this->get('/'.$parent->slug)->assertOk();
    $this->get('/'.$child->slug)->assertOk();
});
