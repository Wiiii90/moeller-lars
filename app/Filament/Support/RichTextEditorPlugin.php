<?php

namespace App\Filament\Support;

use App\Domain\Content\ArtistImageLayout;
use Filament\Forms\Components\RichEditor\FileAttachmentProviders\Contracts\FileAttachmentProvider;
use Filament\Forms\Components\RichEditor\Plugins\Contracts\HasFileAttachmentProvider;
use Filament\Forms\Components\RichEditor\Plugins\Contracts\RichContentPlugin;
use Filament\Forms\Components\RichEditor\RichEditorTool;
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
            // Override Filament's default 'link' tool. Its native LinkAction
            // opens a nested modal and breaks the owning autosave Edit dialog.
            RichEditorTool::make('link')
                ->label(__('Link'))
                ->icon('heroicon-o-link')
                ->toggle()
                ->jsHandler("\$dispatch('artist-rich-link-toggle', { editor: \$getEditor(), anchor: \$event.currentTarget })"),
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
                        ->activeStyling()
                        ->activeJsExpression("window.ArtistRichImage?.hasPlacement(\$getEditor(), '{$placement}') ?? false")
                        ->jsHandler("window.ArtistRichImage?.setPlacement(\$getEditor(), '{$placement}')"),
                ArtistImageLayout::PLACEMENTS,
            ),
            RichEditorTool::make('imageCaption')
                ->label(__('Image caption'))
                ->icon(AdminIcon::Details->value)
                ->jsHandler("\$dispatch('artist-rich-caption-toggle', { editor: \$getEditor(), anchor: \$event.currentTarget })"),

        ];
    }

    public function getEditorActions(): array
    {
        return [];
    }
}
