<?php

namespace App\Http\Controllers;

use App\Domain\Media\PublicMedia;
use App\Domain\Publication\MediaRightsPublicationSelections;
use App\Models\MediaRightsArtifact;
use App\Models\MediaAsset;
use App\Models\MediaVariant;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\Response;

class PublicMediaController extends Controller
{
    public function __construct(
        private readonly PublicMedia $publicMedia,
        private readonly MediaRightsPublicationSelections $rightsSelections,
    ) {}

    public function original(MediaAsset $mediaAsset, ?string $revision = null): Response
    {
        abort_unless($this->publicMedia->isPublicAsset($mediaAsset), 404);

        $artifact = \App\Domain\Media\MediaTypePolicy::isImage((string) $mediaAsset->getAttribute('mime_type'))
            ? $this->rightsSelections->selectedForPublic($mediaAsset)
            : null;
        abort_unless($artifact === null ? $revision === null : $revision === $artifact->getAttribute('revision'), 404);
        if ($artifact instanceof MediaRightsArtifact) {
            return $this->artifactResponse($artifact);
        }

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

    public function variant(MediaVariant $mediaVariant, ?string $revision = null): Response
    {
        abort_unless($this->publicMedia->isPublicVariant($mediaVariant), 404);

        $artifact = $this->rightsSelections->selectedForPublic($mediaVariant);
        abort_unless($artifact === null ? $revision === null : $revision === $artifact->getAttribute('revision'), 404);
        if ($artifact instanceof MediaRightsArtifact) {
            return $this->artifactResponse($artifact);
        }

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

    private function artifactResponse(MediaRightsArtifact $artifact): Response
    {
        $disk = Storage::disk((string) config('media.disk'));
        $key = (string) $artifact->getAttribute('storage_key');
        abort_unless(str_starts_with($key, 'variants/rights/') && $disk->exists($key), 404);

        return $this->sendfile(
            $disk,
            $key,
            (string) $artifact->getAttribute('mime_type'),
            (int) $artifact->getAttribute('byte_size'),
            (string) $artifact->getAttribute('sha256'),
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
