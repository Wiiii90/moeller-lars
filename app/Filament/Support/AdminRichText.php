<?php

namespace App\Filament\Support;

use App\Domain\Content\RichTextMediaReference;
use App\Models\MediaAsset;
use Filament\Forms\Components\MarkdownEditor;
use Filament\Forms\Components\TextInput;
use Illuminate\Validation\ValidationException;

final class AdminRichText
{
    /** @return list<mixed> */
    public static function schema(
        string $name,
        string $label,
        ?int $maxLength,
        bool $nullable = true,
        bool $allowEmbeddedMedia = true,
    ): array {
        $mediaField = '__'.$name.'_media_asset_id';
        $mediaAltField = '__'.$name.'_media_alt_text';

        $editor = MarkdownEditor::make($name)
            ->label($label)
            ->toolbarButtons([
                ['bold', 'italic', 'link'],
                ['heading'],
                ['blockquote', 'bulletList', 'orderedList'],
                ['undo', 'redo'],
            ])
            ->columnSpanFull();

        if ($maxLength !== null) {
            $editor->maxLength($maxLength);
        }
        if ($nullable) {
            $editor->nullable();
        }
        if (! $allowEmbeddedMedia) {
            return [$editor];
        }

        $mediaAlt = TextInput::make($mediaAltField)
            ->label('ALT override for next embedded image')
            ->maxLength(500)
            ->nullable()
            ->dehydrated(false)
            ->columnSpanFull()
            ->helperText('Optional. Leave empty to use the canonical ALT text from Media Files.');

        $media = MediaAssetSelect::makeId($mediaField, 'Embed image from Media Files', imagesOnly: true)
            ->dehydrated(false)
            ->live()
            ->columnSpanFull()
            ->helperText('Choose an image from Media Files. It is inserted using the canonical media:<id> Rich Text reference.')
            ->afterStateUpdated(function (mixed $state, callable $get, callable $set) use ($mediaAltField, $mediaField, $name): void {
                if (! is_numeric($state)) {
                    return;
                }

                /** @var MediaAsset|null $asset */
                $asset = MediaAsset::query()
                    ->whereKey((int) $state)
                    ->where('state', 'available')
                    ->where('mime_type', 'like', 'image/%')
                    ->first();

                if (! $asset instanceof MediaAsset) {
                    throw ValidationException::withMessages([
                        $mediaField => 'Choose an available image from Media Files.',
                    ]);
                }

                $alt = $get($mediaAltField);
                $alt = is_string($alt) && trim($alt) !== '' ? trim($alt) : null;
                $current = $get($name);
                $current = is_string($current) ? rtrim($current) : '';
                $reference = RichTextMediaReference::markdown((int) $asset->getKey(), $alt);

                $set($name, $current.($current === '' ? '' : "\n\n").$reference);
                $set($mediaAltField, null);
                $set($mediaField, null);
            });

        return [$editor, $mediaAlt, $media];
    }
}
