<?php

use App\Domain\Media\MediaTypePolicy;

it('defines an explicit browser media allowlist', function () {
    expect(MediaTypePolicy::acceptedMimeTypes())->toBe([
        'image/jpeg',
        'image/png',
        'image/webp',
        'video/mp4',
        'video/webm',
    ])->and(MediaTypePolicy::extensionFor('application/octet-stream'))->toBeNull();
});

it('uses operator configured type-specific upload limits', function () {
    config()->set('media.upload.image_max_bytes', 12345);
    config()->set('media.upload.video_max_bytes', 67890);

    expect(MediaTypePolicy::maxBytesFor('image/jpeg'))->toBe(12345)
        ->and(MediaTypePolicy::maxBytesFor('video/mp4'))->toBe(67890)
        ->and(MediaTypePolicy::maxUploadBytes())->toBe(67890);
});

it('classifies supported media without assuming every asset is an image', function () {
    expect(MediaTypePolicy::kind('image/webp'))->toBe('image')
        ->and(MediaTypePolicy::kind('video/webm'))->toBe('video')
        ->and(MediaTypePolicy::kind('application/pdf'))->toBe('other');
});
