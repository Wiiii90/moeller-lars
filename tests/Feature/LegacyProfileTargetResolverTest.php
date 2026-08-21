<?php

use App\Domain\Migration\LegacyProfileTargetResolver;
use App\Domain\Migration\LegacyPublicCvImporter;
use App\Models\CvEntry;

it('uses an explicit legacy id when the manifest provides one', function (): void {
    $first = CvEntry::query()->create([
        'section' => 'Biography',
        'title' => 'First',
        'state' => 'draft',
        'position' => 0,
        'legacy_id' => 41,
        'legacy_source' => LegacyPublicCvImporter::SOURCE,
        'migration_batch_id' => LegacyPublicCvImporter::BATCH,
    ]);
    $explicit = CvEntry::query()->create([
        'section' => 'Biography',
        'title' => 'Explicit',
        'state' => 'draft',
        'position' => 10,
        'legacy_id' => 73,
        'legacy_source' => LegacyPublicCvImporter::SOURCE,
        'migration_batch_id' => LegacyPublicCvImporter::BATCH,
    ]);

    $resolved = app(LegacyProfileTargetResolver::class)->resolve(LegacyPublicCvImporter::SOURCE, 73);

    expect($resolved?->is($explicit))->toBeTrue()
        ->and($resolved?->is($first))->toBeFalse();
});

it('uses canonical vita order for historical manifests without a target id', function (): void {
    $later = CvEntry::query()->create([
        'section' => 'Biography',
        'title' => 'Later',
        'state' => 'draft',
        'position' => 20,
        'legacy_id' => 91,
        'legacy_source' => LegacyPublicCvImporter::SOURCE,
        'migration_batch_id' => LegacyPublicCvImporter::BATCH,
    ]);
    $first = CvEntry::query()->create([
        'section' => 'Biography',
        'title' => 'First',
        'state' => 'draft',
        'position' => 0,
        'legacy_id' => 52,
        'legacy_source' => LegacyPublicCvImporter::SOURCE,
        'migration_batch_id' => LegacyPublicCvImporter::BATCH,
    ]);

    $resolved = app(LegacyProfileTargetResolver::class)->resolve(LegacyPublicCvImporter::SOURCE, null);

    expect($resolved?->is($first))->toBeTrue()
        ->and($resolved?->is($later))->toBeFalse();
});
