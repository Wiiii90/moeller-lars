<?php

namespace App\Filament\Support;

use App\Domain\Content\RichTextMediaReference;
use App\Models\MediaAsset;
use Filament\Actions\Action;
use Filament\Forms\Components\MarkdownEditor;
use Filament\Schemas\Components\Component;
use Illuminate\Validation\ValidationException;

final class AdminRichText
{
    /** @return array<int, Component> */
    public static function schema(
        string $name,
        string $label,
        ?int $maxLength = null,
        bool $allowEmbeddedMedia = true,
    ): array {
        $editor = MarkdownEditor::make($name)
            ->label($label)
            ->toolbarButtons([
                ['bold', 'italic', 'link'],
                ['bulletList', 'orderedList'],
                ['undo', 'redo'],
            ])
            ->nullable()
            ->columnSpanFull()
            ->extraFieldWrapperAttributes([
                'class' => 'admin-rich-text',
                'style' => 'position:relative',
            ]);

        if ($maxLength !== null) {
            $editor->maxLength($maxLength);
        }

        if ($allowEmbeddedMedia) {
            $editor->aboveContent(
                Action::make('insertRichTextMedia_'.substr(sha1($name), 0, 12))
                    ->label('Insert image from Media Files')
                    ->icon('heroicon-o-photo')
                    ->iconButton()
                    ->tooltip('Insert image from Media Files')
                    ->extraAttributes([
                        'class' => 'admin-rich-text__media-action',
                        'style' => 'position:absolute;right:.55rem;top:2.55rem;z-index:3',
                    ])
                    ->modalHeading('Insert image from Media Files')
                    ->modalSubmitActionLabel('Insert image')
                    ->modalCancelActionLabel('Cancel')
                    ->schema([
                        MediaAssetSelect::makeId('media_asset_id', 'Image', imagesOnly: true)->required(),
                    ])
                    ->action(function (array $data, Component $schemaComponent, mixed $schemaComponentState): void {
                        $id = filter_var($data['media_asset_id'] ?? null, FILTER_VALIDATE_INT);
                        if ($id === false || $id <= 0) {
                            throw ValidationException::withMessages(['media_asset_id' => 'Choose an image from Media Files.']);
                        }

                        $valid = MediaAsset::query()
                            ->whereKey((int) $id)
                            ->where('state', 'available')
                            ->where('mime_type', 'like', 'image/%')
                            ->exists();
                        if (! $valid) {
                            throw ValidationException::withMessages(['media_asset_id' => 'Choose an available image from Media Files.']);
                        }

                        $current = trim(is_string($schemaComponentState) ? $schemaComponentState : '');
                        $reference = RichTextMediaReference::markdown((int) $id);
                        $schemaComponent->state($current === '' ? $reference : $current."\n\n".$reference);
                    }),
            );
        }

        return [$editor];
    }
}
