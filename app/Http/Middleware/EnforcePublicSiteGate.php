<?php

namespace App\Http\Middleware;

use App\Domain\Content\SitePreviewContext;
use App\Models\SiteSection;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

final class EnforcePublicSiteGate
{
    public function __construct(private readonly SitePreviewContext $preview) {}

    public function handle(Request $request, Closure $next): Response
    {
        if (! $this->preview->active() && SiteSection::query()
            ->where('is_home', true)->where('public_site_gate', true)->exists()) {
            return redirect()->route('home');
        }

        return $next($request);
    }
}
