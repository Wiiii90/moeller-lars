<?php

namespace App\Models;

use App\Domain\Content\PublicAppearance;
use App\Domain\Content\RegionalFormats;
use App\Domain\Content\SocialLinks;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Guarded;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Validation\ValidationException;
use InvalidArgumentException;
use LogicException;

#[Fillable([
    'regional_format',
    'contact_recipient_email',
    'public_email',
    'show_public_email',
    'instagram_handle',
    'show_instagram',
    'social_links',
    'legal_disclaimer',
    'legal_imprint',
    'legal_privacy',
    'legal_usage_rights',
    'favicon_media_asset_id',
    'default_media_copyright_notice',
    'frame_style',
    'menu_type',
    'background_mode',
    'background_color',
    'background_gradient_start',
    'background_gradient_end',
    'background_gradient_angle',
    'menu_background_mode',
    'menu_background_color',
    'menu_background_gradient_start',
    'menu_background_gradient_end',
    'menu_background_gradient_angle',
    'page_background_mode',
    'page_background_color',
    'page_background_gradient_start',
    'page_background_gradient_end',
    'page_background_gradient_angle',
    'background_gradient_stops',
    'background_gradient_repeating',
    'background_gradient_repeat_span',
    'background_gradient_center_x',
    'background_gradient_center_y',
    'background_gradient_shape',
    'menu_background_gradient_stops',
    'menu_background_gradient_repeating',
    'menu_background_gradient_repeat_span',
    'menu_background_gradient_center_x',
    'menu_background_gradient_center_y',
    'menu_background_gradient_shape',
    'page_background_gradient_stops',
    'page_background_gradient_repeating',
    'page_background_gradient_repeat_span',
    'page_background_gradient_center_x',
    'page_background_gradient_center_y',
    'page_background_gradient_shape',
    'header_name_visible',
    'header_name_text',
    'header_name_alignment',
    'header_name_mobile_alignment',
    'header_name_position',
    'header_name_caps',
    'header_name_color',
    'header_name_mode',
    'header_name_gradient_start',
    'header_name_gradient_end',
    'header_name_gradient_angle',
    'header_name_gradient_stops',
    'header_name_gradient_repeating',
    'header_name_gradient_repeat_span',
    'header_name_gradient_center_x',
    'header_name_gradient_center_y',
    'header_name_gradient_shape',
    'header_name_size',
    'header_name_min_size',
    'header_divider_mode',
    'header_divider_color',
    'header_divider_paint_mode',
    'header_divider_paint_gradient_start',
    'header_divider_paint_gradient_end',
    'header_divider_paint_gradient_angle',
    'header_divider_paint_gradient_stops',
    'header_divider_paint_gradient_repeating',
    'header_divider_paint_gradient_repeat_span',
    'header_divider_paint_gradient_center_x',
    'header_divider_paint_gradient_center_y',
    'header_divider_paint_gradient_shape',
    'header_name_auto_scale',
    'public_page_width',
    'public_content_padding',
    'navigation_nesting_enabled',
    'navigation_hover_preview',
    'pages_auto_publish',
    'pages_delete_without_confirmation',
])]
#[Guarded(['id', 'scope'])]
class PublicContentSetting extends Model
{
    public const SCOPE_GENERAL = 'general';

    public const SCOPES = [self::SCOPE_GENERAL];

    private const REQUEST_CACHE_KEY = 'public_content_setting.general';

    protected $table = 'public_content_settings';

    public $incrementing = false;

    protected function casts(): array
    {
        return [
            'show_public_email' => 'boolean',
            'show_instagram' => 'boolean',
            'social_links' => 'array',
            'background_gradient_angle' => 'integer',
            'menu_background_gradient_angle' => 'integer',
            'page_background_gradient_angle' => 'integer',
            'header_name_gradient_angle' => 'integer',
            'header_name_gradient_stops' => 'array',
            'header_name_gradient_repeating' => 'boolean',
            'header_name_gradient_repeat_span' => 'integer',
            'header_name_gradient_center_x' => 'integer',
            'header_name_gradient_center_y' => 'integer',
            'header_divider_paint_gradient_angle' => 'integer',
            'header_divider_paint_gradient_stops' => 'array',
            'header_divider_paint_gradient_repeating' => 'boolean',
            'header_divider_paint_gradient_repeat_span' => 'integer',
            'header_divider_paint_gradient_center_x' => 'integer',
            'header_divider_paint_gradient_center_y' => 'integer',
            'background_gradient_stops' => 'array',
            'background_gradient_repeating' => 'boolean',
            'background_gradient_repeat_span' => 'integer',
            'background_gradient_center_x' => 'integer',
            'background_gradient_center_y' => 'integer',
            'menu_background_gradient_stops' => 'array',
            'menu_background_gradient_repeating' => 'boolean',
            'menu_background_gradient_repeat_span' => 'integer',
            'menu_background_gradient_center_x' => 'integer',
            'menu_background_gradient_center_y' => 'integer',
            'page_background_gradient_stops' => 'array',
            'page_background_gradient_repeating' => 'boolean',
            'page_background_gradient_repeat_span' => 'integer',
            'page_background_gradient_center_x' => 'integer',
            'page_background_gradient_center_y' => 'integer',
            'header_name_visible' => 'boolean',
            'header_name_size' => 'integer',
            'header_name_min_size' => 'integer',
            'header_name_auto_scale' => 'boolean',
            'public_page_width' => 'integer',
            'public_content_padding' => 'integer',
            'navigation_nesting_enabled' => 'boolean',
            'navigation_hover_preview' => 'boolean',
            'pages_auto_publish' => 'boolean',
            'pages_delete_without_confirmation' => 'boolean',
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
        if (app()->bound('request')) {
            $cached = request()->attributes->get(self::REQUEST_CACHE_KEY);
            if ($cached instanceof self) {
                return $cached;
            }
        }

        $setting = self::forScope(self::SCOPE_GENERAL);
        self::cacheGeneralForRequest($setting);

        return $setting;
    }

    public static function navigationNestingEnabled(): bool
    {
        $value = self::general()->getAttribute('navigation_nesting_enabled');

        return $value === null ? true : (bool) $value;
    }

    public static function pagesAutoPublish(): bool
    {
        return (bool) self::general()->getAttribute('pages_auto_publish');
    }

    public static function pagesDeleteWithoutConfirmation(): bool
    {
        return (bool) self::general()->getAttribute('pages_delete_without_confirmation');
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

        static::saved(function (self $setting): void {
            self::cacheGeneralForRequest($setting);
        });

        static::deleting(function (): never {
            throw new LogicException('Global public content settings cannot be deleted.');
        });
    }

    private static function cacheGeneralForRequest(self $setting): void
    {
        if (! app()->bound('request')) {
            return;
        }

        request()->attributes->set(self::REQUEST_CACHE_KEY, $setting);
    }

    private static function validateGeneral(self $setting): void
    {
        $region = $setting->getAttribute('regional_format');
        if (! is_string($region) || ! array_key_exists($region, RegionalFormats::OPTIONS)) {
            throw ValidationException::withMessages(['regional_format' => 'Choose a supported regional format.']);
        }

        foreach (PublicAppearance::SURFACE_PREFIXES as $surface) {
            $mode = $surface.'_mode';
            $setting->setAttribute($mode, PublicAppearance::normalizeMode($setting->getAttribute($mode), $mode));
            foreach (['_gradient_start', '_gradient_end'] as $suffix) {
                $field = $surface.$suffix;
                $setting->setAttribute($field, PublicAppearance::normalizeColor($setting->getAttribute($field), $field));
            }
            $colorField = PublicAppearance::solidColorField($surface);
            $setting->setAttribute($colorField, PublicAppearance::normalizeColor($setting->getAttribute($colorField), $colorField));
            $angle = $surface.'_gradient_angle';
            $setting->setAttribute($angle, PublicAppearance::normalizeAngle($setting->getAttribute($angle), $angle));
            foreach ([
                '_gradient_stops' => 'normalizeStops',
                '_gradient_repeating' => 'normalizeRepeating',
                '_gradient_repeat_span' => 'normalizeRepeatSpan',
                '_gradient_center_x' => 'normalizeCenter',
                '_gradient_center_y' => 'normalizeCenter',
                '_gradient_shape' => 'normalizeShape',
            ] as $suffix => $method) {
                $field = $surface.$suffix;
                $setting->setAttribute($field, PublicAppearance::$method($setting->getAttribute($field), $field));
            }
        }
        $setting->setAttribute('header_name_visible', $setting->getAttribute('header_name_visible') === null ? true : (bool) $setting->getAttribute('header_name_visible'));
        $setting->setAttribute('header_name_text', PublicAppearance::headerText($setting->getAttribute('header_name_text')));
        $setting->setAttribute('header_name_alignment', PublicAppearance::headerAlignment($setting->getAttribute('header_name_alignment')));
        $setting->setAttribute('header_name_mobile_alignment', PublicAppearance::headerAlignment($setting->getAttribute('header_name_mobile_alignment'), 'header_name_mobile_alignment'));
        $setting->setAttribute('header_name_position', PublicAppearance::headerPosition($setting->getAttribute('header_name_position')));
        $setting->setAttribute('header_name_caps', PublicAppearance::headerCaps($setting->getAttribute('header_name_caps')));
        $setting->setAttribute('header_name_color', PublicAppearance::normalizeColor($setting->getAttribute('header_name_color'), 'header_name_color'));
        $setting->setAttribute('header_name_size', PublicAppearance::normalizeTitleSize($setting->getAttribute('header_name_size')));
        $setting->setAttribute('header_name_min_size', PublicAppearance::normalizeTitleMinSize($setting->getAttribute('header_name_min_size')));
        $setting->setAttribute('frame_style', PublicAppearance::frameStyle($setting->getAttribute('frame_style')));
        $setting->setAttribute('menu_type', PublicAppearance::menuType($setting->getAttribute('menu_type')));
        $setting->setAttribute('header_divider_mode', PublicAppearance::headerDivider($setting->getAttribute('header_divider_mode')));
        $setting->setAttribute('header_name_auto_scale', $setting->getAttribute('header_name_auto_scale') === null ? true : (bool) $setting->getAttribute('header_name_auto_scale'));
        $setting->setAttribute('public_page_width', PublicAppearance::normalizePageWidth($setting->getAttribute('public_page_width')));
        $setting->setAttribute('public_content_padding', PublicAppearance::normalizeContentPadding($setting->getAttribute('public_content_padding')));

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
        // Only absent migrated data may inherit legacy Instagram. An explicit
        // empty list means that the admin deleted every General profile.
        if ($socialLinks === null && is_string($legacyInstagram) && trim($legacyInstagram) !== '') {
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

        foreach (\App\Domain\Content\PublicLegalContent::EDITORIAL_FIELDS as $field => $label) {
            $value = $setting->getAttribute($field);
            if ($value === null) {
                continue;
            }
            if (! is_string($value) || mb_strlen($value) > 20000) {
                throw ValidationException::withMessages([$field => $label.' must contain no more than 20,000 characters.']);
            }
            // Existing disclaimers may contain historical formatting. Editing
            // another General setting must not silently rewrite that text.
            if ($field === 'legal_disclaimer') {
                if (trim($value) === '') {
                    throw ValidationException::withMessages([$field => 'The existing legal note must contain text or be empty.']);
                }

                continue;
            }

            $normalized = trim($value);
            $setting->setAttribute($field, $normalized === '' ? null : $normalized);
        }
    }
}
