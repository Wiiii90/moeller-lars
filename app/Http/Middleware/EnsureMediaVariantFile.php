<?php

namespace App\Http\Middleware;

use App\Domain\Media\MediaVariantRegenerationService;
use App\Domain\Media\PublicMedia;
use App\Models\MediaVariant;
use App\Models\User;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\Response;

final class EnsureMediaVariantFile
{
    public function __construct(
        private readonly MediaVariantRegenerationService $regeneration,
        private readonly PublicMedia $publicMedia,
    ) {}

    public function handle(Request $request, Closure $next): Response
    {
        $variant = $request->route('mediaVariant');
        if (! $variant instanceof MediaVariant || $this->fileExists($variant)) {
            return $next($request);
        }

        if ($this->mayRegenerate($request, $variant)) {
            $this->regeneration->ensureFile($variant);
        }

        return $next($request);
    }

    private function mayRegenerate(Request $request, MediaVariant $variant): bool
    {
        $routeName = $request->route()?->getName();

        if ($routeName === 'media.variant') {
            $variant->loadMissing('mediaAsset');

            return $this->publicMedia->isPublicVariant($variant);
        }

        if (in_array($routeName, ['admin.media.variant', 'preview.media.variant'], true)) {
            $actor = Auth::guard('web')->user();

            return $actor instanceof User && (bool) $actor->getAttribute('is_admin');
        }

        return false;
    }

    private function fileExists(MediaVariant $variant): bool
    {
        $storageKey = (string) $variant->getAttribute('storage_key');
        if ($storageKey === '') {
            return false;
        }

        return Storage::disk((string) config('media.disk'))->exists($storageKey);
    }
}
