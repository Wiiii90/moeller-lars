<?php

namespace App\Filament\Support;

use App\Domain\Content\PdfTextCleaner;
use App\Domain\Media\MediaPreviewVariant;
use App\Models\MediaAsset;
use Filament\Actions\Action;
use Filament\Forms\Components\RichEditor;
use Filament\Forms\Components\RichEditor\EditorCommand;
use Filament\Forms\Components\RichEditor\FileAttachmentProviders\Contracts\FileAttachmentProvider;
use Filament\Forms\Components\RichEditor\Plugins\Contracts\HasFileAttachmentProvider;
use Filament\Forms\Components\RichEditor\Plugins\Contracts\RichContentPlugin;
use Filament\Forms\Components\RichEditor\RichEditorTool;
use Filament\Forms\Components\Textarea;

final class RichTextEditorPlugin implements RichContentPlugin, HasFileAttachmentProvider
{
    public static function make(): static
    {
        return new static;
    }

    public function getFileAttachmentProvider(): ?FileAttachmentProvider
    {
        return new MediaRichTextAttachmentProvider;
    }

    public function getTipTapPhpExtensions(): array
    {
        return [];
    }

    public function getTipTapJsExtensions(): array
    {
        return [];
    }

    public function getEditorTools(): array
    {
        return [
            RichEditorTool::make('insertStorageImage')
                ->icon(AdminIcon::Storage->value)
                ->action(),
            RichEditorTool::make('cleanPdfText')
                ->icon(AdminIcon::Clear->value)
                ->activeJsExpression('$getEditor()?.state.selection.from !== $getEditor()?.state.selection.to')
                ->disabledWhenNotActive()
                ->action(arguments: "{ selectedText: \$getEditor()?.state.doc.textBetween(\$getEditor()?.state.selection.from, \$getEditor()?.state.selection.to, '\\n') }"),
        ];
    }

    public function getEditorActions(): array
    {
        return [
            Action::make('insertStorageImage')
                ->modalHeading('Insert image from Storage')
                ->schema([
                    MediaAssetSelect::makeId('media_asset_id', 'Image', imagesOnly: true)->required(),
                ])
                ->action(function (array $arguments, array $data, RichEditor $component): void {
                    $id = (int) ($data['media_asset_id'] ?? 0);
                    $asset = MediaAsset::query()
                        ->whereKey($id)
                        ->where('state', 'available')
                        ->where('mime_type', 'like', 'image/%')
                        ->firstOrFail();
                    $variant = MediaPreviewVariant::imageThumbnail($asset);
                    if ($variant === null) {
                        return;
                    }
                    $component->runCommands(
                        [EditorCommand::make('insertContent', arguments: [[
                            'type' => 'image',
                            'attrs' => [
                                'id' => (string) $id,
                                'src' => route('admin.media.variant', $variant),
                                'alt' => $asset->getAttribute('alt_text') ?: '',
                            ],
                        ]])],
                        editorSelection: $arguments['editorSelection'],
                    );
                }),
            Action::make('cleanPdfText')
                ->modalHeading('Clean copied PDF text')
                ->fillForm(fn (array $arguments): array => [
                    'result' => PdfTextCleaner::clean((string) ($arguments['selectedText'] ?? '')),
                ])
                ->schema([
                    Textarea::make('result')
                        ->label('Review the corrected paragraphs')
                        ->rows(12)
                        ->required(),
                ])
                ->action(function (array $arguments, array $data, RichEditor $component): void {
                    $component->runCommands(
                        [EditorCommand::make('insertContent', arguments: [(string) $data['result']])],
                        editorSelection: $arguments['editorSelection'],
                    );
                }),
        ];
    }
}
