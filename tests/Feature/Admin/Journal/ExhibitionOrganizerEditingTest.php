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

it('audits field visibility through the existing exhibition action without duplicate notifications', function (): void {
    $section = app(SiteSectionEditorialService::class)->createJournal(
        'Visibility Journal', 'visibility-journal', JournalTemplate::Exhibitions->value,
    );
    $entry = app(ExhibitionEditorialService::class)->createDraft([
        'site_section_id' => $section->getKey(),
        'title' => 'Visibility Exhibition',
        'slug' => 'visibility-exhibition',
        'organizer' => 'Organizer One',
        'venue' => 'Venue One',
    ]);
    app(\App\Domain\Admin\AdminActivityNotificationContext::class)->discard();
    $auditCount = AuditEvent::query()->count();
    $notificationCount = AdminNotification::query()->count();

    $editor = Livewire::test(JournalWorkspace::class, ['section' => $section->getKey()])
        ->mountAction('editExhibition', ['exhibition' => $entry->getKey()])
        ->call('setExhibitionFieldVisibility', $entry->getKey(), 'organizer', false)
        ->assertHasNoFormErrors()
        ->assertDispatched('admin-header-notification');

    expect($entry->fresh()->publicFieldVisible('organizer'))->toBeFalse()
        ->and($entry->fresh()->publicFieldVisible('venue'))->toBeTrue()
        ->and(AuditEvent::query()->count())->toBe($auditCount + 1)
        ->and(AuditEvent::query()->latest('id')->value('action'))->toBe('exhibition.updated')
        ->and(AdminNotification::query()->count())->toBe($notificationCount + 1);

    $editor->call('setExhibitionFieldVisibility', $entry->getKey(), 'organizer', false);
    expect(AuditEvent::query()->count())->toBe($auditCount + 1)
        ->and(AdminNotification::query()->count())->toBe($notificationCount + 1);

    $editor->call('setExhibitionFieldVisibility', $entry->getKey(), 'venue', false)
        ->assertHasNoFormErrors();
    expect($entry->fresh()->publicFieldVisible('venue'))->toBeFalse()
        ->and(AuditEvent::query()->count())->toBe($auditCount + 2)
        ->and(AdminNotification::query()->count())->toBe($notificationCount + 2);
});

it('saves cover and gallery video selections through the mounted exhibition editor', function (): void {
    $section = app(SiteSectionEditorialService::class)->createJournal(
        'Media Journal', 'media-journal', JournalTemplate::Exhibitions->value,
    );
    $entry = app(ExhibitionEditorialService::class)->createDraft([
        'site_section_id' => $section->getKey(),
        'title' => 'Media Exhibition',
        'slug' => 'media-exhibition',
        'starts_on' => '2027-03-01',
    ]);
    $video = static function (string $name): \App\Models\MediaAsset {
        return \App\Models\MediaAsset::query()->create([
            'storage_key' => 'originals/'.$name,
            'original_filename' => $name,
            'mime_type' => 'video/mp4',
            'byte_size' => 4,
            'sha256' => hash('sha256', $name),
            'state' => 'available',
            'alt_text' => 'Video '.$name,
        ]);
    };
    $first = $video('exhibition-first.mp4');
    $second = $video('exhibition-second.mp4');
    app(\App\Domain\Admin\AdminActivityNotificationContext::class)->discard();

    $editor = Livewire::test(JournalWorkspace::class, ['section' => $section->getKey()])
        ->mountAction('editExhibition', ['exhibition' => $entry->getKey()])
        ->set('mountedActions.0.data.cover_media_asset_id', $first->getKey())
        ->set('mountedActions.0.data.gallery_enabled', true)
        ->set('mountedActions.0.data.gallery_presentation', 'slideshow')
        ->set('mountedActions.0.data.gallery_images', [
            ['media_asset_id' => $first->getKey()],
            ['media_asset_id' => $second->getKey()],
        ])
        ->call('callMountedAction')
        ->assertHasNoFormErrors()
        ->assertDispatched('admin-header-notification');

    $usages = $entry->fresh()->mediaUsages()->orderBy('position')->get();
    expect($usages->pluck('media_asset_id')->all())->toBe([$first->getKey(), $first->getKey(), $second->getKey()])
        ->and($entry->fresh()->gallery_enabled)->toBeTrue()
        ->and($entry->fresh()->gallery_presentation)->toBe('slideshow');

    $editor->set('mountedActions.0.data.cover_media_asset_id', $second->getKey())
        ->set('mountedActions.0.data.gallery_images', [
            ['media_asset_id' => $second->getKey()],
            ['media_asset_id' => $first->getKey()],
        ])
        ->call('callMountedAction')
        ->assertHasNoFormErrors();

    $usages = $entry->fresh()->mediaUsages()->orderBy('position')->get();
    expect($usages->pluck('media_asset_id')->all())->toBe([$second->getKey(), $second->getKey(), $first->getKey()]);

    $editor->set('mountedActions.0.data.cover_media_asset_id', null)
        ->set('mountedActions.0.data.gallery_images', [
            ['media_asset_id' => $first->getKey()],
        ])
        ->call('callMountedAction')
        ->assertHasNoFormErrors();

    $usages = $entry->fresh()->mediaUsages()->orderBy('position')->get();
    expect($usages->count())->toBe(1)
        ->and($usages->first()->media_asset_id)->toBe($first->getKey())
        ->and($usages->first()->role)->toBe(\App\Models\JournalEntryMedia::ROLE_GALLERY);
});
