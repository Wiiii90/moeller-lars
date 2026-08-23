<?php

namespace App\Filament\Resources\Artworks\Pages\Concerns;

use App\Domain\Artwork\ArtworkPublicationService;
use App\Domain\Media\MediaTypePolicy;
use App\Models\Artwork;
use App\Models\MediaAsset;
use Filament\Notifications\Notification;
use Illuminate\Validation\ValidationException;

trait GalleryWorkspaceReadinessSupport
{
    private function isReadyToPublish(Artwork $artwork, bool $galleryPublished, int $primaryCount, ?MediaAsset $primaryAsset): bool
    {
        return filled($artwork->getAttribute('title'))
            && filled($artwork->getAttribute('slug'))
            && $primaryCount === 1
            && $primaryAsset instanceof MediaAsset
            && app(ArtworkPublicationService::class)->mediaReady($primaryAsset)
            && $galleryPublished;
    }

    private function readinessLabel(Artwork $artwork, bool $galleryPublished, int $primaryCount, ?MediaAsset $primaryAsset): string
    {
        if (! filled($artwork->getAttribute('title')) || ! filled($artwork->getAttribute('slug'))) {
            return 'Missing title or URL';
        }
        if ($primaryCount !== 1) {
            return $primaryCount === 0 ? 'Primary media required' : 'Primary media is ambiguous';
        }
        if (! $primaryAsset || $primaryAsset->getAttribute('state') !== 'available') {
            return 'Primary media unavailable';
        }

        $mime = (string) $primaryAsset->getAttribute('mime_type');
        if (! MediaTypePolicy::isImage($mime) && ! MediaTypePolicy::isVideo($mime)) {
            return 'Primary media must be image or video';
        }
        if (! filled($primaryAsset->getAttribute('alt_text'))) {
            return 'ALT text required';
        }
        if (MediaTypePolicy::isImage($mime) && ! app(ArtworkPublicationService::class)->mediaReady($primaryAsset)) {
            return 'Thumbnail processing required';
        }
        if (! $galleryPublished) {
            return 'Gallery must be published';
        }

        return 'Ready';
    }

    private function notifyValidationFailure(string $title, ValidationException $exception): void
    {
        Notification::make()->title($title)->body($this->firstValidationMessage($exception))->danger()->send();
    }

    private function firstValidationMessage(ValidationException $exception): string
    {
        $message = collect($exception->errors())->flatten()->first();

        return is_string($message) ? $message : 'The requested Gallery change is not valid.';
    }
}
