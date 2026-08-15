<?php

namespace App\Http\Controllers;

use App\Models\MediaAsset;
use App\Models\MediaVariant;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

class PublicMediaController extends Controller
{
    public function original(MediaAsset $mediaAsset): StreamedResponse
    {
        abort_unless($mediaAsset->getAttribute('state') === 'available', 404);

        $disk = Storage::disk(config('media.disk'));
        abort_unless($disk->exists($mediaAsset->getAttribute('storage_key')), 404);

        return $this->stream($disk, $mediaAsset->getAttribute('storage_key'), $mediaAsset->getAttribute('mime_type'));
    }

    public function variant(MediaVariant $mediaVariant): StreamedResponse
    {
        abort_unless($mediaVariant->getAttribute('state') === 'available', 404);
        /** @var MediaAsset $mediaAsset */
        $mediaAsset = $mediaVariant->getRelationValue('mediaAsset');
        abort_unless($mediaAsset->getAttribute('state') === 'available', 404);

        $disk = Storage::disk(config('media.disk'));
        abort_unless($disk->exists($mediaVariant->getAttribute('storage_key')), 404);

        return $this->stream($disk, $mediaVariant->getAttribute('storage_key'), $mediaVariant->getAttribute('mime_type'));
    }

    private function stream($disk, string $key, string $mimeType): StreamedResponse
    {
        $stream = $disk->readStream($key);
        abort_unless(is_resource($stream), 404);

        return response()->stream(function () use ($stream): void {
            fpassthru($stream);
            fclose($stream);
        }, 200, [
            'Content-Type' => $mimeType,
            'X-Content-Type-Options' => 'nosniff',
            'Cache-Control' => 'public, max-age=31536000, immutable',
        ]);
    }
}
