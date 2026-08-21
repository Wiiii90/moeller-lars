<?php

namespace App\Models;

use App\Domain\Content\SocialLinks;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Guarded;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Validation\ValidationException;
use LogicException;

#[Fillable([
    'contact_state',
    'contact_status_text',
    'contact_icon',
    'contact_recipient_email',
    'public_email',
    'show_public_email',
    'instagram_handle',
    'show_instagram',
    'social_links',
    'legal_disclaimer',
    'profile_text_blocks',
    'favicon_media_asset_id',
])]
#[Guarded(['id'])]
class PublicContentSetting extends Model
{
    protected $table = 'public_content_settings';

    public $incrementing = false;

    protected function casts(): array
    {
        return [
            'show_public_email' => 'boolean',
            'show_instagram' => 'boolean',
            'social_links' => 'array',
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

            $socialLinks = $setting->getAttribute('social_links');
            $legacyInstagram = $setting->getAttribute('instagram_handle');
            if (($socialLinks === null || $socialLinks === []) && is_string($legacyInstagram) && trim($legacyInstagram) !== '') {
                $socialLinks = [[
                    'platform' => 'instagram',
                    'url' => 'https://www.instagram.com/'.trim($legacyInstagram).'/',
                    'visible' => (bool) $setting->getAttribute('show_instagram'),
                ]];
                $setting->setAttribute('social_links', $socialLinks);
            }

            if ($socialLinks !== null) {
                if (! is_array($socialLinks)) {
                    throw ValidationException::withMessages([
                        'social_links' => 'Social links must be a list.',
                    ]);
                }

                $platforms = [];
                foreach ($socialLinks as $index => $link) {
                    if (! is_array($link)) {
                        throw ValidationException::withMessages([
                            "social_links.$index" => 'Each social link must be structured content.',
                        ]);
                    }

                    $platform = $link['platform'] ?? null;
                    $url = $link['url'] ?? null;
                    $visible = $link['visible'] ?? true;
                    if (! is_string($platform) || ! SocialLinks::supports($platform)) {
                        throw ValidationException::withMessages([
                            "social_links.$index.platform" => 'Choose a supported social platform.',
                        ]);
                    }
                    if (isset($platforms[$platform])) {
                        throw ValidationException::withMessages([
                            "social_links.$index.platform" => 'Each social platform can only be configured once.',
                        ]);
                    }
                    $platforms[$platform] = true;

                    $scheme = is_string($url) ? strtolower((string) parse_url($url, PHP_URL_SCHEME)) : '';
                    if (! is_string($url) || mb_strlen($url) > 2048 || filter_var($url, FILTER_VALIDATE_URL) === false || ! in_array($scheme, ['http', 'https'], true)) {
                        throw ValidationException::withMessages([
                            "social_links.$index.url" => 'Social links must use a valid HTTP or HTTPS URL.',
                        ]);
                    }
                    if (! is_bool($visible)) {
                        throw ValidationException::withMessages([
                            "social_links.$index.visible" => 'Social link visibility must be true or false.',
                        ]);
                    }
                }
            }

            $faviconId = $setting->getAttribute('favicon_media_asset_id');
            if ($faviconId !== null) {
                $favicon = MediaAsset::query()->find($faviconId);
                $mimeType = $favicon?->getAttribute('mime_type');
                if (! $favicon instanceof MediaAsset || $favicon->getAttribute('state') !== 'available' || ! is_string($mimeType) || ! str_starts_with($mimeType, 'image/')) {
                    throw ValidationException::withMessages([
                        'favicon_media_asset_id' => 'The favicon must be an available image from Media.',
                    ]);
                }
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

        static::deleting(function (): never {
            throw new LogicException('Public content settings cannot be deleted.');
        });
    }
}
