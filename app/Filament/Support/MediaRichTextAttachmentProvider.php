<?php

namespace App\Filament\Support;

use App\Domain\Media\MediaIngestService;
use App\Domain\Media\MediaPreviewVariant;
use App\Models\MediaAsset;
use Filament\Forms\Components\RichEditor\FileAttachmentProviders\Contracts\FileAttachmentProvider;
use Filament\Forms\Components\RichEditor\RichContentAttribute;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;

final class MediaRichTextAttachmentProvider implements FileAttachmentProvider
{
    public function attribute(RichContentAttribute $attribute): static
    {
        return $this;
    }

    public function getFileAttachmentUrl(mixed $file): ?string
    {
        if (! is_scalar($file) || ! ctype_digit((string) $file)) {
            return null;
        }
        $asset = MediaAsset::query()
            ->whereKey((int) $file)
            ->where('state', 'available')
            ->where('mime_type', 'like', 'image/%')
            ->first();
        if (! $asset instanceof MediaAsset) {
            return null;
        }

        $variant = MediaPreviewVariant::imageThumbnail($asset);
        if ($variant === null) {
            return null;
        }

        // The canonical public/preview media route is derived from the asset.
        // No public filesystem path or duplicate editor attachment is created.
        if (request()->is('admin/*')) {
            return route('admin.media.variant', $variant);
        }
        return app(\App\Domain\Media\PublicMedia::class)->thumbnailUrlForAsset($asset);
    }

    public function saveUploadedFileAttachment(TemporaryUploadedFile $file): mixed
    {
        return (string) app(MediaIngestService::class)->ingestUnique($file)['asset']->getKey();
    }

    public function getDefaultFileAttachmentVisibility(): ?string
    {
        return 'private';
    }

    public function isExistingRecordRequiredToSaveNewFileAttachments(): bool
    {
        return false;
    }

    public function cleanUpFileAttachments(array $exceptIds): void
    {
        // Storage owns lifecycle, reference checks and deletion. An editor
        // must never delete shared media because it was removed from one text.
    }
}
