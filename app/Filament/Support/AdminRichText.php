<?php

namespace App\Filament\Support;

use App\Domain\Content\RichTextDocument;
use Filament\Forms\Components\RichEditor;
use Filament\Forms\Components\RichEditor\ToolbarButtonGroup;

final class AdminRichText
{
    /** One visual editor shared by Journal, Custom Pages, Home and nested text. */
    public static function schema(
        string $name,
        string $label,
        ?int $maxLength = null,
        bool $allowEmbeddedMedia = true,
    ): array {
        $toolbar = [
            ['bold', 'italic', 'underline', 'strike', 'subscript', 'superscript', 'link'],
            [ToolbarButtonGroup::make('Paragraph', ['paragraph', 'h1', 'h2', 'h3', 'h4'])->textualButtons()],
            [ToolbarButtonGroup::make('Alignment', ['alignStart', 'alignCenter', 'alignEnd', 'alignJustify'])],
            ['blockquote', 'bulletList', 'orderedList', 'horizontalRule'],
            ['table', 'highlight', 'textColor', 'clearFormatting'],
        ];
        if ($allowEmbeddedMedia) {
            $toolbar[] = ['insertStorageImage'];
            $toolbar[] = [
                ToolbarButtonGroup::make('Image position', [
                    'imageInline', 'imageLeft', 'imageCenter', 'imageRight',
                    'imageFloatleft', 'imageFloatright',
                ])->textualButtons(),
                'imageCaption',
            ];
        }
        $toolbar[] = ['undo', 'redo'];

        $editor = RichEditor::make($name)
            ->label($label)
            ->plugins([RichTextEditorPlugin::make()])
            ->formatStateUsing(static fn (mixed $state): mixed =>
                is_string($state) ? RichTextDocument::forEditor($state) : $state
            )
            ->toolbarButtons($toolbar)
            ->floatingToolbars($allowEmbeddedMedia ? [
                'image' => [
                    'imageInline', 'imageLeft', 'imageCenter', 'imageRight',
                    'imageFloatleft', 'imageFloatright', 'imageCaption',
                ],
                'table' => [
                    'tableAddColumnBefore', 'tableAddColumnAfter', 'tableDeleteColumn',
                    'tableAddRowBefore', 'tableAddRowAfter', 'tableDeleteRow',
                    'tableMergeCells', 'tableSplitCell', 'tableDelete',
                ],
            ] : null)
            ->linkProtocols(['http', 'https'])
            ->nullable()
            ->columnSpanFull()
            ->extraFieldWrapperAttributes(['class' => 'admin-rich-text admin-rich-text--visual']);

        if ($allowEmbeddedMedia) {
            $editor->resizableImages();
        }

        if ($maxLength !== null) {
            $editor->maxLength($maxLength);
        }

        // Commit through the owning Edit dialog's existing change boundary.
        // Never auto-save on every Tiptap keystroke.
        $editor->extraAlpineAttributes([
            'x-on:focusout' => "if (!\$el.contains(\$event.relatedTarget) && \$el.closest('.admin-dialog--edit')) { \$el.dispatchEvent(new Event('change', { bubbles: true })); }",
        ]);

        return [$editor];
    }
}
