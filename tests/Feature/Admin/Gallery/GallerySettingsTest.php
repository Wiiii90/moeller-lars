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

    $component = Livewire::test(GalleryWorkspace::class, ['gallery' => $gallery->getKey()])
        ->mountAction('gallerySettings')
        ->fillForm([
            'name' => 'Must not persist',
            'slug' => $otherGallery->slug,
            'description' => 'Must roll back too',
            'show_on_home' => true,
        ])
        ->callMountedAction()
        ->assertHasFormErrors(['slug'])
        ->assertActionMounted('gallerySettings')
        ->assertNotDispatched('admin-header-notification');

    $fresh = ArtworkCategory::query()->findOrFail($gallery->getKey());
    expect($fresh->name)->toBe($previousName)
        ->and($fresh->slug)->toBe($previousSlug)
        ->and($fresh->description)->toBeNull()
        ->and((bool) $fresh->show_on_home)->toBeFalse()
        ->and($fresh->siteSection()->firstOrFail()->title)->toBe($previousName)
        ->and(AuditEvent::query()->count())->toBe($auditsBefore)
        ->and(AdminNotification::query()->count())->toBe($notificationsBefore);

    $component->fillForm([
        'name' => 'Recovered Gallery title',
        'slug' => $previousSlug,
        'description' => null,
        'show_on_home' => false,
    ])->callMountedAction()
        ->assertHasNoFormErrors()
        ->assertDispatched('admin-header-notification');

    expect(collect($component->effects['dispatches'] ?? [])->where('name', 'admin-header-notification')->count())->toBe(1)
        ->and($gallery->fresh()->name)->toBe('Recovered Gallery title')
        ->and(AuditEvent::query()->count())->toBe($auditsBefore + 1)
        ->and(AdminNotification::query()->count())->toBe($notificationsBefore + 1);

    $component->callMountedAction()->assertNotDispatched('admin-header-notification');
    expect(AuditEvent::query()->count())->toBe($auditsBefore + 1)
        ->and(AdminNotification::query()->count())->toBe($notificationsBefore + 1);
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


it('commits the Materials repeater as one explicit Command, not as Edit autosave', function (): void {
    $gallery = galleryFixtureCategory();
    galleryFixtureUnavailableAnalytics();
    $editor = Livewire::test(GalleryWorkspace::class, ['gallery' => $gallery->getKey()])
        ->mountAction('materialPresets')
        ->assertMountedActionModalSee('Material presets')
        ->fillForm(['presets' => [['name' => 'Bronze'], ['name' => 'Ink']]]);
    app(\App\Domain\Admin\AdminActivityNotificationContext::class)->discard();
    $auditsBefore = AuditEvent::query()->count();
    $notificationsBefore = AdminNotification::query()->count();

    // The draft Repeater has not committed while the dialog is open.
    expect(\App\Models\ArtworkMaterialPreset::query()->whereIn('name', ['Bronze', 'Ink'])->count())->toBe(0);

    $editor->callMountedAction()->assertHasNoFormErrors()->assertDispatched('admin-header-notification');

    expect(\App\Models\ArtworkMaterialPreset::query()->orderBy('name')->pluck('name')->all())->toBe(['Bronze', 'Ink'])
        ->and(AuditEvent::query()->where('action', 'artwork_material_preset.created')->count())->toBe(2)
        ->and(AuditEvent::query()->count())->toBe($auditsBefore + 2)
        ->and(AdminNotification::query()->count())->toBe($notificationsBefore + 1)
        ->and(collect($editor->effects['dispatches'] ?? [])->where('name', 'admin-header-notification')->count())->toBe(1);

    $editor->mountAction('materialPresets')->callMountedAction()
        ->assertHasNoFormErrors()->assertNotDispatched('admin-header-notification');
    expect(AuditEvent::query()->count())->toBe($auditsBefore + 2)
        ->and(AdminNotification::query()->count())->toBe($notificationsBefore + 1);
});

it('rolls back the complete Materials command on audit failure and permits a clean retry', function (): void {
    $gallery = galleryFixtureCategory();
    galleryFixtureUnavailableAnalytics();
    $editor = Livewire::test(GalleryWorkspace::class, ['gallery' => $gallery->getKey()])
        ->mountAction('materialPresets')
        ->fillForm(['presets' => [['name' => 'Bronze'], ['name' => 'Ink']]]);
    app(\App\Domain\Admin\AdminActivityNotificationContext::class)->discard();
    $auditsBefore = AuditEvent::query()->count();
    $notificationsBefore = AdminNotification::query()->count();
    $realAudit = app(\App\Domain\Admin\AdminAuditService::class);
    $calls = 0;
    $audit = Mockery::mock($realAudit);
    $audit->shouldReceive('record')->twice()->andReturnUsing(function (...$parameters) use ($realAudit, &$calls) {
        if (++$calls === 2) {
            throw new RuntimeException('Materials audit unavailable');
        }
        return $realAudit->record(...$parameters);
    });
    app()->instance(\App\Domain\Admin\AdminAuditService::class, $audit);
    try {
        expect(fn () => $editor->callMountedAction())->toThrow(RuntimeException::class, 'Materials audit unavailable');
        expect(\App\Models\ArtworkMaterialPreset::query()->count())->toBe(0)
            ->and(AuditEvent::query()->count())->toBe($auditsBefore)
            ->and(AdminNotification::query()->count())->toBe($notificationsBefore)
            ->and(collect($editor->effects['dispatches'] ?? [])->where('name', 'admin-header-notification')->count())->toBe(0);
    } finally {
        app()->instance(\App\Domain\Admin\AdminAuditService::class, $realAudit);
    }
    $editor->callMountedAction()->assertHasNoFormErrors();
    expect(\App\Models\ArtworkMaterialPreset::query()->orderBy('name')->pluck('name')->all())->toBe(['Bronze', 'Ink'])
        ->and(AuditEvent::query()->count())->toBe($auditsBefore + 2)
        ->and(AdminNotification::query()->count())->toBe($notificationsBefore + 1)
        ->and(collect($editor->effects['dispatches'] ?? [])->where('name', 'admin-header-notification')->count())->toBe(1);
});

it('protects a mounted Gallery Materials dialog after administrative access is lost', function (bool $guest): void {
    $actor = auth('web')->user();
    $gallery = galleryFixtureCategory();
    galleryFixtureUnavailableAnalytics();
    $editor = Livewire::test(GalleryWorkspace::class, ['gallery' => $gallery->getKey()])
        ->mountAction('materialPresets')
        ->fillForm(['presets' => [['name' => 'Bronze']]]);
    if ($guest) {
        auth('web')->logout();
    } else {
        $actor->forceFill(['is_admin' => false])->save();
    }
    $auditsBefore = AuditEvent::query()->count();
    $notificationsBefore = AdminNotification::query()->count();
    $editor->set('mountedActions.0.data.presets.0.name', 'Rejected material')->assertForbidden();
    expect(\App\Models\ArtworkMaterialPreset::query()->count())->toBe(0)
        ->and(AuditEvent::query()->count())->toBe($auditsBefore)
        ->and(AdminNotification::query()->count())->toBe($notificationsBefore);
})->with(['guest' => true, 'revoked administrator' => false]);

it('persists per-Gallery landscape and edge settings through one audited action', function (): void {
    $gallery = galleryFixtureCategory();
    galleryFixtureUnavailableAnalytics();
    $auditBefore = AuditEvent::query()->count();
    $notificationBefore = AdminNotification::query()->count();

    expect((bool) $gallery->getAttribute('landscape_detail_enabled'))->toBeFalse()
        ->and((int) $gallery->getAttribute('landscape_detail_scale'))->toBe(150)
        ->and($gallery->getAttribute('edge_navigation_mode'))->toBe('disabled');

    $form = [
        'name' => $gallery->name,
        'slug' => $gallery->slug,
        'description' => null,
        'show_on_home' => false,
        'landscape_detail_enabled' => true,
        'landscape_detail_scale' => 200,
        'edge_navigation_mode' => 'cyclic',
    ];

    Livewire::test(GalleryWorkspace::class, ['gallery' => $gallery->getKey()])
        ->mountAction('gallerySettings')
        ->fillForm($form)
        ->callMountedAction()
        ->assertHasNoFormErrors()
        ->assertDispatched('admin-header-notification');

    $saved = $gallery->fresh();
    expect((bool) $saved->landscape_detail_enabled)->toBeTrue()
        ->and($saved->landscape_detail_scale)->toBe(200)
        ->and($saved->edge_navigation_mode)->toBe('cyclic')
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

    expect(app(PublicationService::class)->commit(auth()->user()))->not->toBeNull();
    $committed = \Illuminate\Support\Facades\DB::table('committed.artwork_categories')
        ->where('id', $gallery->getKey())
        ->first(['landscape_detail_enabled', 'landscape_detail_scale', 'edge_navigation_mode']);
    expect((bool) $committed->landscape_detail_enabled)->toBeTrue()
        ->and((int) $committed->landscape_detail_scale)->toBe(200)
        ->and($committed->edge_navigation_mode)->toBe('cyclic');
});

it('rejects unsupported Gallery detail scales and navigation modes without mutation', function (): void {
    $gallery = galleryFixtureCategory();
    $service = app(\App\Domain\Artwork\GalleryEditorialService::class);
    $auditBefore = AuditEvent::query()->count();

    expect(fn () => $service->update($gallery, ['name' => $gallery->name,
        'landscape_detail_scale' => 180,
    ]))->toThrow(\Illuminate\Validation\ValidationException::class);
    expect(fn () => $service->update($gallery, ['name' => $gallery->name,
        'edge_navigation_mode' => 'wrap-automatically',
    ]))->toThrow(\Illuminate\Validation\ValidationException::class);

    expect($gallery->fresh()->edge_navigation_mode)->toBe('disabled')
        ->and(AuditEvent::query()->count())->toBe($auditBefore);
});

