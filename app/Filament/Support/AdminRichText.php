<?php

namespace App\Filament\Support;

use App\Domain\Content\RichTextDocument;
use App\Domain\Media\MediaPreviewVariant;
use App\Models\MediaAsset;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\RichEditor\EditorCommand;
use Filament\Schemas\Components\Grid;
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
            [ToolbarButtonGroup::make(__('Paragraph'), ['paragraph', 'h1', 'h2', 'h3', 'h4'])->textualButtons()],
            [ToolbarButtonGroup::make(__('Alignment'), ['alignStart', 'alignCenter', 'alignEnd', 'alignJustify'])],
            ['blockquote', 'bulletList', 'orderedList', 'horizontalRule'],
            ['table', 'highlight', 'textColor', 'clearFormatting'],
        ];
        if ($allowEmbeddedMedia) {
            $toolbar[] = ['insertStorageImage'];
            $toolbar[] = [
                ToolbarButtonGroup::make(__('Image position'), [
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
            // Disables button, drop/paste and Livewire temporary uploads;
            // only the canonical existing-Storage picker may insert images.
            ->fileAttachments(false)
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

        if (! $allowEmbeddedMedia) {
            return [$editor];
        }

        $editorKey = 'artist-rich-text-'.$name;
        $editor->key($editorKey);

        $picker = MediaAssetSelect::makeId(
            '__rich_image_'.$name,
            'Image from Storage',
            imagesOnly: true,
        )
            ->nullable()
            ->dehydrated(false)
            ->live()
            ->afterStateUpdated(static function (mixed $state, Select $field) use ($editorKey): void {
                $id = filter_var($state, FILTER_VALIDATE_INT);
                if ($id === false || $id <= 0) {
                    return;
                }

                $asset = MediaAsset::query()
                    ->whereKey($id)
                    ->where('state', 'available')
                    ->where('mime_type', 'like', 'image/%')
                    ->with('variants')
                    ->firstOrFail();
                $variant = MediaPreviewVariant::imageThumbnail($asset);
                if ($variant === null) {
                    return;
                }

                $target = $field->getRootContainer()->getComponent(
                    static fn (mixed $candidate): bool =>
                        $candidate instanceof RichEditor && $candidate->getKey() === $editorKey,
                    withHidden: true,
                );
                if (! $target instanceof RichEditor) {
                    return;
                }

                $target->runCommands([
                    EditorCommand::make('insertContent', arguments: [[
                        'type' => 'image',
                        'attrs' => [
                            'id' => (string) $id,
                            'src' => route('admin.media.variant', $variant),
                            'alt' => (string) ($asset->getAttribute('alt_text') ?: ''),
                        ],
                    ]]),
                ]);

                $field->state(null);
                $field->getLivewire()->dispatch('artist-rich-image-inserted');
            });

        return [
            Grid::make()
                ->columns(1)
                ->schema([
                    $editor,
                    Grid::make()
                        ->columns(1)
                        ->schema([$picker])
                        ->extraAttributes([
                            'x-show' => 'imagePickerOpen',
                            'x-cloak' => true,
                            'class' => 'admin-rich-text-inline-picker',
                        ])
                        ->columnSpanFull(),
                ])
                ->extraAttributes([
                    'x-data' => '{ imagePickerOpen: false }',
                    'x-on:artist-rich-image-toggle' => 'imagePickerOpen = !imagePickerOpen',
                    'x-on:artist-rich-image-inserted.window' => 'imagePickerOpen = false',
                ])
                ->columnSpanFull(),
        ];
    }
}
