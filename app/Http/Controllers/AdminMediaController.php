<?php

namespace App\Http\Controllers;

use App\Models\MediaAsset;
use App\Models\MediaVariant;
use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\Response;

final class AdminMediaController extends Controller
{
    public function original(MediaAsset $mediaAsset): Response
    {
        $this->authorizeAdmin();
        abort_unless($mediaAsset->getAttribute('state') === 'available', 404);

        $disk = Storage::disk(config('media.disk'));
        $storageKey = (string) $mediaAsset->getAttribute('storage_key');
        abort_unless($disk->exists($storageKey), 404);

        return $this->sendfile(
            $disk,
            $storageKey,
            (string) $mediaAsset->getAttribute('mime_type'),
            (int) $mediaAsset->getAttribute('byte_size'),
            (string) $mediaAsset->getAttribute('sha256'),
        );
    }

    public function variant(MediaVariant $mediaVariant): Response
    {
        $this->authorizeAdmin();
        $mediaVariant->loadMissing('mediaAsset');

        /** @var MediaAsset|null $asset */
        $asset = $mediaVariant->getRelationValue('mediaAsset');
        abort_unless(
            $asset !== null
            && $asset->getAttribute('state') === 'available'
            && $mediaVariant->getAttribute('state') === 'available',
            404,
        );

        $disk = Storage::disk(config('media.disk'));
        $storageKey = (string) $mediaVariant->getAttribute('storage_key');
        abort_unless($disk->exists($storageKey), 404);

        return $this->sendfile(
            $disk,
            $storageKey,
            (string) $mediaVariant->getAttribute('mime_type'),
            (int) $mediaVariant->getAttribute('byte_size'),
            (string) $mediaVariant->getAttribute('sha256'),
        );
    }

    private function authorizeAdmin(): void
    {
        $user = Auth::guard('web')->user();

        abort_unless($user instanceof User && (bool) $user->getAttribute('is_admin'), 403);
    }

    private function sendfile($disk, string $key, string $mimeType, int $byteSize, string $sha256): Response
    {
        $path = $disk->path($key);
        abort_unless(is_file($path) && is_readable($path), 404);

        return response('', 200, [
            'Content-Type' => $mimeType,
            'Content-Length' => (string) max(0, $byteSize),
            'ETag' => '"'.$sha256.'"',
            'Accept-Ranges' => 'bytes',
            'Content-Disposition' => 'inline',
            'X-Content-Type-Options' => 'nosniff',
            'Cache-Control' => 'private, max-age=3600',
            'X-Sendfile' => $path,
        ]);
    }
}
