<?php

use App\Domain\Content\SafeLinkPolicy;

it('allows only the approved absolute link schemes', function (string $url): void {
    expect((new SafeLinkPolicy)->isAllowed($url))->toBeTrue();
})->with([
    'https://example.com',
    'http://example.com',
    'https://example.com/path?q=1#part',
    'mailto:lars@example.com',
]);

it('rejects disallowed, relative, malformed, and credential-bearing links', function (string $url): void {
    expect((new SafeLinkPolicy)->isAllowed($url))->toBeFalse();
})->with([
    '',
    ' https://example.com',
    'https://example.com ',
    'https://example.com/a b',
    "https://example.com/a\n b",
    'https://example.com/a\\b',
    '//example.com',
    '/relative',
    'relative/path',
    '#fragment',
    'javascript:alert(1)',
    'JAVASCRIPT:alert(1)',
    'data:text/html,test',
    'file:///etc/passwd',
    'vbscript:msgbox(1)',
    'ftp://example.com',
    'tel:+49123',
    'custom:foo',
    'https://user@example.com',
    'https://user:pass@example.com',
    'https://',
    'mailto:',
    'mailto:not-an-email',
    'mailto:lars@example.com?subject=test',
    'mailto:lars@example.com#fragment',
]);
