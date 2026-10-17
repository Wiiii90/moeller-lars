<?php

use App\Domain\Media\MediaRightsWorkingPreparation;
use App\Models\MediaRightsArtifact;

it('refuses offline image preparation unless separately enabled', function (): void {
    $service = app(MediaRightsWorkingPreparation::class);

    config()->set('media.rights_preparation_enabled', false);
    config()->set('media.rights_delivery_enabled', false);
    expect(fn () => $service->prepare(101))
        ->toThrow(RuntimeException::class, 'authorized separately');

    config()->set('media.rights_preparation_enabled', true);
    config()->set('media.rights_delivery_enabled', true);
    expect(fn () => $service->prepare(101))
        ->toThrow(RuntimeException::class, 'authorized separately');

    expect(MediaRightsArtifact::query()->count())->toBe(0);
});

it('does not create files or selections for an ineligible Working image', function (): void {
    config()->set('media.rights_preparation_enabled', true);
    config()->set('media.rights_delivery_enabled', false);

    expect(app(MediaRightsWorkingPreparation::class)->prepare(999999999))->toBeFalse()
        ->and(MediaRightsArtifact::query()->count())->toBe(0);
    expect(\Illuminate\Support\Facades\DB::table('media_rights_artifact_selections')->count())->toBe(0);
});
