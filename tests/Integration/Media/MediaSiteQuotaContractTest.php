<?php

use App\Domain\Media\MediaCapacityService;
use App\Domain\Storage\SiteStorageDatabaseUsageService;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

it('allows an exact-fit site storage write and blocks the first byte beyond quota', function (): void {
    Storage::fake('media-site-quota-contract');
    config(['media.disk' => 'media-site-quota-contract']);
    Storage::disk('media-site-quota-contract')->put('originals/one.jpg', str_repeat('a', 40));

    $database = app(SiteStorageDatabaseUsageService::class)->snapshot();

    expect($database['measurement_available'])->toBeTrue();

    $databaseBytes = (int) $database['logical_bytes'];
    config()->set('media.quota_bytes', $databaseBytes + 100);

    $capacity = app(MediaCapacityService::class);
    $capacity->assertCanStoreOriginal(60);

    expect(fn () => $capacity->assertCanStoreOriginal(61))
        ->toThrow(ValidationException::class, 'site storage allowance is full');
});
