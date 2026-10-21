<?php

namespace App\Filament\Support;

use App\Domain\Content\RichTextDocument;
use App\Domain\Media\MediaPreviewVariant;
use App\Models\MediaAsset;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\RichEditor\EditorCommand;
use Filament\Schemas\Components\Grid;
use Filament\Forms\Components\RichEditor;

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
            ['paragraph', 'h1', 'h2', 'h3', 'h4'],
            ['alignStart', 'alignCenter', 'alignEnd', 'alignJustify'],
            ['blockquote', 'bulletList', 'orderedList', 'horizontalRule'],
            ['table', 'highlight', 'textColor', 'clearFormatting'],
        ];
        if ($allowEmbeddedMedia) {
            $toolbar[] = ['insertStorageImage'];
            $toolbar[] = [
                'imageInline', 'imageLeft', 'imageCenter', 'imageRight',
                'imageFloatleft', 'imageFloatright', 'imageCaption',
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

        // Filament 5.7.6 renders extraInputAttributes on the editor area,
        // but does not render extraAlpineAttributes in its embedded view.
        // For a bare editor, leaving the area must reach the canonical
        // Edit-dialog change handler. The media-enabled editor below binds
        // the same event to its whole shell, including tools and picker.
        if (! $allowEmbeddedMedia) {
            $editor->extraInputAttributes([
                'x-on:focusout' => <<<'JS'
                    if (!$el.closest('.admin-dialog--edit')) return;
                    if ($el.closest('.fi-fo-rich-editor')?.contains($event.relatedTarget)) return;
                    $el.dispatchEvent(new Event('change', { bubbles: true }));
                    JS,
            ]);

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
            ->afterStateUpdated(static function (mixed $state, Select $field) use ($editor): void {
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

                // Use the owning editor object from this shared schema.
                // The former root-schema search could resolve the wrong
                // component during Livewire morphs and break Edit dialogs.
                $editor->runCommands([
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
                            'x-bind:style' => '`top: ${pickerTop}px; left: ${pickerLeft}px`',
                            'class' => 'admin-rich-text-storage-dropdown',
                            'role' => 'group',
                            'aria-label' => __('Image from Storage'),
                        ])
                        ->columnSpanFull(),
                ])
                ->key($editorKey.'-shell')
                ->extraAttributes([
                    'class' => 'admin-rich-text-editor-shell',
                    'x-data' => <<<'JS'
                        {
                            imagePickerOpen: false,
                            pickerLeft: 0,
                            pickerTop: 0,
                            toggleImagePicker(event) {
                                if (this.imagePickerOpen) {
                                    this.imagePickerOpen = false
                                    return
                                }

                                const trigger = event.target.closest('.fi-fo-rich-editor-tool')
                                if (!trigger) return

                                const shell = this.$el.getBoundingClientRect()
                                const anchor = trigger.getBoundingClientRect()
                                const maxLeft = Math.max(0, shell.width - 320)
                                this.pickerLeft = Math.max(0, Math.min(anchor.left - shell.left, maxLeft))
                                this.pickerTop = anchor.bottom - shell.top + 4
                                this.imagePickerOpen = true
                                // Open Filament's own searchable Select immediately:
                                // one toolbar click goes straight to image search.
                                this.$nextTick(() => {
                                    this.$el.querySelector(
                                        '.admin-rich-text-storage-dropdown .fi-select-input-btn',
                                    )?.click()
                                })
                            },
                        }
                        JS,
                    // One blur boundary for editor, toolbar and image picker:
                    // do not autosave while switching between their controls.
                    'x-on:focusout' => <<<'JS'
                        if (!$el.closest('.admin-dialog--edit')) return;
                        if ($el.contains($event.relatedTarget)) return;
                        $el.dispatchEvent(new Event('change', { bubbles: true }));
                        JS,
                    'x-on:artist-rich-image-toggle' => 'toggleImagePicker($event)',
                    'x-on:artist-rich-image-inserted.window' => 'imagePickerOpen = false',
                    'x-on:click.outside' => 'imagePickerOpen = false',
                    'x-on:keydown.escape.window' => 'imagePickerOpen = false',
                ])
                ->columnSpanFull(),
        ];
    }
}
