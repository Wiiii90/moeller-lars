<?php

use App\Domain\Publication\PublicationService;
use App\Models\ArtworkMaterialPreset;
use App\Filament\Pages\GalleryWorkspace;
use App\Models\AdminNotification;
use App\Models\AuditEvent;
use App\Models\User;
use Filament\Facades\Filament;
use Livewire\Livewire;

require_once __DIR__.'/GalleryFixtures.php';

beforeEach(function (): void {
    $this->actingAs(User::factory()->admin()->create(), 'web');
    Filament::setCurrentPanel('admin');
    Filament::bootCurrentPanel();
});

it('edits material presets in the shared live table while preserving record identities and ranks', function (): void {
    $gallery = galleryFixtureCategory();
    galleryFixtureUnavailableAnalytics();
    $editor = Livewire::test(GalleryWorkspace::class, ['gallery' => $gallery->getKey()])
        ->mountAction('materialPresets')
        ->assertMountedActionModalSee('Material presets');

    $editor->call('addMaterialPreset', 'Bronze')->assertDispatched('admin-header-notification');
    $editor->call('addMaterialPreset', 'Ink');
    $bronze = ArtworkMaterialPreset::query()->where('name', 'Bronze')->firstOrFail();
    $ink = ArtworkMaterialPreset::query()->where('name', 'Ink')->firstOrFail();
    expect(app(\App\Domain\Artwork\ArtworkMaterialPresetService::class)->ordered()->pluck('name')->all())
        ->toBe(['Bronze', 'Ink']);

    $editor->call('renameMaterialPreset', (int) $bronze->getKey(), 'Gold');
    expect($bronze->fresh()->name)->toBe('Gold');
    $editor->call('sortMaterialPreset', (int) $ink->getKey(), 0);
    expect(app(\App\Domain\Artwork\ArtworkMaterialPresetService::class)->ordered()->pluck('name')->all())
        ->toBe(['Ink', 'Gold']);
    $editor->call('deleteMaterialPreset', (int) $bronze->getKey());
    expect(ArtworkMaterialPreset::query()->whereKey($bronze->getKey())->exists())->toBeFalse()
        ->and(app(\App\Domain\Artwork\ArtworkMaterialPresetService::class)->ordered()->pluck('name')->all())->toBe(['Ink']);
});

it('does not silently merge duplicate material names or emit success for no-op edits', function (): void {
    $gallery = galleryFixtureCategory();
    galleryFixtureUnavailableAnalytics();
    $editor = Livewire::test(GalleryWorkspace::class, ['gallery' => $gallery->getKey()])
        ->mountAction('materialPresets');

    $editor->call('addMaterialPreset', 'Bronze');
    $preset = ArtworkMaterialPreset::query()->where('name', 'Bronze')->firstOrFail();
    $auditsBefore = AuditEvent::query()->count();
    $successBefore = AdminNotification::query()->where('status', 'success')->count();

    $editor->call('renameMaterialPreset', (int) $preset->getKey(), 'Bronze');
    expect(AuditEvent::query()->count())->toBe($auditsBefore)
        ->and(AdminNotification::query()->where('status', 'success')->count())->toBe($successBefore);

    $editor->call('addMaterialPreset', 'bronze');
    expect(ArtworkMaterialPreset::query()->count())->toBe(1)
        ->and(AuditEvent::query()->count())->toBe($auditsBefore)
        ->and(AdminNotification::query()->where('status', 'success')->count())->toBe($successBefore);
});

it('persists per-Gallery landscape and edge settings through one audited action', function (): void {
    $gallery = galleryFixtureCategory();
    galleryFixtureUnavailableAnalytics();
    $auditBefore = AuditEvent::query()->count();
    $notificationBefore = AdminNotification::query()->count();

    expect((bool) $gallery->getAttribute('landscape_detail_enabled'))->toBeFalse()
        ->and((bool) $gallery->getAttribute('portrait_detail_enabled'))->toBeFalse()
        ->and($gallery->getAttribute('edge_navigation_mode'))->toBe('disabled')
        ->and($gallery->getAttribute('artwork_divider'))->toBe('none');

    $form = [
        'name' => $gallery->name,
        'show_on_home' => false,
        'landscape_detail_enabled' => true,
        'portrait_detail_enabled' => true,
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
        ->and((bool) $saved->portrait_detail_enabled)->toBeTrue()
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

    expect(\Illuminate\Support\Facades\DB::table('committed.artwork_categories')
        ->where('id', $gallery->getKey())->value('edge_navigation_mode'))->toBe('disabled');
    expect(\Illuminate\Support\Facades\DB::table('committed.artwork_categories')
        ->where('id', $gallery->getKey())->value('artwork_divider'))->toBe('none');

    expect(app(PublicationService::class)->commit(auth()->user()))->not->toBeNull();
    $committed = \Illuminate\Support\Facades\DB::table('committed.artwork_categories')
        ->where('id', $gallery->getKey())
        ->first(['landscape_detail_enabled', 'portrait_detail_enabled', 'edge_navigation_mode', 'artwork_divider']);
    expect((bool) $committed->landscape_detail_enabled)->toBeTrue()
        ->and((bool) $committed->portrait_detail_enabled)->toBeTrue()
        ->and($committed->edge_navigation_mode)->toBe('cyclic')
        ->and($committed->artwork_divider)->toBe('dotted');
});

it('validates authored Gallery rights and Artwork caption display using the central settings policy', function (): void {
    $gallery = galleryFixtureCategory();
    $service = app(\App\Domain\Artwork\GalleryEditorialService::class);

    expect(\App\Domain\Artwork\GalleryPresentation::showArtworkInformation($gallery))->toBeTrue()
        ->and(\App\Domain\Artwork\GalleryPresentation::showMediaCredits($gallery))->toBeFalse()
        ->and(\App\Domain\Artwork\GalleryPresentation::showMediaCopyright($gallery))->toBeFalse();

    $service->update($gallery, [
        'name' => $gallery->name,
        'show_artwork_information' => false,
        'show_media_credits' => true,
        'show_media_copyright' => true,
        'caption_layout' => 'rights_right',
    ]);
    $saved = $gallery->fresh();
    expect(\App\Domain\Artwork\GalleryPresentation::showArtworkInformation($saved))->toBeFalse()
        ->and(\App\Domain\Artwork\GalleryPresentation::showMediaCredits($saved))->toBeTrue()
        ->and(\App\Domain\Artwork\GalleryPresentation::showMediaCopyright($saved))->toBeTrue()
        ->and(\App\Domain\Artwork\GalleryPresentation::captionLayout($saved))->toBe('rights_right');

    expect(fn () => $service->update($saved, ['name' => $saved->name, 'caption_layout' => 'unknown']))
        ->toThrow(\Illuminate\Validation\ValidationException::class);
});

it('rejects unsupported Gallery navigation and divider modes without mutation', function (): void {
    $gallery = galleryFixtureCategory();
    $service = app(\App\Domain\Artwork\GalleryEditorialService::class);
    $auditBefore = AuditEvent::query()->count();

    expect(fn () => $service->update($gallery, ['name' => $gallery->name,
        'edge_navigation_mode' => 'wrap-automatically',
    ]))->toThrow(\Illuminate\Validation\ValidationException::class);
    expect(fn () => $service->update($gallery, ['name' => $gallery->name,
        'artwork_divider' => 'double',
    ]))->toThrow(\Illuminate\Validation\ValidationException::class);

    expect($gallery->fresh()->edge_navigation_mode)->toBe('disabled')
        ->and($gallery->fresh()->artwork_divider)->toBe('none')
        ->and(AuditEvent::query()->count())->toBe($auditBefore);
});

