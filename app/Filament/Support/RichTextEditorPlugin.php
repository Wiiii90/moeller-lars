<?php

namespace App\Filament\Support;

use App\Domain\Content\ArtistImageLayout;
use App\Domain\Content\ArtistTextSpacing;
use Filament\Forms\Components\RichEditor\FileAttachmentProviders\Contracts\FileAttachmentProvider;
use Filament\Forms\Components\RichEditor\Plugins\Contracts\HasFileAttachmentProvider;
use Filament\Forms\Components\RichEditor\Plugins\Contracts\RichContentPlugin;
use Filament\Forms\Components\RichEditor\RichEditorTool;
use Illuminate\Support\Facades\Vite;

final class RichTextEditorPlugin implements RichContentPlugin, HasFileAttachmentProvider
{
    private const TABLE_COMMANDS = [
        'table' => '$getEditor()?.commands.insertTable({ rows: 2, cols: 3, withHeaderRow: true })',
        'tableAddColumnBefore' => '$getEditor()?.chain().focus().addColumnBefore().run()',
        'tableAddColumnAfter' => '$getEditor()?.chain().focus().addColumnAfter().run()',
        'tableDeleteColumn' => '$getEditor()?.chain().focus().deleteColumn().run()',
        'tableAddRowBefore' => '$getEditor()?.chain().focus().addRowBefore().run()',
        'tableAddRowAfter' => '$getEditor()?.chain().focus().addRowAfter().run()',
        'tableDeleteRow' => '$getEditor()?.chain().focus().deleteRow().run()',
        'tableMergeCells' => '$getEditor()?.chain().focus().mergeCells().run()',
        'tableSplitCell' => '$getEditor()?.chain().focus().splitCell().run()',
        'tableToggleHeaderRow' => '$getEditor()?.chain().focus().toggleHeaderRow().run()',
        'tableToggleHeaderCell' => '$getEditor()?.chain().focus().toggleHeaderCell().run()',
        'tableDelete' => '$getEditor()?.chain().focus().deleteTable().run()',
    ];
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
        return [new ArtistImageLayout, new ArtistTextSpacing];
    }

    public function getTipTapJsExtensions(): array
    {
        return [
            Vite::asset('resources/js/artist-rich-image.js'),
            Vite::asset('resources/js/artist-rich-spacing.js'),
        ];
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
                ->activeStyling(false)
                ->jsHandler("const editor = \$getEditor(); if (editor) editor.view.dom.dispatchEvent(new CustomEvent('artist-rich-link-toggle', { detail: { editor, anchor: \$event.currentTarget }, bubbles: true }))"),
            RichEditorTool::make('textColor')
                ->label(__('Text color'))
                ->icon('heroicon-o-swatch')
                ->activeStyling(false)
                ->jsHandler("const editor = \$getEditor(); if (editor) editor.view.dom.dispatchEvent(new CustomEvent('artist-rich-color-toggle', { detail: { editor, anchor: \$event.currentTarget }, bubbles: true }))"),
            RichEditorTool::make('lineSpacing')
                ->label(__('Line spacing'))
                ->icon('heroicon-o-bars-3-bottom-left')
                ->activeStyling(false)
                ->jsHandler("const editor = \$getEditor(); if (editor) editor.view.dom.dispatchEvent(new CustomEvent('artist-rich-spacing-toggle', { detail: { editor, anchor: \$event.currentTarget }, bubbles: true }))"),
            ...array_map(
                static function (string $name, string $command): RichEditorTool {
                    $suffix = \Illuminate\Support\Str::snake($name);

                    return RichEditorTool::make($name)
                        ->label(__('filament-forms::components.rich_editor.tools.'.$suffix))
                        ->icon('fi-o-'.\Illuminate\Support\Str::kebab($name))
                        ->jsHandler("if ({$command}) \$dispatch('artist-rich-content-changed')");
                },
                array_keys(self::TABLE_COMMANDS),
                array_values(self::TABLE_COMMANDS),
            ),
            RichEditorTool::make('insertStorageImage')
                ->label(__('Insert image from Storage'))
                ->icon(AdminIcon::Storage->value)
                ->activeStyling(false)
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
                        ->activeJsExpression("\$getEditor()?.isActive('image', { artistPlacement: '{$placement}' }) ?? false")
                        ->jsHandler("const editor = \$getEditor(); if (editor) { const updated = editor.storage.artistImageLayout.setPlacement(editor, '{$placement}'); if (updated) editor.view.dom.dispatchEvent(new CustomEvent('artist-rich-image-changed', { bubbles: true })); }"),
                ArtistImageLayout::PLACEMENTS,
            ),
            RichEditorTool::make('imageCaption')
                ->label(__('Image caption'))
                ->icon(AdminIcon::Details->value)
                ->jsHandler("const editor = \$getEditor(); if (editor) editor.view.dom.dispatchEvent(new CustomEvent('artist-rich-caption-toggle', { detail: { editor, anchor: \$event.currentTarget }, bubbles: true }))"),

        ];
    }

    public function getEditorActions(): array
    {
        return [];
    }
}
