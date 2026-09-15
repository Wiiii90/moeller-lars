<?php

use App\Domain\Admin\AdminSettingsService;
use App\Domain\Publication\PublicationService;
use App\Filament\Pages\General;
use App\Models\PublicContentSetting;
use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;

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
    $committedCss = 'linear-gradient(2deg, #555555, #C9C3C3) fixed';
    $workingCss = 'linear-gradient(2deg, #555555, #0F19FA) fixed';

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

    $this->get('/')
        ->assertOk()
        ->assertSee('--public-page: '.$committedCss.';', false)
        ->assertDontSee($working, false);

    $this->get('/preview')
        ->assertOk()
        ->assertSee('--public-page: '.$workingCss.';', false)
        ->assertDontSee($committed, false)
        ->assertHeader('X-Robots-Tag', 'noindex, nofollow, noarchive');

    $nextWorking = '#123ABC';
    app(AdminSettingsService::class)->updatePublicContent(PublicContentSetting::general(), [
        'background_gradient_end' => $nextWorking,
    ]);

    Livewire::test(General::class)
        ->assertSet('data.background_secondary_color', $nextWorking);

    $this->get('/')
        ->assertOk()
        ->assertSee($committed, false)
        ->assertDontSee($nextWorking, false);

    $checkpoint = app(PublicationService::class)->commit($this->actor);

    expect($checkpoint)->not->toBeNull()
        ->and(app(PublicationService::class)->hasPendingChanges())->toBeFalse()
        ->and((string) DB::table('committed.public_content_settings')
            ->where('scope', PublicContentSetting::SCOPE_GENERAL)
            ->value('background_gradient_end'))->toBe($nextWorking);

    $this->get('/')
        ->assertOk()
        ->assertSee($nextWorking, false)
        ->assertDontSee($committed, false);
});
