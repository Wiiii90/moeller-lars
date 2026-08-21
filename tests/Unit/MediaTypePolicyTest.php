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

it('classifies supported media without assuming every asset is an image', function () {
    expect(MediaTypePolicy::kind('image/webp'))->toBe('image')
        ->and(MediaTypePolicy::kind('video/webm'))->toBe('video')
        ->and(MediaTypePolicy::kind('application/pdf'))->toBe('other');
});
