<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Guarded;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Validation\ValidationException;

#[Fillable(['storage_key', 'original_filename', 'mime_type', 'byte_size', 'sha256', 'state', 'alt_text', 'copyright_notice', 'copyright_notice_mode', 'credit', 'width', 'height', 'metadata', 'focal_point_x', 'focal_point_y', 'legacy_id', 'legacy_source', 'legacy_path', 'legacy_filename', 'legacy_byte_size', 'migration_batch_id', 'migrated_at'])]
#[Guarded(['id'])]
class MediaAsset extends Model
{
    use HasFactory;

    public const COPYRIGHT_INHERIT = 'inherit';

    public const COPYRIGHT_OVERRIDE = 'override';

    public const COPYRIGHT_NONE = 'none';

    public const COPYRIGHT_MODES = [
        self::COPYRIGHT_INHERIT,
        self::COPYRIGHT_OVERRIDE,
        self::COPYRIGHT_NONE,
    ];

    protected function casts(): array
    {
        return [
            'metadata' => 'array',
            'focal_point_x' => 'decimal:4',
            'focal_point_y' => 'decimal:4',
            'migrated_at' => 'datetime',
        ];
    }

    public function effectiveCopyrightNotice(): ?string
    {
        return match ((string) $this->getAttribute('copyright_notice_mode')) {
            self::COPYRIGHT_OVERRIDE => $this->normalCopyrightNotice($this->getAttribute('copyright_notice')),
            self::COPYRIGHT_NONE => null,
            default => $this->normalCopyrightNotice(PublicContentSetting::general()->getAttribute('default_media_copyright_notice')),
        };
    }

    public function copyrightNoticeSourceLabel(): string
    {
        return match ((string) $this->getAttribute('copyright_notice_mode')) {
            self::COPYRIGHT_OVERRIDE => 'Asset override',
            self::COPYRIGHT_NONE => 'No notice',
            default => 'Inherited from General',
        };
    }

    public function variants(): HasMany
    {
        return $this->hasMany(MediaVariant::class);
    }

    public function cvEntries(): HasMany
    {
        return $this->hasMany(CvEntry::class, 'image_media_asset_id');
    }

    public function blogPosts(): HasMany
    {
        return $this->hasMany(BlogPost::class, 'cover_media_asset_id');
    }

    public function siteIdentitySettings(): HasMany
    {
        return $this->hasMany(PublicContentSetting::class, 'favicon_media_asset_id');
    }

    public function artworks(): BelongsToMany
    {
        return $this->belongsToMany(Artwork::class, 'artwork_media')
            ->withPivot(['role', 'position', 'alt_text_override'])
            ->withTimestamps();
    }

    public function exhibitionMedia(): HasMany
    {
        return $this->hasMany(ExhibitionMedia::class);
    }

    public function exhibitions(): BelongsToMany
    {
        return $this->belongsToMany(Exhibition::class, 'exhibition_media')
            ->withPivot(['role', 'position', 'alt_text_override'])
            ->withTimestamps();
    }

    protected static function booted(): void
    {
        static::saving(function (self $asset): void {
            $mode = $asset->getAttribute('copyright_notice_mode');
            if ($mode === null) {
                $notice = $asset->getAttribute('copyright_notice');
                $mode = is_string($notice) && trim($notice) !== ''
                    ? self::COPYRIGHT_OVERRIDE
                    : self::COPYRIGHT_INHERIT;
                $asset->setAttribute('copyright_notice_mode', $mode);
            }

            if (! is_string($mode) || ! in_array($mode, self::COPYRIGHT_MODES, true)) {
                throw ValidationException::withMessages([
                    'copyright_notice_mode' => 'Choose whether copyright is inherited, overridden, or omitted.',
                ]);
            }

            if ($mode !== self::COPYRIGHT_OVERRIDE) {
                $asset->setAttribute('copyright_notice', null);

                return;
            }

            $notice = $asset->getAttribute('copyright_notice');
            if (! is_string($notice) || trim($notice) === '' || mb_strlen($notice) > 500) {
                throw ValidationException::withMessages([
                    'copyright_notice' => 'An asset copyright override must contain no more than 500 characters.',
                ]);
            }

            $asset->setAttribute('copyright_notice', trim($notice));
        });
    }

    private function normalCopyrightNotice(mixed $value): ?string
    {
        if (! is_string($value)) {
            return null;
        }

        $value = trim($value);

        return $value === '' ? null : $value;
    }
}
