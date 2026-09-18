<?php

namespace App\Http\Middleware;

use App\Domain\Publication\CommittedRead;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

final class UseCommittedPublicState
{
    public function __construct(private readonly CommittedRead $committedRead) {}

    public function handle(Request $request, Closure $next): Response
    {
        if (! $this->isPublicReadRequest($request)) {
            return $next($request);
        }

        return $this->committedRead->run(function () use ($request, $next): Response {
            /** @var Response $response */
            $response = $next($request);

            return $response;
        });
    }

    private function isPublicReadRequest(Request $request): bool
    {
        if ($request->is('admin', 'admin/*', 'preview', 'preview/*', 'livewire', 'livewire/*', 'up')) {
            return false;
        }

        return $request->isMethod('GET') || $request->isMethod('HEAD');
    }
}
