<?php

namespace App\Domain\Content;

use Closure;
use Illuminate\Contracts\Cache\LockTimeoutException;
use Illuminate\Support\Facades\Cache;

final class NominatimRequestThrottle
{
    private const KEY_VERSION = 'v1';

    private Closure $clock;

    private Closure $sleep;

    public function __construct(?Closure $clock = null, ?Closure $sleep = null)
    {
        $this->clock = $clock ?? static fn (): int => (int) floor(microtime(true) * 1000);
        $this->sleep = $sleep ?? static function (int $milliseconds): void {
            usleep($milliseconds * 1000);
        };
    }

    public function run(string $endpoint, callable $request): mixed
    {
        try {
            return Cache::lock(self::lockKeyFor($endpoint), $this->lockSeconds())
                ->block($this->lockWaitSeconds(), function () use ($endpoint, $request): mixed {
                    $lastRequestAt = Cache::get(self::lastRequestKeyFor($endpoint));
                    if (is_numeric($lastRequestAt)) {
                        $elapsed = max(0, ($this->nowMilliseconds() - (int) $lastRequestAt));
                        $remaining = max(0, $this->minimumIntervalMilliseconds() - $elapsed);
                        if ($remaining > 0) {
                            ($this->sleep)($remaining);
                        }
                    }

                    try {
                        return $request();
                    } finally {
                        Cache::put(
                            self::lastRequestKeyFor($endpoint),
                            $this->nowMilliseconds(),
                            now()->addMinutes(10),
                        );
                    }
                });
        } catch (LockTimeoutException $exception) {
            throw new ExhibitionGeocodingUnavailable(
                'Nominatim request throttle is busy.',
                previous: $exception,
            );
        }
    }

    public static function lockKeyFor(string $endpoint): string
    {
        return 'journal:nominatim:network-lock:'.self::KEY_VERSION.':'.sha1(trim($endpoint));
    }

    public static function lastRequestKeyFor(string $endpoint): string
    {
        return 'journal:nominatim:last-request:'.self::KEY_VERSION.':'.sha1(trim($endpoint));
    }

    private function nowMilliseconds(): int
    {
        return (int) ($this->clock)();
    }

    private function minimumIntervalMilliseconds(): int
    {
        return max(1000, (int) config('services.nominatim.min_interval_ms', 1000));
    }

    private function lockSeconds(): int
    {
        return max(2, (int) config('services.nominatim.lock_seconds', 8));
    }

    private function lockWaitSeconds(): int
    {
        return max(0, (int) config('services.nominatim.lock_wait_seconds', 3));
    }
}
