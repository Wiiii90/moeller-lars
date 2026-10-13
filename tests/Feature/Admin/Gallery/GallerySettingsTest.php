<?php

use App\Domain\Publication\PublicationService;
use App\Filament\Pages\GalleryWorkspace;
use App\Models\AdminNotification;
use App\Models\AuditEvent;
use App\Models\ArtworkCategory;
use App\Models\Redirect;
use App\Models\User;
use Filament\Facades\Filament;
use Livewire\Livewire;

require_once __DIR__.'/GalleryFixtures.php';

beforeEach(function (): void {
    $this->actingAs(User::factory()->admin()->create(), 'web');
    Filament::setCurrentPanel('admin');
    Filament::bootCurrentPanel();
});

it('saves a Gallery slug change and only redirects the old public URL after Commit', function (): void {
    $gallery = galleryFixtureCategory();
    $originalSlug = (string) $gallery->slug;
    $nextSlug = 'revised-gallery-'.$gallery->getKey();
    galleryFixtureUnavailableAnalytics();
    $auditsBefore = AuditEvent::query()->count();
    $notificationsBefore = AdminNotification::query()->count();

    $settings = [
        'name' => $gallery->name,
        'slug' => $nextSlug,
        'description' => $gallery->description,
        'show_on_home' => (bool) $gallery->show_on_home,
    ];

    Livewire::test(GalleryWorkspace::class, ['gallery' => $gallery->getKey()])
        ->mountAction('gallerySettings')
        ->fillForm($settings)
        ->callMountedAction()
        ->assertHasNoFormErrors()
        ->assertDispatched('admin-header-notification');

    expect($gallery->fresh()->slug)->toBe($nextSlug)
        ->and($gallery->siteSection()->firstOrFail()->slug)->toBe($nextSlug)
        ->and(Redirect::query()->where('source_path', '/'.$originalSlug)->where('target_path', '/'.$nextSlug)->where('status_code', 301)->exists())->toBeTrue()
        ->and(AuditEvent::query()->count())->toBe($auditsBefore + 1)
        ->and(AdminNotification::query()->count())->toBe($notificationsBefore + 1);

    $this->get('/'.$originalSlug)->assertNotFound();
    app(PublicationService::class)->commit(auth()->user());
    $this->get('/'.$originalSlug)->assertStatus(301)->assertRedirect('/'.$nextSlug);
    $this->get('/'.$nextSlug)->assertOk();

    $auditsAfter = AuditEvent::query()->count();
    $notificationsAfter = AdminNotification::query()->count();

    Livewire::test(GalleryWorkspace::class, ['gallery' => $gallery->getKey()])
        ->mountAction('gallerySettings')
        ->fillForm($settings)
        ->callMountedAction()
        ->assertHasNoFormErrors()
        ->assertNotDispatched('admin-header-notification');

    expect(AuditEvent::query()->count())->toBe($auditsAfter)
        ->and(AdminNotification::query()->count())->toBe($notificationsAfter);
});

it('rolls back Gallery name changes when the requested slug belongs to another Gallery', function (): void {
    $gallery = galleryFixtureCategory();
    $otherGallery = galleryFixtureCategory();
    $previousName = (string) $gallery->name;
    $previousSlug = (string) $gallery->slug;
    galleryFixtureUnavailableAnalytics();
    $auditsBefore = AuditEvent::query()->count();
    $notificationsBefore = AdminNotification::query()->count();

    Livewire::test(GalleryWorkspace::class, ['gallery' => $gallery->getKey()])
        ->mountAction('gallerySettings')
        ->fillForm([
            'name' => 'Must not persist',
            'slug' => $otherGallery->slug,
            'description' => 'Must roll back too',
            'show_on_home' => true,
        ])
        ->callMountedAction()
        ->assertHasFormErrors(['slug'])
        ->assertNotDispatched('admin-header-notification');

    $fresh = ArtworkCategory::query()->findOrFail($gallery->getKey());
    expect($fresh->name)->toBe($previousName)
        ->and($fresh->slug)->toBe($previousSlug)
        ->and($fresh->description)->toBeNull()
        ->and((bool) $fresh->show_on_home)->toBeFalse()
        ->and($fresh->siteSection()->firstOrFail()->title)->toBe($previousName)
        ->and(AuditEvent::query()->count())->toBe($auditsBefore)
        ->and(AdminNotification::query()->count())->toBe($notificationsBefore);
});


it('surfaces a combined Gallery title and slug edit as one successful admin action', function (): void {
    $gallery = galleryFixtureCategory();
    galleryFixtureUnavailableAnalytics();
    $originalSlug = (string) $gallery->slug;
    $nextSlug = 'combined-gallery-'.$gallery->getKey();
    $auditsBefore = AuditEvent::query()->count();
    $notificationsBefore = AdminNotification::query()->count();

    Livewire::test(GalleryWorkspace::class, ['gallery' => $gallery->getKey()])
        ->mountAction('gallerySettings')
        ->fillForm([
            'name' => 'Combined Gallery title',
            'slug' => $nextSlug,
            'description' => 'Edited together',
            'show_on_home' => true,
        ])
        ->callMountedAction()
        ->assertHasNoFormErrors()
        ->assertDispatched('admin-header-notification');

    $updated = $gallery->fresh();
    expect($updated->name)->toBe('Combined Gallery title')
        ->and($updated->slug)->toBe($nextSlug)
        ->and($updated->description)->toBe('Edited together')
        ->and((bool) $updated->show_on_home)->toBeTrue()
        ->and($updated->siteSection()->firstOrFail()->title)->toBe('Combined Gallery title')
        ->and(Redirect::query()->where('source_path', '/'.$originalSlug)->where('target_path', '/'.$nextSlug)->exists())->toBeTrue()
        // Two append-only audit mutations still describe one submitted UI action.
        ->and(AuditEvent::query()->count())->toBe($auditsBefore + 2)
        ->and(AdminNotification::query()->count())->toBe($notificationsBefore + 1);
});
