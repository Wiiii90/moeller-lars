<?php

namespace App\Models;

use App\Domain\Content\SafeLinkPolicy;
use App\Domain\Content\SafeRichTextRenderer;
use App\Domain\Content\SocialLinks;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Guarded;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Validation\ValidationException;

#[Fillable(['blocks'])]
#[Guarded(['id', 'site_section_id'])]
final class CustomPageSetting extends Model
{
    protected function casts(): array
    {
        return [
            'blocks' => 'array',
        ];
    }

    protected static function booted(): void
    {
        static::saving(function (self $settings): void {
            $settings->validateBlocks(requirePublicMedia: false);
        });
    }

    /** @return BelongsTo<SiteSection, $this> */
    public function siteSection(): BelongsTo
    {
        return $this->belongsTo(SiteSection::class);
    }

    public function assertReadyForPublic(): void
    {
        $this->validateBlocks(requirePublicMedia: true);
    }

    private function validateBlocks(bool $requirePublicMedia): void
    {
        $blocks = $this->getAttribute('blocks');
        if (! is_array($blocks) || ! array_is_list($blocks)) {
            throw ValidationException::withMessages(['blocks' => 'Page components must be an ordered list.']);
        }

        foreach ($blocks as $blockIndex => $block) {
            if (! is_array($block)) {
                throw ValidationException::withMessages(['blocks' => 'Each page component must be structured data.']);
            }

            $type = $block['type'] ?? null;
            if (! is_string($type) || ! in_array($type, ['text', 'list', 'contact'], true)) {
                throw ValidationException::withMessages(['blocks' => 'A page component has an unsupported type.']);
            }

            if (array_key_exists('divider', $block) && ! is_bool($block['divider'])) {
                throw ValidationException::withMessages(['blocks' => 'Component divider settings must be boolean.']);
            }

            if ($type === 'text') {
                $this->validateRichText($block['body'] ?? null, 'blocks.'.$blockIndex.'.body');
            }

            if ($type === 'list') {
                $items = $block['items'] ?? [];
                if (! is_array($items) || ! array_is_list($items)) {
                    throw ValidationException::withMessages(['blocks' => 'List component entries must be ordered.']);
                }
                foreach ($items as $itemIndex => $item) {
                    if (! is_array($item)) {
                        throw ValidationException::withMessages(['blocks' => 'A list component entry is invalid.']);
                    }
                    if (array_key_exists('visible', $item) && ! is_bool($item['visible'])) {
                        throw ValidationException::withMessages(['blocks' => 'List entry visibility must be boolean.']);
                    }
                    $title = $item['title'] ?? null;
                    if (! is_string($title) || trim($title) === '' || mb_strlen($title) > 240) {
                        throw ValidationException::withMessages(['blocks' => 'Each list entry requires a short title.']);
                    }
                    $this->validateRichText($item['body'] ?? null, 'blocks.'.$blockIndex.'.items.'.$itemIndex.'.body');
                    $this->validateUrl($item['url'] ?? null, 'blocks.'.$blockIndex.'.items.'.$itemIndex.'.url');
                }
            }

            if ($type === 'contact') {
                foreach (['show_email', 'show_form'] as $toggle) {
                    if (array_key_exists($toggle, $block) && ! is_bool($block[$toggle])) {
                        throw ValidationException::withMessages(['blocks' => 'Contact component toggles must be boolean.']);
                    }
                }
                $platforms = $block['social_platforms'] ?? [];
                if (! is_array($platforms) || ! array_is_list($platforms)) {
                    throw ValidationException::withMessages(['blocks' => 'Contact social links must be an ordered selection.']);
                }
                foreach ($platforms as $platform) {
                    if (! is_string($platform) || ! SocialLinks::supports($platform)) {
                        throw ValidationException::withMessages(['blocks' => 'A Contact component references an unsupported social platform.']);
                    }
                }
            }

            $mediaId = $block['media_asset_id'] ?? null;
            if ($mediaId !== null) {
                if (filter_var($mediaId, FILTER_VALIDATE_INT) === false) {
                    throw ValidationException::withMessages(['blocks' => 'A component image reference is invalid.']);
                }

                /** @var MediaAsset|null $asset */
                $asset = MediaAsset::query()->find((int) $mediaId);
                if (! $asset instanceof MediaAsset || ! str_starts_with((string) $asset->getAttribute('mime_type'), 'image/')) {
                    throw ValidationException::withMessages(['blocks' => 'A component image must reference an image from Files.']);
                }

                if ($requirePublicMedia) {
                    $alt = $asset->getAttribute('alt_text');
                    if ((string) $asset->getAttribute('state') !== 'available' || ! is_string($alt) || trim($alt) === '') {
                        throw ValidationException::withMessages(['blocks' => 'Published custom-page images must be available and have canonical ALT text.']);
                    }
                }
            }
        }
    }

    private function validateRichText(mixed $value, string $field): void
    {
        if ($value === null || $value === '') {
            return;
        }
        if (! is_string($value)) {
            throw ValidationException::withMessages([$field => 'Component rich text must be text.']);
        }

        app(SafeRichTextRenderer::class)->assertValid($value);
    }

    private function validateUrl(mixed $value, string $field): void
    {
        if ($value === null || $value === '') {
            return;
        }
        if (! is_string($value)
            || ! in_array(strtolower((string) parse_url($value, PHP_URL_SCHEME)), ['http', 'https'], true)
            || ! app(SafeLinkPolicy::class)->isAllowed($value)) {
            throw ValidationException::withMessages([$field => 'Component links must be valid HTTP or HTTPS URLs.']);
        }
    }
}
