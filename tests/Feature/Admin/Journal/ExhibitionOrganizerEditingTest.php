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

it('autosaves precise and partial Exhibition dates in the shared editor without manufacturing day values', function (): void {
    $section = app(SiteSectionEditorialService::class)->createJournal(
        'Date precision journal', 'date-precision-journal', JournalTemplate::Exhibitions->value,
    );
    $entry = app(ExhibitionEditorialService::class)->createDraft([
        'site_section_id' => $section->getKey(),
        'title' => 'Dated exhibition',
        'slug' => 'dated-exhibition',
    ]);
    app(\App\Domain\Admin\AdminActivityNotificationContext::class)->discard();
    $audits = AuditEvent::query()->count();
    $notifications = AdminNotification::query()->count();

    $editor = Livewire::test(JournalWorkspace::class, ['section' => $section->getKey()])
        ->mountAction('editExhibition', ['exhibition' => $entry->getKey()])
        ->set('mountedActions.0.data.starts_on', '2027-06')
        ->set('mountedActions.0.data.ends_on', '2027-08')
        ->callMountedAction()->assertHasNoFormErrors()
        ->assertSet('mountedActions.0.name', 'editExhibition');

    expect($entry->fresh()->starts_on)->toBe('2027-06')
        ->and($entry->fresh()->ends_on)->toBe('2027-08')
        ->and(AuditEvent::query()->count())->toBe($audits + 1)
        ->and(AdminNotification::query()->count())->toBe($notifications + 1);
    $reopened = Livewire::test(JournalWorkspace::class, ['section' => $section->getKey()])
        ->mountAction('editExhibition', ['exhibition' => $entry->getKey()])
        ->assertActionDataSet(['starts_on' => '2027-06', 'ends_on' => '2027-08']);
    $reopened->callMountedAction()->assertHasNoFormErrors()->assertNotDispatched('admin-header-notification');
    expect(AuditEvent::query()->count())->toBe($audits + 1)
        ->and(AdminNotification::query()->count())->toBe($notifications + 1);

    $reopened->set('mountedActions.0.data.starts_on', '2027-06-17')
        ->set('mountedActions.0.data.ends_on', null)
        ->callMountedAction()->assertHasNoFormErrors();
    expect($entry->fresh()->starts_on)->toBe('2027-06-17')
        ->and($entry->fresh()->ends_on)->toBeNull();
});

it('rejects invalid or backwards Exhibition partial dates atomically in the mounted form', function (
    string $start,
    ?string $end,
    string $field,
): void {
    $section = app(SiteSectionEditorialService::class)->createJournal(
        'Invalid date journal', 'invalid-date-journal', JournalTemplate::Exhibitions->value,
    );
    $entry = app(ExhibitionEditorialService::class)->createDraft([
        'site_section_id' => $section->getKey(),
        'title' => 'Protected exhibition',
        'slug' => 'protected-exhibition',
        'starts_on' => '2027-07',
        'ends_on' => '2027-08',
    ]);
    app(\App\Domain\Admin\AdminActivityNotificationContext::class)->discard();
    $before = $entry->fresh()->getAttributes();
    $audits = AuditEvent::query()->count();
    $notifications = AdminNotification::query()->count();

    Livewire::test(JournalWorkspace::class, ['section' => $section->getKey()])
        ->mountAction('editExhibition', ['exhibition' => $entry->getKey()])
        ->set('mountedActions.0.data.title', 'Rejected edit')
        ->set('mountedActions.0.data.starts_on', $start)
        ->set('mountedActions.0.data.ends_on', $end)
        ->callMountedAction()->assertHasFormErrors([$field])
        ->assertNotDispatched('admin-header-notification');

    expect($entry->fresh()->getAttributes())->toBe($before)
        ->and(AuditEvent::query()->count())->toBe($audits)
        ->and(AdminNotification::query()->count())->toBe($notifications);
})->with([
    'invalid calendar day' => ['2027-02-29', null, 'starts_on'],
    'backwards month range' => ['2027-07', '2027-05', 'ends_on'],
    'end without start' => ['', '2027-06', 'ends_on'],
    'noncanonical month' => ['2027-00', null, 'starts_on'],
]);

it('filters Exhibition timing using the exact same partial-date certainty as the displayed entry', function (): void {
    \Carbon\Carbon::setTestNow('2027-06-12 12:00:00');
    try {
        $section = app(SiteSectionEditorialService::class)->createJournal(
            'Timing precision', 'timing-precision', JournalTemplate::Exhibitions->value,
        );
        foreach ([
            ['Year only', '2027', null, 'unknown'],
            ['Month only', '2027-06', null, 'unknown'],
            ['Undated', null, null, 'unknown'],
            ['Exact current', '2027-06-11', '2027-06-13', 'current'],
            ['Future month', '2027-07', null, 'upcoming'],
            ['Past month', '2027-05', null, 'past'],
        ] as $i => [$title, $start, $end, $expected]) {
            Exhibition::query()->create([
                'site_section_id' => $section->getKey(),
                'title' => $title,
                'slug' => 'timing-'.($i + 1),
                'state' => 'draft',
                'position' => $i + 1,
                'starts_on' => $start,
                'ends_on' => $end,
            ]);
        }

        $page = Livewire::test(JournalWorkspace::class, ['section' => $section->getKey()]);
        foreach ([
            'unknown' => ['Year only', 'Month only', 'Undated'],
            'current' => ['Exact current'],
            'upcoming' => ['Future month'],
            'past' => ['Past month'],
        ] as $filter => $titles) {
            $page->set('timingFilter', $filter)->assertHasNoErrors();
            $rows = $page->get('exhibitions');
            expect(array_column($rows, 'title'))->toBe($titles);
            foreach ($rows as $row) {
                expect($row['timing'])->toBe($filter);
            }
        }
        $page->set('timingFilter', 'any')->assertHasNoErrors();
        expect($page->get('total'))->toBe(6);
    } finally {
        \Carbon\Carbon::setTestNow();
    }
});
