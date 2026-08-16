<?php

namespace App\Domain\Content;

final class SafeLinkPolicy
{
    /** @var list<string> */
    private const ALLOWED_SCHEMES = ['http', 'https', 'mailto'];

    public function isAllowed(string $url): bool
    {
        return $this->sanitize($url) !== null;
    }

    public function allows(string $url): bool
    {
        return $this->isAllowed($url);
    }

    public function sanitize(string $url): ?string
    {
        $url = trim($url);

        if ($url === '' || preg_match('/[\x00-\x1F\x7F]/', $url) === 1 || str_contains($url, '\\')) {
            return null;
        }

        if (str_starts_with($url, '//')) {
            return null;
        }

        $parts = parse_url($url);
        if ($parts === false) {
            return null;
        }

        $scheme = strtolower($parts['scheme'] ?? '');
        if ($scheme === '') {
            return str_starts_with($url, '/')
                ? $url
                : ($url[0] === '#' ? $url : null);
        }

        if (! in_array($scheme, self::ALLOWED_SCHEMES, true)) {
            return null;
        }

        if (in_array($scheme, ['http', 'https'], true) && empty($parts['host'])) {
            return null;
        }

        if ($scheme === 'mailto' && (! isset($parts['path']) || filter_var($parts['path'], FILTER_VALIDATE_EMAIL) === false)) {
            return null;
        }

        return $url;
    }
}
