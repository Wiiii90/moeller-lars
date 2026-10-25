<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

final class SetAdminLocale
{
    public function handle(Request $request, Closure $next): Response
    {
        $language = $request->user()?->getAttribute('admin_language');

        // This middleware runs only in the authenticated Filament panel,
        // including persistent Livewire requests. Public retains APP_LOCALE.
        app()->setLocale(in_array($language, ['en', 'de'], true) ? $language : 'en');

        return $next($request);
    }
}
