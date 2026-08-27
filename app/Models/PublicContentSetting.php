<?php

namespace App\Models;

use App\Domain\Content\PublicAppearance;
use App\Domain\Content\SocialLinks;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Guarded;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Validation\ValidationException;
use InvalidArgumentException;
use LogicException;

#[Fillable([
    'contact_recipient_email',
    'public_email',
    'show_public_email',
    'instagram_handle',
    'show_instagram',
    'social_links',
    'legal_disclaimer',
    'favicon_media_asset_id',
    'default_media_copyright_notice',
    'background_mode',
    'background_color',
    'background_gradient_start',
    'background_gradient_end',
    'background_gradient_angle',
])]
#[Guarded(['id', 'scope'])]
class PublicContentSetting extends Model
{
    public const SCOPE_GENERAL = 'general';
    public const SCOPES = [self::SCOPE_GENERAL];

    protected $table = 'public_content_settings';
    public $incrementing = false;

    protected function casts(): array
    {
        return [
            'show_public_email' => 'boolean',
            'show_instagram' => 'boolean',
            'social_links' => 'array',
            'background_gradient_angle' => 'integer',
        ];
    }

    public static function forScope(string $scope): self
    {
        if (! in_array($scope, self::SCOPES, true)) {
            throw new InvalidArgumentException('Unsupported public content settings scope.');
        }

        /** @var self $setting */
        $setting = self::query()->where('scope', $scope)->firstOrFail();

        return $setting;
    }

    public static function general(): self
    {
        return self::forScope(self::SCOPE_GENERAL);
    }

    /** @return BelongsTo<MediaAsset, $this> */
    public function faviconMediaAsset(): BelongsTo
    {
        return $this->belongsTo(MediaAsset::class, 'favicon_media_asset_id');
    }

    protected static function booted(): void
    {
        static::saving(function (self $setting): void {
            if ((string) $setting->getAttribute('scope') !== self::SCOPE_GENERAL) {
                throw ValidationException::withMessages(['scope' => 'The settings scope is invalid.']);
            }

            self::validateGeneral($setting);
        });

        static::deleting(function (): never {
            throw new LogicException('Global public content settings cannot be deleted.');
        });
    }

    private static function validateGeneral(self $setting): void
    {
        $setting->setAttribute('background_mode', PublicAppearance::normalizeMode($setting->getAttribute('background_mode')));
        $setting->setAttribute('background_color', PublicAppearance::normalizeColor($setting->getAttribute('background_color'), 'background_color'));
        $setting->setAttribute('background_gradient_start', PublicAppearance::normalizeColor($setting->getAttribute('background_gradient_start'), 'background_gradient_start'));
        $setting->setAttribute('background_gradient_end', PublicAppearance::normalizeColor($setting->getAttribute('background_gradient_end'), 'background_gradient_end'));
        $setting->setAttribute('background_gradient_angle', PublicAppearance::normalizeAngle($setting->getAttribute('background_gradient_angle')));

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

        $defaultCopyright = $setting->getAttribute('default_media_copyright_notice');
        if ($defaultCopyright !== null) {
            if (! is_string($defaultCopyright) || mb_strlen($defaultCopyright) > 500) {
                throw ValidationException::withMessages([
                    'default_media_copyright_notice' => 'The default media copyright notice may contain no more than 500 characters.',
                ]);
            }

            $defaultCopyright = trim($defaultCopyright);
            $setting->setAttribute('default_media_copyright_notice', $defaultCopyright === '' ? null : $defaultCopyright);
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
                throw ValidationException::withMessages(['social_links' => 'Social links must be a list.']);
            }

            $platforms = [];
            foreach ($socialLinks as $index => $link) {
                if (! is_array($link)) {
                    throw ValidationException::withMessages(["social_links.$index" => 'Each social link must be structured content.']);
                }

                $platform = $link['platform'] ?? null;
                $url = $link['url'] ?? null;
                $visible = $link['visible'] ?? true;
                if (! is_string($platform) || ! SocialLinks::supports($platform)) {
                    throw ValidationException::withMessages(["social_links.$index.platform" => 'Choose a supported social platform.']);
                }
                if (isset($platforms[$platform])) {
                    throw ValidationException::withMessages(["social_links.$index.platform" => 'Each social platform can only be configured once.']);
                }
                $platforms[$platform] = true;

                $scheme = is_string($url) ? strtolower((string) parse_url($url, PHP_URL_SCHEME)) : '';
                if (! is_string($url) || mb_strlen($url) > 2048 || filter_var($url, FILTER_VALIDATE_URL) === false || ! in_array($scheme, ['http', 'https'], true)) {
                    throw ValidationException::withMessages(["social_links.$index.url" => 'Social links must use a valid HTTP or HTTPS URL.']);
                }
                if (! is_bool($visible)) {
                    throw ValidationException::withMessages(["social_links.$index.visible" => 'Social link visibility must be true or false.']);
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
    }
}
