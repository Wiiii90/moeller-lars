<?php

namespace App\Filament\Support;

use App\Domain\Content\ArtistImageLayout;
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
                ->label(__('Insert image from Storage'))
                ->icon(AdminIcon::Storage->value)
                ->jsHandler("\$dispatch('artist-rich-image-toggle')"),
            ...array_map(
                static fn (string $placement): RichEditorTool =>
                    RichEditorTool::make('image'.str_replace('-', '', ucfirst($placement)))
                        ->label(match ($placement) {
                            'inline' => __('Inline image'),
                            'left' => __('Align image left'),
                            'center' => __('Center image'),
                            'right' => __('Align image right'),
                            'float-left' => __('Wrap text on right'),
                            'float-right' => __('Wrap text on left'),
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
                ->label(__('Image caption'))
                ->icon(AdminIcon::Details->value)
                ->disabledWhenNotActive()
                ->activeJsExpression("window.ArtistRichImage?.hasSelection(\$getEditor()) ?? false")
                ->action(arguments: "{ caption: \$getEditor()?.getAttributes('image').artistCaption ?? '' }"),

        ];
    }

    public function getEditorActions(): array
    {
        return [
            Action::make('imageCaption')
                ->modalHeading(__('Image caption'))
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
