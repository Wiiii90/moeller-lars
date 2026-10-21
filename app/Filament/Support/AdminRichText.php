<?php

namespace App\Filament\Support;

use App\Domain\Content\RichTextDocument;
use App\Domain\Media\MediaPreviewVariant;
use App\Models\MediaAsset;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\RichEditor\EditorCommand;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\View;
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
            ->linkProtocols(['http', 'https', 'mailto'])
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

        // One autosave boundary for every shared RichEditor instance.
        // The owning AdminDialog remains responsible for persistence.
        // Creation and Command dialogs never autosave.
        $editorKey = 'artist-rich-text-'.$name;
        $editor->key($editorKey);
        $fields = [
            $editor,
            View::make('filament.schemas.components.rich-text-link-picker')->columnSpanFull(),
        ];

        if ($allowEmbeddedMedia) {
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


            $fields[] = Grid::make()
                ->columns(1)
                ->schema([$picker])
                ->extraAttributes([
                    'x-show' => 'imagePickerOpen',
                    'x-cloak' => true,
                    'x-bind:style' => '`top: ${pickerTop}px; left: ${pickerLeft}px`',
                    // Picker is a transient Livewire field. Its native change
                    // must not autosave an unrelated Rich Text state.
                    'x-on:change.stop' => 'void 0',
                    'class' => 'admin-rich-text-storage-dropdown',
                    'role' => 'group',
                    'aria-label' => __('Image from Storage'),
                ])
                ->columnSpanFull();
        }

        return [
            Grid::make()
                ->columns(1)
                ->schema($fields)
                ->key($editorKey.'-shell')
                ->extraAttributes([
                    'class' => 'admin-rich-text-editor-shell',
                    'x-data' => <<<'JS'
                        {
                            linkOpen: false,
                            linkEditor: null,
                            linkSelection: null,
                            linkUrl: '',
                            linkText: '',
                            linkIsExisting: false,
                            linkNewTab: false,
                            linkInvalid: false,
                            linkLeft: 0,
                            linkTop: 0,
                            openLinkEditor(event) {
                                const editor = event.detail?.editor
                                if (!editor || editor.isDestroyed) return
                                if (this.linkOpen) {
                                    this.linkOpen = false
                                    return
                                }

                                this.imagePickerOpen = false
                                const selection = editor.state.selection
                                const attrs = editor.getAttributes('link')
                                this.linkEditor = editor
                                this.linkSelection = { from: selection.from, to: selection.to }
                                this.linkIsExisting = editor.isActive('link') && Boolean(attrs?.href)
                                this.linkUrl = this.linkIsExisting ? (attrs.href ?? '') : ''
                                this.linkNewTab = this.linkIsExisting && attrs.target === '_blank'
                                this.linkText = selection.from !== selection.to
                                    ? editor.state.doc.textBetween(selection.from, selection.to, ' ')
                                    : ''
                                this.linkInvalid = false

                                const shell = this.$el.getBoundingClientRect()
                                const anchor = event.detail?.anchor?.getBoundingClientRect()
                                const maxLeft = Math.max(0, shell.width - 320)
                                this.linkLeft = Math.max(0, Math.min((anchor?.left ?? shell.left) - shell.left, maxLeft))
                                this.linkTop = (anchor?.bottom ?? shell.top) - shell.top + 4
                                this.linkOpen = true
                                this.$nextTick(() => this.$refs.richLinkUrl?.focus())
                            },
                            normalizedLinkUrl() {
                                let url = this.linkUrl.trim()
                                if (!url || /[\s\\]/.test(url)) return null
                                if (!/^[a-z][a-z\d+.-]*:/i.test(url)) {
                                    url = /^[^/@\s]+@[^/@\s]+$/.test(url)
                                        ? 'mailto:' + url
                                        : 'https://' + url
                                }

                                if (/^mailto:/i.test(url)) {
                                    return /^mailto:[^@/?#\s]+@[^@/?#\s]+\.[^@/?#\s]+$/i.test(url)
                                        ? url
                                        : null
                                }

                                try {
                                    const parsed = new URL(url)
                                    return ['http:', 'https:'].includes(parsed.protocol)
                                        && parsed.hostname
                                        && !parsed.username
                                        && !parsed.password
                                        ? parsed.href
                                        : null
                                } catch {
                                    return null
                                }
                            },
                            applyLink() {
                                const editor = window.Alpine?.raw?.(this.linkEditor) ?? this.linkEditor
                                if (!editor || editor.isDestroyed || !this.linkSelection) return

                                const url = this.normalizedLinkUrl()
                                if (!url) {
                                    this.linkInvalid = true
                                    return
                                }

                                const { from, to } = this.linkSelection
                                let chain = editor.chain().focus().setTextSelection({ from, to })
                                const attrs = { href: url, target: this.linkNewTab ? '_blank' : null }
                                let result = false

                                if (from === to && !this.linkIsExisting) {
                                    const label = this.linkText.trim() || url
                                    result = chain.insertContent({
                                        type: 'text',
                                        text: label,
                                        marks: [{ type: 'link', attrs }],
                                    }).run()
                                } else {
                                    if (from === to && this.linkIsExisting) {
                                        chain = chain.extendMarkRange('link')
                                    }
                                    result = chain.setLink(attrs).run()
                                }

                                if (result) this.finishLinkEdit()
                            },
                            removeLink() {
                                const editor = window.Alpine?.raw?.(this.linkEditor) ?? this.linkEditor
                                if (!editor || editor.isDestroyed || !this.linkSelection) return
                                const result = editor.chain().focus()
                                    .setTextSelection(this.linkSelection)
                                    .extendMarkRange('link')
                                    .unsetLink()
                                    .run()
                                if (result) this.finishLinkEdit()
                            },
                            finishLinkEdit() {
                                this.linkOpen = false
                                this.linkEditor = null
                                this.linkSelection = null
                                // Tiptap synchronizes its Livewire state before
                                // this single canonical Edit-dialog change event.
                                if (this.$el.closest('.admin-dialog--edit')) {
                                    this.$nextTick(() => {
                                        if (this.$el.isConnected) {
                                            this.$el.dispatchEvent(new Event('change', { bubbles: true }))
                                        }
                                    })
                                }
                            },
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
                                this.linkOpen = false
                                this.$nextTick(() => {
                                    this.$el.querySelector(
                                        '.admin-rich-text-storage-dropdown .fi-select-input-btn',
                                    )?.click()
                                })
                            },
                        }
                        JS,
                    // One shared blur-to-change bridge, including image tools.
                    // A focus change inside the editor/picker never commits.
                    // The focus check runs after Alpine's state update without
                    // a timer, but only when the editor genuinely loses focus.
                    'x-on:focusout' => <<<'JS'
                        if (!$el.closest('.admin-dialog--edit')) return;
                        const shell = $el;
                        $nextTick(() => {
                            if (!shell.isConnected || shell.contains(document.activeElement)) return;
                            shell.dispatchEvent(new Event('change', { bubbles: true }));
                        });
                        JS,
                    'x-on:artist-rich-link-toggle' => 'openLinkEditor($event)',
                    'x-on:artist-rich-image-toggle' => 'toggleImagePicker($event)',
                    'x-on:artist-rich-image-inserted.window' => 'imagePickerOpen = false',
                    'x-on:click.outside' => 'imagePickerOpen = false; linkOpen = false',
                    'x-on:keydown.escape.window' => 'imagePickerOpen = false; linkOpen = false',
                ])
                ->columnSpanFull(),
        ];
    }
}
