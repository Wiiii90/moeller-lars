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
            ->schema([
                Hidden::make('embed_key')->default(fn (): string => (string) Str::uuid()),
                MediaAssetSelect::makeId('media_asset_id', 'Image', imagesOnly: true)->required(),
                TextInput::make('alt_text_override')->label('ALT override')->maxLength(500)->nullable(),
            ]);
    }

    public static function getPreviewLabel(array $config): string
    {
        $asset = self::asset($config);
        return $asset instanceof MediaAsset ? (string) $asset->getAttribute('original_filename') : 'Inline image';
    }

    public static function toPreviewHtml(array $config): string
    {
        $asset = self::asset($config);
        if (! $asset instanceof MediaAsset) {
            return '<p>Selected image is unavailable.</p>';
        }

        $variant = $asset->variants->first(fn (MediaVariant $candidate): bool => $candidate->getAttribute('variant_kind') === PublicMedia::THUMBNAIL_KIND
            && $candidate->getAttribute('transform_profile') === PublicMedia::PUBLIC_TRANSFORM_PROFILE
            && $candidate->getAttribute('state') === 'available');
        $filename = e((string) $asset->getAttribute('original_filename'));
        if (! $variant instanceof MediaVariant) {
            return '<p><strong>'.$filename.'</strong><br><small>Preview pending</small></p>';
        }

        return '<figure class="journal-entry-editor__inline-preview">'
            .'<img src="'.e(route('admin.media.variant', $variant)).'" alt="" loading="lazy">'
            .'<figcaption>'.$filename.'</figcaption>'
            .'</figure>';
    }

    private static function asset(array $config): ?MediaAsset
    {
        $id = filter_var($config['media_asset_id'] ?? null, FILTER_VALIDATE_INT);
        if ($id === false || $id <= 0) {
            return null;
        }

        return MediaAsset::query()->whereKey((int) $id)->where('state', 'available')->with('variants')->first();
    }
}
