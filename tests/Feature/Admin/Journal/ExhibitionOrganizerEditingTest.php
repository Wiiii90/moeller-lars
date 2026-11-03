<?php

use App\Domain\Content\ExhibitionEditorialService;
use App\Domain\Content\JournalTemplate;
use App\Domain\Content\SiteSectionEditorialService;
use App\Filament\Pages\JournalWorkspace;
use App\Models\AdminNotification;
use App\Models\AuditEvent;
use App\Models\Exhibition;
use App\Models\User;
use Filament\Facades\Filament;
use Livewire\Livewire;

beforeEach(function (): void {
    $this->actingAs(User::factory()->admin()->create(), 'web');
    Filament::setCurrentPanel('admin');
    Filament::bootCurrentPanel();
});

it('persists independent organizer, physical venue and vernissage visibility via the shared editor', function (): void {
    $section = app(SiteSectionEditorialService::class)->createJournal(
        'Exhibition editorial Journal', 'exhibition-editorial-journal', JournalTemplate::Exhibitions->value,
    );
    $entry = app(ExhibitionEditorialService::class)->createDraft([
        'site_section_id' => $section->getKey(),
        'title' => 'Johannes der Täufer',
        'slug' => 'johannes-der-taeufer',
        'organizer' => 'Barfuss Galerie & Freundeskreis',
        'venue' => 'Simon-Petrus-Kirche',
        'location_text' => 'Harksheider Straße 12',
        'postal_code' => '22844',
        'city' => 'Norderstedt',
        'country' => 'Deutschland',
        'starts_on' => '2027-05-01',
        'ends_on' => '2027-06-01',
        'vernissage_at' => '2027-05-01 18:00:00',
        'show_vernissage' => false,
    ]);
    app(\App\Domain\Admin\AdminActivityNotificationContext::class)->discard();
    $initialAudits = AuditEvent::query()->count();
    $initialNotifications = AdminNotification::query()->count();

    expect($entry->summaryAttribution())->toBe('Barfuss Galerie & Freundeskreis')
        ->and($entry->mapLocationLabel())->toContain('Simon-Petrus-Kirche')
        ->and($entry->streetAddress())->toBe('Harksheider Straße 12');

    Livewire::test(JournalWorkspace::class, ['section' => $section->getKey()])
        ->mountAction('editExhibition', ['exhibition' => $entry->getKey()])
        ->assertSet('mountedActions.0.data.organizer', 'Barfuss Galerie & Freundeskreis')
        ->assertSet('mountedActions.0.data.venue', 'Simon-Petrus-Kirche')
        ->set('mountedActions.0.data.organizer', 'Galerie XY')
        ->set('mountedActions.0.data.show_vernissage', true)
        ->call('callMountedAction')
        ->assertHasNoFormErrors()
        ->assertDispatched('admin-header-notification');

    expect($entry->fresh()->getAttribute('organizer'))->toBe('Galerie XY')
        ->and($entry->fresh()->getAttribute('venue'))->toBe('Simon-Petrus-Kirche')
        ->and($entry->fresh()->getAttribute('show_vernissage'))->toBeTrue()
        ->and(AuditEvent::query()->count())->toBe($initialAudits + 1)
        ->and(AdminNotification::query()->count())->toBe($initialNotifications + 1);
});

it('round-trips formatted Exhibition descriptions through the actual mounted Journal editor', function (): void {
    $section = app(SiteSectionEditorialService::class)->createJournal(
        'Exhibition rich description', 'exhibition-rich-description', JournalTemplate::Exhibitions->value,
    );
    $entry = app(ExhibitionEditorialService::class)->createDraft([
        'site_section_id' => $section->getKey(),
        'title' => 'A synthetic exhibition',
        'slug' => 'synthetic-rich-exhibition',
    ]);
    $body = '<p data-artist-line-spacing="1.15"><a href="https://example.org/exhibition">Exhibition information</a></p>'
        .'<table><tbody><tr><th>Venue</th></tr><tr><td>Gallery</td></tr></tbody></table>';

    Livewire::test(JournalWorkspace::class, ['section' => $section->getKey()])
        ->mountAction('editExhibition', ['exhibition' => $entry->getKey()])
        ->set('mountedActions.0.data.description', $body)
        ->callMountedAction()->assertHasNoFormErrors();

    expect($entry->fresh()->description)->toBe($body);
    Livewire::test(JournalWorkspace::class, ['section' => $section->getKey()])
        ->mountAction('editExhibition', ['exhibition' => $entry->getKey()])
        ->assertActionDataSet(['description' => $body]);
});

it('shows Exhibition Gallery video controls only when the selected Storage media is video', function (): void {
    $undoRepeaterFake = \Filament\Forms\Components\Repeater::fake();

    try {
        $section = app(SiteSectionEditorialService::class)->createJournal(
            'Gallery video controls', 'gallery-video-controls', JournalTemplate::Exhibitions->value,
        );
        $image = \App\Models\MediaAsset::query()->create([
            'storage_key' => 'originals/gallery-control-photo.jpg',
            'original_filename' => 'gallery-control-photo.jpg',
            'mime_type' => 'image/jpeg',
            'byte_size' => 4,
            'sha256' => hash('sha256', 'gallery-control-photo'),
            'state' => 'available',
        ]);
        $video = \App\Models\MediaAsset::query()->create([
            'storage_key' => 'originals/gallery-control-motion.mp4',
            'original_filename' => 'gallery-control-motion.mp4',
            'mime_type' => 'video/mp4',
            'byte_size' => 4,
            'sha256' => hash('sha256', 'gallery-control-motion'),
            'state' => 'available',
        ]);

        $editor = Livewire::test(JournalWorkspace::class, ['section' => $section->getKey()])
            ->mountAction('addExhibition')
            ->set('mountedActions.0.data.gallery_enabled', true)
            ->set('mountedActions.0.data.gallery_images', [[
                'media_asset_id' => $image->getKey(),
                'video_controls_enabled' => true,
            ]]);
        $editor->assertFormFieldHidden('gallery_images.0.video_controls_enabled');

        $editor->set('mountedActions.0.data.gallery_images', [[
            'media_asset_id' => $video->getKey(),
            'video_controls_enabled' => true,
        ]])->assertFormFieldVisible('gallery_images.0.video_controls_enabled');

        $editor->set('mountedActions.0.data.gallery_images', [[
            'media_asset_id' => $image->getKey(),
            'video_controls_enabled' => true,
        ]])->assertFormFieldHidden('gallery_images.0.video_controls_enabled');
    } finally {
        $undoRepeaterFake();
    }
});
