<?php

namespace App\Http\Controllers;

use App\Domain\Media\PublicMedia;
use App\Models\MediaAsset;
use App\Models\MediaVariant;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\Response;

class PublicMediaController extends Controller
{
    public function __construct(private readonly PublicMedia $publicMedia) {}

    public function original(MediaAsset $mediaAsset): Response
    {
        abort_unless($this->publicMedia->isPublicAsset($mediaAsset), 404);

        $disk = Storage::disk(config('media.disk'));
        $storageKey = (string) $mediaAsset->getAttribute('storage_key');
        abort_unless($disk->exists($storageKey), 404);

        return $this->sendfile(
            $disk,
            $storageKey,
            (string) $mediaAsset->getAttribute('mime_type'),
            (int) $mediaAsset->getAttribute('byte_size'),
            (string) $mediaAsset->getAttribute('sha256'),
            'public, max-age=31536000, immutable',
        );
    }

    public function variant(MediaVariant $mediaVariant): Response
    {
        abort_unless($this->publicMedia->isPublicVariant($mediaVariant), 404);

        $disk = Storage::disk(config('media.disk'));
        $storageKey = (string) $mediaVariant->getAttribute('storage_key');
        abort_unless($disk->exists($storageKey), 404);

        return $this->sendfile(
            $disk,
            $storageKey,
            (string) $mediaVariant->getAttribute('mime_type'),
            (int) $mediaVariant->getAttribute('byte_size'),
            (string) $mediaVariant->getAttribute('sha256'),
            'public, max-age=31536000, immutable',
        );
    }

    private function sendfile($disk, string $key, string $mimeType, int $byteSize, string $sha256, string $cacheControl): Response
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
            'Cache-Control' => $cacheControl,
            'X-Sendfile' => $path,
        ]);
    }
}
