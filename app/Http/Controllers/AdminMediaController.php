<?php

namespace App\Http\Controllers;

use App\Models\MediaAsset;
use App\Models\MediaVariant;
use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

final class AdminMediaController extends Controller
{
    public function original(MediaAsset $mediaAsset): StreamedResponse
    {
        $this->authorizeAdmin();
        abort_unless($mediaAsset->getAttribute('state') === 'available', 404);

        $disk = Storage::disk(config('media.disk'));
        $storageKey = (string) $mediaAsset->getAttribute('storage_key');
        abort_unless($disk->exists($storageKey), 404);

        return $this->stream($disk, $storageKey, (string) $mediaAsset->getAttribute('mime_type'));
    }

    public function variant(MediaVariant $mediaVariant): StreamedResponse
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

        return $this->stream($disk, $storageKey, (string) $mediaVariant->getAttribute('mime_type'));
    }

    private function authorizeAdmin(): void
    {
        $user = Auth::guard('web')->user();

        abort_unless($user instanceof User && (bool) $user->getAttribute('is_admin'), 403);
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
            'Cache-Control' => 'private, max-age=3600',
        ]);
    }
}
