<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\Response;

final class UseCommittedPublicState
{
    public function handle(Request $request, Closure $next): Response
    {
        if (! $this->isPublicReadRequest($request)) {
            return $next($request);
        }

        return DB::transaction(function () use ($request, $next): Response {
            DB::statement('SET TRANSACTION ISOLATION LEVEL REPEATABLE READ');
            DB::statement('SET LOCAL search_path TO committed, public');

            /** @var Response $response */
            $response = $next($request);

            return $response;
        }, attempts: 1);
    }

    private function isPublicReadRequest(Request $request): bool
    {
        if ($request->is('admin', 'admin/*', 'preview', 'preview/*', 'livewire', 'livewire/*')) {
            return false;
        }

        if ($request->isMethod('GET') || $request->isMethod('HEAD')) {
            return true;
        }

        return $request->isMethod('POST') && $request->is('contact');
    }
}
