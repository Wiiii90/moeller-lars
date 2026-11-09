<?php

use App\Domain\Artwork\GalleryEditorialService;
use App\Domain\Artwork\GalleryPresentation;
use App\Domain\Publication\PublicationService;
use App\Filament\Pages\GalleryWorkspace;
use App\Filament\Pages\SitePages;
use App\Models\AdminNotification;
use App\Models\AuditEvent;
use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;

require_once __DIR__.'/GalleryFixtures.php';

beforeEach(function (): void {
    $this->actingAs(User::factory()->admin()->create(), 'web');
    Filament::setCurrentPanel('admin');
    Filament::bootCurrentPanel();
});

it('saves a Gallery identity through Pages and publishes its redirect', function (): void {
    $gallery = galleryFixtureCategory();
    $section = $gallery->siteSection()->firstOrFail();
    $oldSlug = (string) $section->slug;
    $newSlug = 'revised-gallery-'.$gallery->getKey();
    $before = AdminNotification::query()->count();
    $editor = Livewire::test(SitePages::class)
        ->mountAction('editPage', ['section' => $section->getKey()])
        ->fillForm(['name' => 'Revised Gallery', 'slug' => $newSlug])
        ->callMountedAction()->assertHasNoFormErrors()
        ->assertDispatched('admin-header-notification');

    expect($section->fresh()->title)->toBe('Revised Gallery')
        ->and($section->fresh()->slug)->toBe($newSlug)
        ->and($gallery->fresh()->name)->toBe('Revised Gallery')
        ->and($gallery->fresh()->slug)->toBe($newSlug)
        ->and(AdminNotification::query()->count())->toBe($before + 1);
    $editor->callMountedAction()->assertNotDispatched('admin-header-notification');
    expect(AdminNotification::query()->count())->toBe($before + 1);

    $this->get('/'.$oldSlug)->assertNotFound();
    expect(app(PublicationService::class)->commit(auth()->user()))->not->toBeNull();
    $this->get('/'.$oldSlug)->assertRedirect('/'.$newSlug);
    $this->get('/'.$newSlug)->assertOk();
});

it('rejects a duplicate Gallery slug without persisting or notifying', function (): void {
    $gallery = galleryFixtureCategory();
    $other = galleryFixtureCategory();
    $section = $gallery->siteSection()->firstOrFail();
    $before = AuditEvent::query()->count();
    $notifications = AdminNotification::query()->count();
    Livewire::test(SitePages::class)
        ->mountAction('editPage', ['section' => $section->getKey()])
        ->fillForm(['name' => 'Rejected Gallery', 'slug' => $other->slug])
        ->callMountedAction()->assertHasFormErrors(['slug'])
        ->assertNotDispatched('admin-header-notification');
    expect($section->fresh()->title)->toBe($gallery->name)
        ->and($gallery->fresh()->slug)->toBe($gallery->slug)
        ->and(AuditEvent::query()->count())->toBe($before)
        ->and(AdminNotification::query()->count())->toBe($notifications);
});

it('persists per-Gallery landscape and edge settings through one audited action', function (): void {
    $gallery = galleryFixtureCategory();
    galleryFixtureUnavailableAnalytics();
    expect(app(PublicationService::class)->commit(auth()->user()))->not->toBeNull();
    $committedBefore = DB::table('committed.artwork_categories')
        ->where('id', $gallery->getKey())
        ->first(['landscape_detail_enabled', 'artwork_menu_surface_wide', 'edge_navigation_mode', 'artwork_divider']);
    expect($committedBefore)->not->toBeNull();
    $auditBefore = AuditEvent::query()->count();
    $notificationBefore = AdminNotification::query()->count();

    expect((bool) $gallery->getAttribute('landscape_detail_enabled'))->toBeFalse()
        ->and((bool) $gallery->getAttribute('artwork_menu_surface_wide'))->toBeFalse()
        ->and(GalleryPresentation::edgeMode($gallery))->toBe('disabled')
        ->and(GalleryPresentation::divider($gallery))->toBe('none');

    $form = [
        'name' => $gallery->name,
        'slug' => $gallery->slug,
        'description' => null,
        'show_on_home' => false,
        'landscape_detail_enabled' => true,
        'artwork_menu_surface_wide' => true,
        'edge_navigation_mode' => 'cyclic',
        'artwork_divider' => 'dotted',
    ];

    Livewire::test(GalleryWorkspace::class, ['gallery' => $gallery->getKey()])
        ->mountAction('gallerySettings')
        ->fillForm($form)
        ->callMountedAction()
        ->assertHasNoFormErrors()
        ->assertDispatched('admin-header-notification');

    $saved = $gallery->fresh();
    expect((bool) $saved->landscape_detail_enabled)->toBeTrue()
        ->and((bool) $saved->artwork_menu_surface_wide)->toBeTrue()
        ->and($saved->edge_navigation_mode)->toBe('cyclic')
        ->and($saved->artwork_divider)->toBe('dotted')
        ->and(AuditEvent::query()->count())->toBe($auditBefore + 1)
        ->and(AdminNotification::query()->count())->toBe($notificationBefore + 1);

    Livewire::test(GalleryWorkspace::class, ['gallery' => $gallery->getKey()])
        ->mountAction('gallerySettings')
        ->fillForm($form)
        ->callMountedAction()
        ->assertHasNoFormErrors()
        ->assertNotDispatched('admin-header-notification');

    expect(AuditEvent::query()->count())->toBe($auditBefore + 1)
        ->and(AdminNotification::query()->count())->toBe($notificationBefore + 1);

    expect(DB::table('committed.artwork_categories')
        ->where('id', $gallery->getKey())
        ->first(['landscape_detail_enabled', 'artwork_menu_surface_wide', 'edge_navigation_mode', 'artwork_divider']))
        ->toEqual($committedBefore);

    expect(app(PublicationService::class)->commit(auth()->user()))->not->toBeNull();
    $committed = DB::table('committed.artwork_categories')
        ->where('id', $gallery->getKey())
        ->first(['landscape_detail_enabled', 'artwork_menu_surface_wide', 'edge_navigation_mode', 'artwork_divider']);
    expect((bool) $committed->landscape_detail_enabled)->toBeTrue()
        ->and((bool) $committed->artwork_menu_surface_wide)->toBeTrue()
        ->and($committed->edge_navigation_mode)->toBe('cyclic')
        ->and($committed->artwork_divider)->toBe('dotted');
});

it('validates authored Gallery rights and Artwork caption display using the central settings policy', function (): void {
    $gallery = galleryFixtureCategory();
    $service = app(GalleryEditorialService::class);

    expect(GalleryPresentation::showArtworkInformation($gallery))->toBeTrue()
        ->and(GalleryPresentation::showMediaCredits($gallery))->toBeFalse()
        ->and(GalleryPresentation::showMediaCopyright($gallery))->toBeFalse();

    $service->update($gallery, [
        'name' => $gallery->name,
        'show_artwork_information' => false,
        'show_media_credits' => true,
        'show_media_copyright' => true,
        'caption_layout' => 'rights_right',
    ]);
    $saved = $gallery->fresh();
    expect(GalleryPresentation::showArtworkInformation($saved))->toBeFalse()
        ->and(GalleryPresentation::showMediaCredits($saved))->toBeTrue()
        ->and(GalleryPresentation::showMediaCopyright($saved))->toBeTrue()
        ->and(GalleryPresentation::captionLayout($saved))->toBe('rights_right');

    expect(fn () => $service->update($saved, ['name' => $saved->name, 'caption_layout' => 'unknown']))
        ->toThrow(ValidationException::class);
});

it('rejects unsupported Gallery navigation and divider modes without mutation', function (): void {
    $gallery = galleryFixtureCategory();
    $service = app(GalleryEditorialService::class);
    $auditBefore = AuditEvent::query()->count();

    expect(fn () => $service->update($gallery, ['name' => $gallery->name,
        'edge_navigation_mode' => 'wrap-automatically',
    ]))->toThrow(ValidationException::class);
    expect(fn () => $service->update($gallery, ['name' => $gallery->name,
        'artwork_divider' => 'double',
    ]))->toThrow(ValidationException::class);

    expect($gallery->fresh()->edge_navigation_mode)->toBe('disabled')
        ->and($gallery->fresh()->artwork_divider)->toBe('none')
        ->and(AuditEvent::query()->count())->toBe($auditBefore);
});
