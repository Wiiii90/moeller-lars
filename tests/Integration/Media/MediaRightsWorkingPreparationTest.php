<?php

use App\Domain\Media\MediaRightsWorkingPreparation;
use App\Jobs\PrepareMediaRightsBatch;
use App\Jobs\PrepareMediaRightsImage;
use App\Models\MediaAsset;
use Illuminate\Support\Facades\Queue;
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

it('queues only available image rights in resumable bounded batches after explicit authorization', function (): void {
    Queue::fake();
    config()->set('media.rights_preparation_enabled', false);

    (new PrepareMediaRightsBatch)->handle();
    Queue::assertNothingPushed();

    $ids = [];
    foreach (range(1, 21) as $index) {
        $asset = MediaAsset::query()->create([
            'storage_key' => 'originals/rights-queue-'.$index.'.jpg',
            'original_filename' => 'rights-queue-'.$index.'.jpg',
            'mime_type' => 'image/jpeg',
            'byte_size' => 16,
            'sha256' => hash('sha256', 'image-'.$index),
            'state' => 'available',
        ]);
        $ids[] = (int) $asset->getKey();
    }

    MediaAsset::query()->create([
        'storage_key' => 'originals/rights-queue-video.mp4',
        'original_filename' => 'rights-queue-video.mp4',
        'mime_type' => 'video/mp4',
        'byte_size' => 16,
        'sha256' => hash('sha256', 'queue-video'),
        'state' => 'available',
    ]);

    config()->set('media.rights_preparation_enabled', true);
    (new PrepareMediaRightsBatch)->handle();

    Queue::assertPushed(PrepareMediaRightsImage::class, 20);
    Queue::assertPushed(PrepareMediaRightsImage::class,
        fn (PrepareMediaRightsImage $job): bool => $job->mediaAssetId === $ids[0]);
    Queue::assertPushed(PrepareMediaRightsBatch::class, 1);
    Queue::assertPushed(PrepareMediaRightsBatch::class,
        fn (PrepareMediaRightsBatch $job): bool => $job->afterId === $ids[19]);

    (new PrepareMediaRightsBatch($ids[19]))->handle();
    Queue::assertPushed(PrepareMediaRightsImage::class, 21);
    Queue::assertPushed(PrepareMediaRightsImage::class,
        fn (PrepareMediaRightsImage $job): bool => $job->mediaAssetId === $ids[20]);
    Queue::assertPushed(PrepareMediaRightsBatch::class, 1);
    expect(MediaRightsArtifact::query()->count())->toBe(0);
});
