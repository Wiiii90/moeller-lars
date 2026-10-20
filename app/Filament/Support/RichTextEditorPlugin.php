<?php

namespace App\Filament\Support;

use App\Domain\Content\ArtistImageLayout;
use App\Domain\Media\MediaPreviewVariant;
use App\Models\MediaAsset;
use Filament\Actions\Action;
use Filament\Forms\Components\RichEditor;
use Filament\Forms\Components\RichEditor\EditorCommand;
use Filament\Forms\Components\RichEditor\FileAttachmentProviders\Contracts\FileAttachmentProvider;
use Filament\Forms\Components\RichEditor\Plugins\Contracts\HasFileAttachmentProvider;
use Filament\Forms\Components\RichEditor\Plugins\Contracts\RichContentPlugin;
use Filament\Forms\Components\RichEditor\RichEditorTool;
use Filament\Forms\Components\TextInput;
use Illuminate\Support\Facades\Vite;

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
        return [new ArtistImageLayout];
    }

    public function getTipTapJsExtensions(): array
    {
        return [Vite::asset('resources/js/artist-rich-image.js')];
    }

    public function getEditorTools(): array
    {
        return [
            RichEditorTool::make('insertStorageImage')
                ->icon(AdminIcon::Storage->value)
                ->action(),
            ...array_map(
                static fn (string $placement): RichEditorTool =>
                    RichEditorTool::make('image'.str_replace('-', '', ucfirst($placement)))
                        ->label(match ($placement) {
                            'inline' => 'Inline image',
                            'left' => 'Align image left',
                            'center' => 'Center image',
                            'right' => 'Align image right',
                            'float-left' => 'Wrap text on right',
                            'float-right' => 'Wrap text on left',
                        })
                        ->icon(match ($placement) {
                            'inline' => 'heroicon-o-squares-2x2',
                            'left' => 'heroicon-o-bars-3-bottom-left',
                            'center' => 'heroicon-o-bars-3',
                            'right' => 'heroicon-o-bars-3-bottom-right',
                            'float-left' => 'heroicon-o-bars-arrow-down',
                            'float-right' => 'heroicon-o-bars-arrow-up',
                        })
                        ->disabledWhenNotActive()
                        ->activeJsExpression("window.ArtistRichImage?.hasSelection(\$getEditor()) ?? false")
                        ->jsHandler("window.ArtistRichImage?.setPlacement(\$getEditor(), '{$placement}')"),
                ArtistImageLayout::PLACEMENTS,
            ),
            RichEditorTool::make('imageCaption')
                ->label('Image caption')
                ->icon(AdminIcon::Details->value)
                ->disabledWhenNotActive()
                ->activeJsExpression("window.ArtistRichImage?.hasSelection(\$getEditor()) ?? false")
                ->action(arguments: "{ caption: \$getEditor()?.getAttributes('image').artistCaption ?? '' }"),

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
            Action::make('imageCaption')
                ->modalHeading('Image caption')
                ->fillForm(fn (array $arguments): array => ['caption' => $arguments['caption'] ?? ''])
                ->schema([
                    TextInput::make('caption')->label('Caption')->maxLength(500)->nullable(),
                ])
                ->action(function (array $arguments, array $data, RichEditor $component): void {
                    $component->runCommands(
                        [EditorCommand::make('updateAttributes', arguments: [
                            'image', ['artistCaption' => trim((string) ($data['caption'] ?? '')) ?: null],
                        ])],
                        editorSelection: $arguments['editorSelection'],
                    );
                }),
        ];
    }
}
