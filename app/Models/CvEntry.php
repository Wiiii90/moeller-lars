<?php

namespace App\Models;

use App\Domain\Content\SafeLinkPolicy;
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

    /**
     * Historical relation retained for imported records. New/public CV presentation
     * uses the canonical image configured on the CV List component instead.
     */
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
