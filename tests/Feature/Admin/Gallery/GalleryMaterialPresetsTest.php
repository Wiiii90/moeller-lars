<?php

use App\Domain\Admin\AdminAuditService;
use App\Domain\Artwork\ArtworkMaterialPresetService;
use App\Filament\Pages\GalleryWorkspace;
use App\Filament\Support\AdminActivityFeed;
use App\Models\AdminNotification;
use App\Models\ArtworkMaterialPreset;
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

it('manages Material presets without rewriting historical Artwork material text', function (): void {
    $artwork = galleryFixtureArtwork(galleryFixtureCategory(), ['medium' => 'Oil on linen']);
    $service = app(ArtworkMaterialPresetService::class);
    $before = AuditEvent::query()->count();

    expect($service->add('Oil on linen'))->toBeTrue()
        ->and($service->add('Graphite'))->toBeTrue();
    $oil = ArtworkMaterialPreset::query()->where('name', 'Oil on linen')->firstOrFail();
    $created = AuditEvent::query()->where('action', 'artwork_material_preset.created')
        ->where('entity_id', $oil->getKey())->firstOrFail();
    expect($created->metadata['target_label'] ?? null)->toBe('Oil on linen');
    expect($service->rename((int) $oil->getKey(), 'Tempera'))->toBeTrue()
        ->and($service->rename((int) $oil->getKey(), 'Tempera'))->toBeFalse();
    $renamed = AuditEvent::query()->where('action', 'artwork_material_preset.renamed')->firstOrFail();
    $renamedActivity = app(AdminActivityFeed::class)->event((int) $renamed->getKey(), auth('web')->user());
    expect($renamed->metadata['target_label'] ?? null)->toBe('Tempera')
        ->and($renamedActivity['target'] ?? null)->toBe('Tempera')
        ->and($renamedActivity['details'])->toContain('Oil on linen', 'Tempera');
    expect($service->delete((int) $oil->getKey()))->toBeTrue()
        ->and($service->delete((int) $oil->getKey()))->toBeFalse();
    $deleted = AuditEvent::query()->where('action', 'artwork_material_preset.deleted')->firstOrFail();
    $deletedActivity = app(AdminActivityFeed::class)->event((int) $deleted->getKey(), auth('web')->user());
    expect($deleted->metadata['target_label'] ?? null)->toBe('Tempera')
        ->and($deletedActivity['target'] ?? null)->toBe('Tempera')
        ->and($deletedActivity['url'] ?? null)->toBeNull();

    expect($service->ordered()->pluck('name')->all())->toBe(['Graphite'])
        ->and($artwork->fresh()->medium)->toBe('Oil on linen')
        ->and(AuditEvent::query()->count())->toBe($before + 4)
        ->and(AuditEvent::query()->where('action', 'artwork_material_preset.created')->count())->toBe(2)
        ->and(AuditEvent::query()->where('action', 'artwork_material_preset.renamed')->count())->toBe(1)
        ->and(AuditEvent::query()->where('action', 'artwork_material_preset.deleted')->count())->toBe(1);
});

it('notifies once for each Material change and remains silent for no-ops', function (): void {
    $gallery = galleryFixtureCategory();
    galleryFixtureUnavailableAnalytics();
    $before = AdminNotification::query()->count();
    $workspace = Livewire::test(GalleryWorkspace::class, ['gallery' => $gallery->getKey()])
        ->mountAction('materialPresets')
        ->assertMountedActionModalSee('Material presets')
        ->call('addMaterialPreset', 'Bronze')
        ->assertDispatched('admin-header-notification');

    $preset = ArtworkMaterialPreset::query()->where('name', 'Bronze')->firstOrFail();
    expect(AdminNotification::query()->count())->toBe($before + 1);
    $workspace->call('renameMaterialPreset', (int) $preset->getKey(), 'Bronze')
        ->assertNotDispatched('admin-header-notification');
    expect(AdminNotification::query()->count())->toBe($before + 1);
    $workspace->call('renameMaterialPreset', (int) $preset->getKey(), 'Ink')
        ->assertDispatched('admin-header-notification');
    expect($preset->fresh()->name)->toBe('Ink')
        ->and(AdminNotification::query()->count())->toBe($before + 2);
    $workspace->call('deleteMaterialPreset', (int) $preset->getKey())
        ->assertDispatched('admin-header-notification');
    expect(ArtworkMaterialPreset::query()->whereKey($preset->getKey())->exists())->toBeFalse()
        ->and(AdminNotification::query()->count())->toBe($before + 3);
});

it('rolls back a Material change when auditing fails and permits a clean retry', function (): void {
    $gallery = galleryFixtureCategory();
    galleryFixtureUnavailableAnalytics();
    $editor = Livewire::test(GalleryWorkspace::class, ['gallery' => $gallery->getKey()])
        ->mountAction('materialPresets');
    $audit = app(AdminAuditService::class);
    $failing = Mockery::mock($audit);
    $failing->shouldReceive('record')->once()->andThrow(new RuntimeException('Material audit unavailable'));
    app()->instance(AdminAuditService::class, $failing);
    $before = AdminNotification::query()->count();
    $auditsBefore = AuditEvent::query()->count();
    try {
        expect(fn () => $editor->call('addMaterialPreset', 'Bronze'))
            ->toThrow(RuntimeException::class, 'Material audit unavailable');
        expect(ArtworkMaterialPreset::query()->where('name', 'Bronze')->exists())->toBeFalse()
            ->and(AdminNotification::query()->count())->toBe($before)
            ->and(AuditEvent::query()->count())->toBe($auditsBefore);
    } finally {
        app()->instance(AdminAuditService::class, $audit);
    }
    $editor->call('addMaterialPreset', 'Bronze')->assertDispatched('admin-header-notification');
    expect(ArtworkMaterialPreset::query()->where('name', 'Bronze')->exists())->toBeTrue()
        ->and(AdminNotification::query()->count())->toBe($before + 1)
        ->and(AuditEvent::query()->count())->toBe($auditsBefore + 1);
});

it('rejects Material mutations after administrative access is lost', function (bool $guest): void {
    $actor = auth('web')->user();
    $gallery = galleryFixtureCategory();
    galleryFixtureUnavailableAnalytics();
    $editor = Livewire::test(GalleryWorkspace::class, ['gallery' => $gallery->getKey()])
        ->mountAction('materialPresets');
    if ($guest) {
        auth('web')->logout();
    } else {
        $actor->forceFill(['is_admin' => false])->save();
    }
    $audits = AuditEvent::query()->count();
    $notifications = AdminNotification::query()->count();
    $editor->call('addMaterialPreset', 'Rejected material')->assertForbidden();
    expect(ArtworkMaterialPreset::query()->where('name', 'Rejected material')->exists())->toBeFalse()
        ->and(AuditEvent::query()->count())->toBe($audits)
        ->and(AdminNotification::query()->count())->toBe($notifications);
})->with(['guest' => true, 'revoked administrator' => false]);
