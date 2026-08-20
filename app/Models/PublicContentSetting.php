<?php

namespace App\Models;

use App\Models\Concerns\SingletonRecord;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Guarded;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Validation\ValidationException;

#[Fillable([
    'contact_state',
    'contact_status_text',
    'contact_icon',
    'contact_recipient_email',
    'public_email',
    'show_public_email',
    'instagram_handle',
    'show_instagram',
    'legal_disclaimer',
    'profile_text_blocks',
    'favicon_media_asset_id',
])]
#[Guarded(['id'])]
class PublicContentSetting extends Model
{
    use SingletonRecord;

    protected $table = 'public_content_settings';

    public $incrementing = false;

    protected function casts(): array
    {
        return [
            'show_public_email' => 'boolean',
            'show_instagram' => 'boolean',
            'profile_text_blocks' => 'array',
        ];
    }

    /** @return BelongsTo<MediaAsset, $this> */
    public function faviconMediaAsset(): BelongsTo
    {
        return $this->belongsTo(MediaAsset::class, 'favicon_media_asset_id');
    }

    protected static function booted(): void
    {
        static::saving(function (self $setting): void {
            if ($setting->getAttribute('contact_state') === 'under_construction') {
                $statusText = $setting->getAttribute('contact_status_text');
                if (! is_string($statusText) || trim($statusText) === '') {
                    throw ValidationException::withMessages([
                        'contact_status_text' => 'A status text is required while Contact is under construction.',
                    ]);
                }
            }

            $publicEmail = $setting->getAttribute('public_email');
            if ($publicEmail !== null && (! is_string($publicEmail) || filter_var($publicEmail, FILTER_VALIDATE_EMAIL) === false)) {
                throw ValidationException::withMessages([
                    'public_email' => 'The public email address is invalid.',
                ]);
            }

            $contactRecipientEmail = $setting->getAttribute('contact_recipient_email');
            if ($contactRecipientEmail !== null && (! is_string($contactRecipientEmail) || filter_var($contactRecipientEmail, FILTER_VALIDATE_EMAIL) === false)) {
                throw ValidationException::withMessages([
                    'contact_recipient_email' => 'The contact form recipient email address is invalid.',
                ]);
            }

            $instagramHandle = $setting->getAttribute('instagram_handle');
            if ($instagramHandle !== null && (! is_string($instagramHandle) || preg_match('/^[A-Za-z0-9._]{1,30}$/', $instagramHandle) !== 1)) {
                throw ValidationException::withMessages([
                    'instagram_handle' => 'The Instagram handle is invalid.',
                ]);
            }

            $legalDisclaimer = $setting->getAttribute('legal_disclaimer');
            if ($legalDisclaimer !== null && (! is_string($legalDisclaimer) || trim($legalDisclaimer) === '')) {
                throw ValidationException::withMessages([
                    'legal_disclaimer' => 'The legal disclaimer must contain text or be empty.',
                ]);
            }

            $blocks = $setting->getAttribute('profile_text_blocks');
            if ($blocks !== null) {
                if (! is_array($blocks)) {
                    throw ValidationException::withMessages([
                        'profile_text_blocks' => 'Additional CV text blocks must be a list.',
                    ]);
                }

                foreach ($blocks as $index => $block) {
                    if (! is_array($block)) {
                        throw ValidationException::withMessages([
                            "profile_text_blocks.$index" => 'Each additional CV text block must be structured content.',
                        ]);
                    }

                    $title = $block['title'] ?? null;
                    $body = $block['body'] ?? null;
                    if (! is_string($title) || trim($title) === '' || mb_strlen($title) > 120) {
                        throw ValidationException::withMessages([
                            "profile_text_blocks.$index.title" => 'Each additional CV text block needs a short title.',
                        ]);
                    }
                    if (! is_string($body) || trim($body) === '' || mb_strlen($body) > 5000) {
                        throw ValidationException::withMessages([
                            "profile_text_blocks.$index.body" => 'Each additional CV text block needs text.',
                        ]);
                    }
                }
            }
        });
    }
}
