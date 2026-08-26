<?php

namespace App\Models;

use App\Domain\Content\SafeLinkPolicy;
use App\Domain\Content\SafeRichTextRenderer;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Guarded;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Validation\ValidationException;

#[Fillable(['section', 'title', 'state', 'position', 'date_precision', 'organisation', 'location', 'body', 'external_url', 'image_media_asset_id', 'year_text', 'starts_on', 'ends_on', 'legacy_id', 'legacy_source', 'migration_batch_id', 'migrated_at', 'published_at'])]
#[Guarded(['id'])]
class CvEntry extends Model
{
    use HasFactory;

    protected function casts(): array
    {
        return [
            'starts_on' => 'date',
            'ends_on' => 'date',
            'migrated_at' => 'datetime',
            'published_at' => 'datetime',
        ];
    }

    public function imageMediaAsset(): BelongsTo
    {
        return $this->belongsTo(MediaAsset::class, 'image_media_asset_id');
    }

    protected static function booted(): void
    {
        static::saving(function (self $entry): void {
            if ($entry->getAttribute('state') !== 'published') {
                return;
            }

            foreach (['section', 'title', 'year_text'] as $field) {
                $value = $entry->getAttribute($field);
                if (! is_string($value) || trim($value) === '') {
                    throw ValidationException::withMessages([$field => 'Published CV entries require explicit '.$field.'.']);
                }
            }

            $body = $entry->getAttribute('body');
            if ($body !== null) {
                if (! is_string($body)) {
                    throw ValidationException::withMessages(['body' => 'CV body content must be text.']);
                }
                app(SafeRichTextRenderer::class)->assertValid(
                    $body,
                    allowEmbeddedMedia: true,
                    requirePublicMedia: true,
                );
            }

            $imageId = $entry->getAttribute('image_media_asset_id');
            if ($imageId !== null) {
                /** @var MediaAsset|null $asset */
                $asset = is_numeric($imageId) ? MediaAsset::query()->find((int) $imageId) : null;
                $alt = $asset?->getAttribute('alt_text');
                if (! $asset instanceof MediaAsset
                    || $asset->getAttribute('state') !== 'available'
                    || ! str_starts_with((string) $asset->getAttribute('mime_type'), 'image/')
                    || ! is_string($alt)
                    || trim($alt) === '') {
                    throw ValidationException::withMessages([
                        'image_media_asset_id' => 'Published CV images require an available image with canonical ALT text.',
                    ]);
                }
            }

            $externalUrl = $entry->getAttribute('external_url');
            if ($externalUrl !== null) {
                if (! is_string($externalUrl)
                    || ! str_starts_with(strtolower($externalUrl), 'https://')
                    || ! app(SafeLinkPolicy::class)->isAllowed($externalUrl)) {
                    throw ValidationException::withMessages(['external_url' => 'CV links must be valid HTTPS URLs.']);
                }
            }

            if ($entry->getAttribute('published_at') === null) {
                $entry->setAttribute('published_at', now());
            }
        });
    }
}
