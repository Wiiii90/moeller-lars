<?php

namespace App\Http\Controllers;

use App\Domain\Content\PreviewDeviceProfiles;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Validation\Rule;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

final class AdminLivePreviewRenderController
{
    public function __invoke(Request $request): Response
    {
        $user = $request->user();
        abort_unless($user instanceof User && (bool) $user->getAttribute('is_admin'), 404);

        $input = $request->validate([
            'path' => ['required', 'string', 'max:500'],
            'profile' => ['required', 'string', Rule::in(array_keys(PreviewDeviceProfiles::all()))],
            'orientation' => ['required', Rule::in(['default', 'rotated'])],
            'engine' => ['required', Rule::in(['chromium', 'webkit', 'firefox'])],
            'scroll_y' => ['sometimes', 'integer', 'min:0', 'max:150000'],
        ]);

        // Never allow the browser-facing request to select a host, port or
        // arbitrary internal endpoint. Only the protected Working preview tree.
        $path = $input['path'];
        abort_unless(
            preg_match('~^/preview(?:/|$)~D', $path) === 1
            && ! str_contains($path, '..')
            && ! str_contains($path, '//')
            && ! str_contains($path, '%')
            && ! str_contains($path, '?')
            && ! str_contains($path, '#')
            && ! str_contains($path, '\\'),
            422,
        );

        $endpoint = config('services.live_preview_renderer.endpoint');
        $token = config('services.live_preview_renderer.token');
        if (! is_string($endpoint) || $endpoint === '' || ! is_string($token) || $token === '') {
            return response()->json(['message' => 'Browser comparison is not configured.'], 503);
        }

        $profile = PreviewDeviceProfiles::find($input['profile']);
        abort_unless($profile !== null, 422);

        // Only the encrypted Laravel session cookie is needed by the
        // protected Preview. Do not forward the artist's entire cookie jar.
        $sessionCookie = (string) config('session.cookie');
        $cookie = collect(explode(';', (string) $request->headers->get('Cookie', '')))
            ->map(static fn (string $part): string => trim($part))
            ->first(static fn (string $part): bool => str_starts_with($part, $sessionCookie.'='));
        abort_unless(is_string($cookie) && $cookie !== '', 401);

        try {
            // The first isolated browser launch and initial media load can
            // exceed a short HTTP timeout. The client keeps its interactive
            // preview visible while the comparison runs.
            $render = Http::connectTimeout(3)
                ->timeout(45)
                ->withToken($token)
                ->withHeaders([
                    // Forward only the already-encrypted session cookie.
                    'Cookie' => $cookie,
                    'Accept' => 'image/png',
                ])
                ->post(rtrim($endpoint, '/').'/render', [
                    'path' => $path,
                    'engine' => $input['engine'],
                    'device' => $profile,
                    'orientation' => $input['orientation'],
                    'scrollY' => (int) ($input['scroll_y'] ?? 0),
                ]);
        } catch (Throwable $exception) {
            report($exception);

            return response()->json(['message' => 'Browser renderer is unavailable.'], 503);
        }

        if (! $render->successful() || ! str_starts_with((string) $render->header('Content-Type'), 'image/png')) {
            return response()->json(['message' => 'Browser rendering failed.'], 503);
        }

        return response($render->body(), 200, [
            'Content-Type' => 'image/png',
            'Cache-Control' => 'private, no-store, max-age=0',
            'X-Robots-Tag' => 'noindex, nofollow, noarchive',
            'X-Preview-Overflow-X' => (string) $render->header('X-Preview-Overflow-X', '0'),
            'X-Preview-Scroll-Max' => (string) $render->header('X-Preview-Scroll-Max', '0'),
            'X-Preview-Scroll-Y' => (string) $render->header('X-Preview-Scroll-Y', '0'),
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }
}
