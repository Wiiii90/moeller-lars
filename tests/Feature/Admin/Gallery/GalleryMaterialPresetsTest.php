<?php

use App\Domain\Artwork\ArtworkMaterialPresetService;
use App\Filament\Support\AdminActivityFeed;
use App\Models\Artwork;
use App\Models\ArtworkMaterialPreset;
use App\Models\AuditEvent;
use App\Models\User;

beforeEach(function (): void {
    $this->actingAs(User::factory()->admin()->create(), 'web');
    \Filament\Facades\Filament::setCurrentPanel('admin');
    \Filament\Facades\Filament::bootCurrentPanel();
});

require_once __DIR__.'/GalleryFixtures.php';

it('stores reusable Material presets without rewriting historical Artwork material text', function (): void {
    $artwork = galleryFixtureArtwork(galleryFixtureCategory(), ['medium' => 'Oil on linen']);

    expect(app(ArtworkMaterialPresetService::class)->sync(['Oil on linen', 'Graphite']))->toBeTrue()
        ->and(app(ArtworkMaterialPresetService::class)->sync(['Oil on linen', 'Graphite']))->toBeFalse();
    expect(ArtworkMaterialPreset::query()->orderBy('name')->pluck('name')->all())
        ->toBe(['Graphite', 'Oil on linen']);

    expect(app(ArtworkMaterialPresetService::class)->sync(['Graphite']))->toBeTrue()
        ->and(app(ArtworkMaterialPresetService::class)->sync(['Graphite']))->toBeFalse();

    expect(ArtworkMaterialPreset::query()->pluck('name')->all())->toBe(['Graphite'])
        ->and($artwork->fresh()->medium)->toBe('Oil on linen');
});


it('records Material preset mutations precisely and keeps no-op preset writes silent', function (): void {
    $service = app(ArtworkMaterialPresetService::class);
    $eventsBefore = AuditEvent::query()->count();

    expect($service->add('Tempera'))->toBeInstanceOf(ArtworkMaterialPreset::class);
    $created = AuditEvent::query()->latest('id')->firstOrFail();
    $afterCreate = AuditEvent::query()->count();

    expect($created->getAttribute('action'))->toBe('artwork_material_preset.created')
        ->and($created->getAttribute('entity_type'))->toBe('artwork_material_preset')
        ->and($created->getAttribute('metadata')['target_label'] ?? null)->toBe('Tempera')
        ->and($afterCreate)->toBe($eventsBefore + 1);

    expect((int) $service->add('Tempera')->getKey())->toBe((int) $created->getAttribute('entity_id'))
        ->and(AuditEvent::query()->count())->toBe($afterCreate);

    expect($service->sync(['TEMPERA', 'Graphite']))->toBeTrue();
    $renamed = AuditEvent::query()->where('action', 'artwork_material_preset.renamed')->latest('id')->firstOrFail();
    $createdGraphite = AuditEvent::query()
        ->where('action', 'artwork_material_preset.created')
        ->where('id', '>', $created->getKey())
        ->latest('id')
        ->firstOrFail();
    $afterSync = AuditEvent::query()->count();

    $renamedActivity = app(AdminActivityFeed::class)->event((int) $renamed->getKey(), auth('web')->user());

    expect($renamed->getAttribute('metadata')['target_label'] ?? null)->toBe('TEMPERA')
        ->and($renamedActivity['action_key'])->toBe('artwork_material_preset.renamed')
        ->and(collect($renamedActivity['change_summary']['items'])->firstWhere('field', 'name'))
        ->toMatchArray(['before' => 'Tempera', 'after' => 'TEMPERA'])
        ->and($renamedActivity['details'])->toContain('Tempera', 'TEMPERA')
        ->and($renamedActivity['target'] ?? null)->toBe('TEMPERA')
        ->and($createdGraphite->getAttribute('metadata')['target_label'] ?? null)->toBe('Graphite');

    expect($service->sync(['TEMPERA', 'Graphite']))->toBeFalse()
        ->and(AuditEvent::query()->count())->toBe($afterSync);

    expect($service->sync(['Graphite']))->toBeTrue();
    $deleted = AuditEvent::query()->where('action', 'artwork_material_preset.deleted')->latest('id')->firstOrFail();
    $deletedActivity = app(AdminActivityFeed::class)->event((int) $deleted->getKey(), auth('web')->user());

    expect($deleted->getAttribute('metadata')['target_label'] ?? null)->toBe('TEMPERA')
        ->and($deletedActivity['target'] ?? null)->toBe('TEMPERA')
        ->and($deletedActivity['action_key'])->toBe('artwork_material_preset.deleted')
        ->and($deletedActivity['details'])->toContain('TEMPERA')
        ->and($deletedActivity['url'] ?? null)->toBeNull();
});
