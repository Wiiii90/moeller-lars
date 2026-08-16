<?php

namespace App\Domain\Content;

final class SafeLinkPolicy
{
    public function isAllowed(string $url): bool
    {
        if ($url === '' || trim($url) !== $url) {
            return false;
        }

        if (preg_match('/[\x00-\x20\x7F]/', $url) === 1 || str_contains($url, '\\')) {
            return false;
        }

        $parts = parse_url($url);
        if ($parts === false || ! isset($parts['scheme'])) {
            return false;
        }

        $scheme = strtolower($parts['scheme']);
        if (! in_array($scheme, ['http', 'https', 'mailto'], true)) {
            return false;
        }

        if ($scheme === 'mailto') {
            return isset($parts['path'])
                && $parts['path'] !== ''
                && ! array_key_exists('host', $parts)
                && ! array_key_exists('user', $parts)
                && ! array_key_exists('pass', $parts)
                && ! array_key_exists('query', $parts)
                && ! array_key_exists('fragment', $parts)
                && filter_var($parts['path'], FILTER_VALIDATE_EMAIL) !== false;
        }

        return filter_var($url, FILTER_VALIDATE_URL) !== false
            && ! empty($parts['host'])
            && ! array_key_exists('user', $parts)
            && ! array_key_exists('pass', $parts);
    }
}
