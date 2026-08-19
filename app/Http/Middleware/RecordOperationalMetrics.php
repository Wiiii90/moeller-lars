<?php

namespace App\Http\Middleware;

use App\Domain\Analytics\OperationalMetricRecorder;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

final class RecordOperationalMetrics
{
    public function __construct(private readonly OperationalMetricRecorder $metrics) {}

    public function handle(Request $request, Closure $next): Response
    {
        $startedAt = hrtime(true);
        $isBot = $this->isBot($request->userAgent());
        $isAdmin = $request->is('admin', 'admin/*');
        $pending = [];

        try {
            $response = $next($request);
            $status = $response->getStatusCode();

            if ($status === 404) {
                $pending[] = $this->metric('error:http_404', 1.0, 'count');
            }
            if ($status >= 500) {
                $pending[] = $this->metric('error:http_5xx', 1.0, 'count');
            }
            if ($isAdmin) {
                $pending[] = $this->metric('operation:admin_request', 1.0, 'count');
            }

            return $response;
        } catch (Throwable $exception) {
            $pending[] = $this->metric('error:request_exception', 1.0, 'count');
            throw $exception;
        } finally {
            if (! $request->is('up')) {
                $durationMilliseconds = (hrtime(true) - $startedAt) / 1_000_000;
                $pending[] = $this->metric('performance:request_duration_ms', $durationMilliseconds, 'ms');
                if ($isAdmin) {
                    $pending[] = $this->metric('performance:admin_request_duration_ms', $durationMilliseconds, 'ms');
                }
                if ($isBot) {
                    $pending[] = $this->metric('bot:request', 1.0, 'count');
                }
            }

            $this->metrics->addMany($pending);
        }
    }

    /** @return array{name:string,value:float,unit:string} */
    private function metric(string $name, float $value, string $unit): array
    {
        return [
            'name' => $name,
            'value' => $value,
            'unit' => $unit,
        ];
    }

    private function isBot(?string $userAgent): bool
    {
        if ($userAgent === null || $userAgent === '') {
            return false;
        }

        return preg_match('/bot|crawl|spider|slurp|bingpreview|facebookexternalhit|headless|uptime|monitor/i', $userAgent) === 1;
    }
}
