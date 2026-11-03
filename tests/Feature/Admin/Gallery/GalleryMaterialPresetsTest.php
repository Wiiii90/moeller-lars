<?php

use App\Domain\Artwork\ArtworkMaterialPresetService;
use App\Filament\Support\AdminActivityFeed;
use App\Models\Artwork;
use App\Models\ArtworkMaterialPreset;
use App\Models\AuditEvent;
use App\Models\User;
use Illuminate\Validation\ValidationException;

beforeEach(function (): void {
    $this->actingAs(User::factory()->admin()->create(), 'web');
    \Filament\Facades\Filament::setCurrentPanel('admin');
    \Filament\Facades\Filament::bootCurrentPanel();
});

require_once __DIR__.'/GalleryFixtures.php';

it('keeps material presets ranked without rewriting historical Artwork text', function (): void {
    $artwork = galleryFixtureArtwork(galleryFixtureCategory(), ['medium' => 'Oil on linen']);
    $service = app(ArtworkMaterialPresetService::class);

    expect($service->add('Oil on linen'))->toBeTrue()
        ->and($service->add('Graphite'))->toBeTrue();
    $oil = ArtworkMaterialPreset::query()->where('name', 'Oil on linen')->firstOrFail();
    $graphite = ArtworkMaterialPreset::query()->where('name', 'Graphite')->firstOrFail();

    expect($service->ordered()->pluck('name')->all())->toBe(['Oil on linen', 'Graphite']);
    expect($service->rename((int) $oil->getKey(), 'Oil on canvas'))->toBeTrue();
    expect($service->move((int) $graphite->getKey(), 0))->toBeTrue();
    expect($service->ordered()->pluck('name')->all())->toBe(['Graphite', 'Oil on canvas']);
    expect($service->delete((int) $oil->getKey()))->toBeTrue();

    expect($service->ordered()->pluck('name')->all())->toBe(['Graphite'])
        ->and($artwork->fresh()->medium)->toBe('Oil on linen')
        ->and((int) $graphite->fresh()->position)->toBe(0);
});

it('audits material creation, rename, sorting and deletion while rejecting duplicate names', function (): void {
    $service = app(ArtworkMaterialPresetService::class);
    $eventsBefore = AuditEvent::query()->count();

    expect($service->add('Tempera'))->toBeTrue();
    $tempera = ArtworkMaterialPreset::query()->where('name', 'Tempera')->firstOrFail();
    $created = AuditEvent::query()->latest('id')->firstOrFail();
    expect($created->action)->toBe('artwork_material_preset.created')
        ->and($created->getAttribute('metadata')['target_label'] ?? null)->toBe('Tempera');

    expect($service->rename((int) $tempera->getKey(), 'TEMPERA'))->toBeTrue();
    $renamed = AuditEvent::query()->latest('id')->firstOrFail();
    expect($renamed->action)->toBe('artwork_material_preset.renamed');
    $renamedActivity = app(AdminActivityFeed::class)->event((int) $renamed->getKey(), auth('web')->user());
    expect($renamedActivity['action_key'])->toBe('artwork_material_preset.renamed')
        ->and(collect($renamedActivity['change_summary']['items'])->firstWhere('field', 'name'))
        ->toMatchArray(['before' => 'Tempera', 'after' => 'TEMPERA']);

    expect($service->add('Graphite'))->toBeTrue();
    $graphite = ArtworkMaterialPreset::query()->where('name', 'Graphite')->firstOrFail();
    expect($service->move((int) $graphite->getKey(), 0))->toBeTrue();
    expect(AuditEvent::query()->latest('id')->value('action'))->toBe('artwork_material_preset.reordered');

    $afterReorder = AuditEvent::query()->count();
    expect($service->move((int) $graphite->getKey(), 0))->toBeFalse()
        ->and($service->rename((int) $graphite->getKey(), 'Graphite'))->toBeFalse()
        ->and(AuditEvent::query()->count())->toBe($afterReorder);
    expect(fn () => $service->add('tempera'))->toThrow(ValidationException::class);
    expect(AuditEvent::query()->count())->toBe($afterReorder);

    expect($service->delete((int) $tempera->getKey()))->toBeTrue()
        ->and(AuditEvent::query()->latest('id')->value('action'))->toBe('artwork_material_preset.deleted')
        ->and(AuditEvent::query()->count())->toBe($eventsBefore + 5);
});
