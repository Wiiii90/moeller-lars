<?php

use App\Domain\Content\SafeLinkPolicy;

it('allows explicitly approved link forms', function (string $url): void {
    expect((new SafeLinkPolicy)->isAllowed($url))->toBeTrue();
})->with([
    'https://example.test/path?q=1',
    'http://example.test',
    'mailto:artist@example.test',
    '/artworks/example',
    '#biography',
]);

it('rejects unsafe and malformed link forms', function (string $url): void {
    expect((new SafeLinkPolicy)->isAllowed($url))->toBeFalse();
})->with([
    'javascript:alert(1)',
    'data:text/html,<script>alert(1)</script>',
    'file:///etc/passwd',
    'vbscript:msgbox(1)',
    '//example.test/path',
    'https://',
    "https://example.test/\njavascript:alert(1)",
]);
