<?php

namespace App\Filament\Support;

use App\Domain\Content\RichTextMediaReference;
use App\Models\MediaAsset;
use Filament\Forms\Components\MarkdownEditor;
use Illuminate\Validation\ValidationException;

final class AdminRichText
{
    /** @return list<mixed> */
    public static function schema(
        string $name,
        string $label,
        int $maxLength,
        bool $nullable = true,
    ): array {
        $mediaField = '__'.$name.'_media_asset_id';

        $editor = MarkdownEditor::make($name)
            ->label($label)
            ->toolbarButtons([
                ['bold', 'italic', 'link'],
                ['heading'],
                ['blockquote', 'bulletList', 'orderedList'],
                ['undo', 'redo'],
            ])
            ->maxLength($maxLength)
            ->columnSpanFull();

        if ($nullable) {
            $editor->nullable();
        }

        $media = MediaAssetSelect::makeId($mediaField, 'Embed image from Media Files', imagesOnly: true)
            ->dehydrated(false)
            ->live()
            ->columnSpanFull()
            ->helperText('Choose an image from Media Files. It is inserted into this text using its canonical Media reference.')
            ->afterStateUpdated(function (mixed $state, callable $get, callable $set) use ($mediaField, $name): void {
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

                $current = $get($name);
                $current = is_string($current) ? rtrim($current) : '';
                $reference = RichTextMediaReference::markdown((int) $asset->getKey());

                if (! str_contains($current, $reference)) {
                    $set($name, $current.($current === '' ? '' : "\n\n").$reference);
                }

                $set($mediaField, null);
            });

        return [$editor, $media];
    }
}
