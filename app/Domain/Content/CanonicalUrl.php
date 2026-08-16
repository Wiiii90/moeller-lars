<?php

namespace App\Domain\Content;

use LogicException;

final class CanonicalUrl
{
    public function base(): string
    {
        $base = config('app.url');
        if (! is_string($base) || trim($base) === '') {
            throw new LogicException('APP_URL must define the canonical public base URL.');
        }

        $base = rtrim(trim($base), '/');
        $scheme = parse_url($base, PHP_URL_SCHEME);
        $host = parse_url($base, PHP_URL_HOST);
        if (! is_string($scheme) || ! is_string($host) || $host === '') {
            throw new LogicException('APP_URL must be an absolute canonical public URL.');
        }
        if (app()->environment('production') && strtolower($scheme) !== 'https') {
            throw new LogicException('Production APP_URL must use HTTPS.');
        }

        return $base;
    }

    public function forPath(string $path): string
    {
        if ($path === '' || $path[0] !== '/' || str_starts_with($path, '//')) {
            throw new LogicException('Canonical paths must be absolute application paths.');
        }

        return $this->base().($path === '/' ? '' : $path);
    }

    public function current(): string
    {
        return $this->forPath(request()->getPathInfo());
    }
}
