<?php

namespace App\Filament\Support;

use App\Domain\Content\JournalEntryContent;
use App\Domain\Media\PublicMedia;
use App\Models\MediaAsset;
use App\Models\MediaVariant;
use Filament\Actions\Action;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\RichEditor\RichContentCustomBlock;
use Filament\Forms\Components\TextInput;
use Illuminate\Support\Str;

final class JournalInlineImageBlock extends RichContentCustomBlock
{
    private const PREVIEW_CACHE = 'admin.journal_inline_image.preview';

    public static function getId(): string
    {
        return JournalEntryContent::INLINE_IMAGE_BLOCK_ID;
    }

    public static function getLabel(): string
    {
        return 'Insert image';
    }

    public static function configureEditorAction(Action $action): Action
    {
        return $action
            ->modalHeading('Insert image')
            ->modalSubmitActionLabel('Insert image')
            ->modalCancelActionLabel('Cancel')
            ->schema([
                Hidden::make('embed_key')->default(fn (): string => (string) Str::uuid()),
                MediaAssetSelect::makeId('media_asset_id', 'Image', imagesOnly: true)->required(),
                TextInput::make('alt_text_override')->label('ALT override')->maxLength(500)->nullable(),
            ]);
    }

    public static function getPreviewLabel(array $config): string
    {
        $preview = self::preview($config);

        return $preview['label'] ?? 'Inline image';
    }

    public static function toPreviewHtml(array $config): string
    {
        $preview = self::preview($config);
        $label = e($preview['label'] ?? 'Inline image');
        $url = $preview['url'] ?? null;

        if (! is_string($url) || $url === '') {
            return '<p><strong>'.$label.'</strong><br><small>Preview pending</small></p>';
        }

        return '<figure class="journal-entry-editor__inline-preview">'
            .'<img src="'.e($url).'" alt="" loading="lazy" decoding="async">'
            .'<figcaption>'.$label.'</figcaption>'
            .'</figure>';
    }

    /** @return array{label:string,url:?string} */
    private static function preview(array $config): array
    {
        $providedLabel = trim((string) ($config['preview_label'] ?? ''));
        $providedUrl = trim((string) ($config['preview_url'] ?? ''));
        if ($providedLabel !== '') {
            return ['label' => $providedLabel, 'url' => $providedUrl !== '' ? $providedUrl : null];
        }

        $id = filter_var($config['media_asset_id'] ?? null, FILTER_VALIDATE_INT);
        if ($id === false || $id <= 0) {
            return ['label' => 'Inline image', 'url' => null];
        }

        $cache = request()->attributes->get(self::PREVIEW_CACHE, []);
        if (is_array($cache) && isset($cache[(int) $id]) && is_array($cache[(int) $id])) {
            return $cache[(int) $id];
        }

        $asset = MediaAsset::query()->whereKey((int) $id)->where('state', 'available')->with('variants')->first();
        if (! $asset instanceof MediaAsset) {
            $preview = ['label' => 'Inline image', 'url' => null];
        } else {
            MediaAssetSelect::primeOptionLabel($asset);
            $variant = $asset->getRelationValue('variants')->first(
                fn (MediaVariant $candidate): bool => $candidate->getAttribute('variant_kind') === PublicMedia::THUMBNAIL_KIND
                    && $candidate->getAttribute('transform_profile') === PublicMedia::PUBLIC_TRANSFORM_PROFILE
                    && $candidate->getAttribute('state') === 'available',
            );
            $preview = [
                'label' => (string) $asset->getAttribute('original_filename'),
                'url' => $variant instanceof MediaVariant ? route('admin.media.variant', $variant) : null,
            ];
        }

        $cache = is_array($cache) ? $cache : [];
        $cache[(int) $id] = $preview;
        request()->attributes->set(self::PREVIEW_CACHE, $cache);

        return $preview;
    }
}
