<?php

namespace App\Http\Middleware;

use App\Domain\Content\HomePresentationResolver;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

final class EnforceHomePresentationGate
{
    public function __construct(private readonly HomePresentationResolver $home) {}

    public function handle(Request $request, Closure $next): Response
    {
        if ($this->home->publicGateActive()) {
            return redirect()->route('home');
        }

        return $next($request);
    }
}
