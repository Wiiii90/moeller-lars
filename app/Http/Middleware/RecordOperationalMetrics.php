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

        try {
            $response = $next($request);
            $status = $response->getStatusCode();

            if ($status === 404) {
                $this->metrics->add('error:http_404', 1, 'count');
            }
            if ($status >= 500) {
                $this->metrics->add('error:http_5xx', 1, 'count');
            }
            if ($isAdmin) {
                $this->metrics->add('operation:admin_request', 1, 'count');
            }

            return $response;
        } catch (Throwable $exception) {
            $this->metrics->add('error:request_exception', 1, 'count');
            throw $exception;
        } finally {
            if (! $request->is('up')) {
                $durationMilliseconds = (hrtime(true) - $startedAt) / 1_000_000;
                $this->metrics->add('performance:request_duration_ms', $durationMilliseconds, 'ms');
                if ($isAdmin) {
                    $this->metrics->add('performance:admin_request_duration_ms', $durationMilliseconds, 'ms');
                }
                if ($isBot) {
                    $this->metrics->add('bot:request', 1, 'count');
                }
            }
        }
    }

    private function isBot(?string $userAgent): bool
    {
        if ($userAgent === null || $userAgent === '') {
            return false;
        }

        return preg_match('/bot|crawl|spider|slurp|bingpreview|facebookexternalhit|headless|uptime|monitor/i', $userAgent) === 1;
    }
}
