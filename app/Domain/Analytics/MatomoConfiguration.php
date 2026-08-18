<?php

namespace App\Domain\Analytics;

use LogicException;

final class MatomoConfiguration
{
    /** @return array{base_url:string,site_id:int}|null */
    public function browserTracking(): ?array
    {
        if (! (bool) config('analytics.matomo.enabled')) {
            return null;
        }

        return [
            'base_url' => $this->baseUrl(),
            'site_id' => $this->siteId(),
        ];
    }

    public function baseUrl(): string
    {
        $value = config('analytics.matomo.base_url');
        if (! is_string($value) || trim($value) === '') {
            throw new LogicException('MATOMO_BASE_URL is required when Matomo is enabled.');
        }

        $url = rtrim(trim($value), '/');
        $scheme = strtolower((string) parse_url($url, PHP_URL_SCHEME));
        $host = parse_url($url, PHP_URL_HOST);
        if (! in_array($scheme, ['http', 'https'], true) || ! is_string($host) || $host === '') {
            throw new LogicException('MATOMO_BASE_URL must be an absolute HTTP(S) URL.');
        }
        if (app()->environment('production') && $scheme !== 'https') {
            throw new LogicException('Production MATOMO_BASE_URL must use HTTPS.');
        }

        return $url;
    }

    public function siteId(): int
    {
        $value = config('analytics.matomo.site_id');
        if (filter_var($value, FILTER_VALIDATE_INT) === false || (int) $value < 1) {
            throw new LogicException('MATOMO_SITE_ID must be a positive integer when Matomo is enabled.');
        }

        return (int) $value;
    }

    public function apiToken(): string
    {
        $value = config('analytics.matomo.api_token');
        if (! is_string($value) || trim($value) === '') {
            throw new LogicException('MATOMO_API_TOKEN is required for the analytics reporting dashboard.');
        }

        return trim($value);
    }

    public function timeoutSeconds(): int
    {
        $value = (int) config('analytics.matomo.report_timeout_seconds');
        if ($value < 1 || $value > 30) {
            throw new LogicException('MATOMO_REPORT_TIMEOUT_SECONDS must be between 1 and 30.');
        }

        return $value;
    }

    public function reportCacheSeconds(): int
    {
        $value = (int) config('analytics.matomo.report_cache_seconds');
        if ($value < 1 || $value > 3600) {
            throw new LogicException('MATOMO_REPORT_CACHE_SECONDS must be between 1 and 3600.');
        }

        return $value;
    }

    public function reportStaleSeconds(): int
    {
        $value = (int) config('analytics.matomo.report_stale_seconds');
        if ($value < 60 || $value > 604800) {
            throw new LogicException('MATOMO_REPORT_STALE_SECONDS must be between 60 and 604800.');
        }

        return $value;
    }
}
