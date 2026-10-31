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
        bool $required = false,
    ): array {
        $toolbar = [
            ['bold', 'italic', 'underline', 'strike', 'subscript', 'superscript', 'link'],
            ['paragraph', 'h1', 'h2', 'h3', 'h4'],
            ['alignStart', 'alignCenter', 'alignEnd', 'alignJustify'],
            ['blockquote', 'bulletList', 'orderedList', 'horizontalRule'],
            ['table', 'highlight', 'textColor', 'lineSpacing', 'clearFormatting'],
        ];
        if ($allowEmbeddedMedia) {
            $toolbar[] = ['insertStorageImage'];
        }
        $toolbar[] = ['undo', 'redo'];

        $editor = RichEditor::make($name)
            ->label($label)
            ->plugins([RichTextEditorPlugin::make()])
            ->json(false)
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
            ->required($required)
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
            View::make('filament.schemas.components.rich-text-color-picker')->columnSpanFull(),
            View::make('filament.schemas.components.rich-text-spacing-picker')->columnSpanFull(),
        ];

        if ($allowEmbeddedMedia) {
            $fields[] = View::make('filament.schemas.components.rich-text-image-caption-picker')->columnSpanFull();

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
                ->schema([
                    View::make('filament.schemas.components.rich-text-image-picker-header'),
                    $picker->hiddenLabel(),
                ])
                ->extraAttributes([
                    'x-show' => 'imagePickerOpen',
                    'x-ref' => 'richImagePicker',
                    'x-cloak' => true,
                    'x-bind:style' => '`top: ${pickerTop}px; left: ${pickerLeft}px`',
                    // Picker is a transient Livewire field. Its native change
                    // must not autosave an unrelated Rich Text state.
                    'x-on:change.stop' => 'void 0',
                    'x-on:click.outside' => 'closeImagePicker()',
                    'x-on:keydown.escape.capture.stop.prevent' => 'closeImagePicker()',
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
                            linkSelection: null,
                            linkUrl: '',
                            linkText: '',
                            linkIsExisting: false,
                            linkNewTab: false,
                            linkInvalid: false,
                            linkLeft: 0,
                            linkTop: 0,
                            // Filament wraps each View field in its own Alpine scope.
                            // Child x-refs are not members of this editor shell's $refs.
                            richElement(ref) {
                                return this.$el.querySelector(`[x-ref='${ref}']`)
                            },
                            openLinkEditor(event) {
                                const editor = event.detail?.editor
                                if (!editor || editor.isDestroyed) return
                                this.$el.__artistRichEditor = editor
                                if (this.linkOpen) {
                                    this.linkOpen = false
                                    return
                                }

                                this.closeImagePicker()
                                const selection = editor.state.selection
                                const attrs = editor.getAttributes('link')
                                this.linkSelection = { from: selection.from, to: selection.to }
                                this.linkIsExisting = editor.isActive('link') && Boolean(attrs?.href)
                                this.linkUrl = this.linkIsExisting ? (attrs.href ?? '') : ''
                                this.linkNewTab = this.linkIsExisting && attrs.target === '_blank'
                                this.linkText = selection.from !== selection.to
                                    ? editor.state.doc.textBetween(selection.from, selection.to, ' ')
                                    : ''
                                this.linkInvalid = false

                                this.linkOpen = true
                                this.$nextTick(() => {
                                    const panel = this.richElement('richLinkPanel')
                                    if (panel) this.positionInlinePanel(panel, event.detail?.anchor, 'link')
                                    this.richElement('richLinkUrl')?.focus()
                                })
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
                                const editor = this.$el.__artistRichEditor
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
                                const editor = this.$el.__artistRichEditor
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
                                this.linkSelection = null
                                this.commitEditorChange()
                            },
                            captionOpen: false,
                            captionText: '',
                            captionLeft: 0,
                            captionTop: 0,
                            openCaptionEditor(event) {
                                const editor = event.detail?.editor
                                const image = editor?.storage?.artistImageLayout?.selected(editor)
                                if (!image) return
                                this.$el.__artistRichEditor = editor
                                if (this.captionOpen) {
                                    this.captionOpen = false
                                    return
                                }

                                this.captionText = image.caption ?? ''
                                this.closeImagePicker()
                                this.linkOpen = false
                                this.captionOpen = true
                                this.$nextTick(() => {
                                    const panel = this.richElement('richCaptionPanel')
                                    if (!panel || !event.detail?.anchor) return
                                    this.positionInlinePanel(panel, event.detail?.anchor, 'caption')
                                    this.richElement('richCaptionInput')?.focus()
                                })
                            },
                            applyCaption() {
                                const editor = this.$el.__artistRichEditor
                                if (!editor?.storage?.artistImageLayout?.setCaption(editor, this.captionText)) return
                                this.captionOpen = false
                                this.commitEditorChange()
                            },
                            commitEditorChange() {
                                if (!this.$el.closest('.admin-dialog--edit')) return
                                this.$nextTick(() => {
                                    if (this.$el.isConnected) {
                                        this.$el.dispatchEvent(new Event('change', { bubbles: true }))
                                    }
                                })
                            },
                            colorOpen: false,
                            colorSelection: null,
                            colorValue: '#333333',
                            colorLeft: 0,
                            colorTop: 0,
                            spacingOpen: false,
                            spacingValue: '1.5',
                            spacingNodeType: 'paragraph',
                            spacingSelection: null,
                            spacingLeft: 0,
                            spacingTop: 0,
                            inlinePanelSpace: 0,
                            positionInlinePanel(panel, anchor, prefix) {
                                const bounds = this.$el.closest('.fi-modal-content')?.getBoundingClientRect()
                                    ?? this.$el.getBoundingClientRect()
                                const shell = this.$el.getBoundingClientRect()
                                const box = anchor?.getBoundingClientRect() ?? shell
                                const left = Math.max(bounds.left + 8,
                                    Math.min(box.left, bounds.right - panel.offsetWidth - 8))
                                // The first toolbar can wrap to two rows. Never cover
                                // its lower row with a popup from its upper row.
                                const toolbarBottom = this.$el.querySelector('.fi-fo-rich-editor-toolbar')
                                    ?.getBoundingClientRect().bottom ?? box.bottom
                                const below = Math.max(box.bottom, toolbarBottom) + 8
                                const top = below + panel.offsetHeight <= bounds.bottom - 8
                                    ? below
                                    : Math.max(bounds.top + 8, box.top - panel.offsetHeight - 4)
                                this[prefix + 'Left'] = left - shell.left
                                this[prefix + 'Top'] = top - shell.top
                                // Reserve room only for main-toolbar popups. An image
                                // caption is anchored to a floating toolbar within
                                // the document and must not move its own anchor.
                                if (prefix !== 'caption') {
                                    const contentTop = this.$el.querySelector('.fi-fo-rich-editor-content')
                                        ?.getBoundingClientRect().top
                                    this.inlinePanelSpace = contentTop === undefined
                                        ? 0
                                        : Math.max(0, top + panel.offsetHeight + 8 - contentTop)
                                }
                            },
                            openColorEditor(event) {
                                const editor = event.detail?.editor
                                if (!editor || editor.isDestroyed) return
                                this.$el.__artistRichEditor = editor
                                if (this.colorOpen) { this.colorOpen = false; return }
                                const selection = editor.state.selection
                                const current = editor.getAttributes('textColor')['data-color']
                                this.colorSelection = { from: selection.from, to: selection.to }
                                this.colorValue = /^#[0-9a-f]{6}$/i.test(current ?? '') ? current : '#333333'
                                this.colorOpen = true
                                this.spacingOpen = false
                                this.linkOpen = false
                                this.captionOpen = false
                                this.closeImagePicker()
                                this.$nextTick(() => {
                                    const panel = this.richElement('richColorPanel')
                                    if (panel) this.positionInlinePanel(panel, event.detail?.anchor, 'color')
                                })
                            },
                            applyTextColor(clear = false) {
                                const editor = this.$el.__artistRichEditor
                                if (!editor || editor.isDestroyed || !this.colorSelection) return
                                let chain = editor.chain().focus().setTextSelection(this.colorSelection)
                                if (this.colorSelection.from === this.colorSelection.to) {
                                    chain = chain.extendMarkRange('textColor')
                                }
                                const changed = clear ? chain.unsetTextColor().run()
                                    : chain.setTextColor({ color: this.colorValue }).run()
                                if (changed) {
                                    this.colorOpen = false
                                    this.commitEditorChange()
                                }
                            },
                            openSpacingEditor(event) {
                                const editor = event.detail?.editor
                                if (!editor || editor.isDestroyed) return
                                this.$el.__artistRichEditor = editor
                                if (this.spacingOpen) { this.spacingOpen = false; return }
                                this.spacingNodeType = editor.isActive('heading') ? 'heading' : 'paragraph'
                                this.spacingSelection = {
                                    from: editor.state.selection.from,
                                    to: editor.state.selection.to,
                                }
                                this.spacingValue = editor.getAttributes(this.spacingNodeType).artistSpacing ?? '1.5'
                                this.spacingOpen = true
                                this.colorOpen = false
                                this.linkOpen = false
                                this.captionOpen = false
                                this.closeImagePicker()
                                this.$nextTick(() => {
                                    const panel = this.richElement('richSpacingPanel')
                                    if (panel) this.positionInlinePanel(panel, event.detail?.anchor, 'spacing')
                                })
                            },
                            applySpacing() {
                                const editor = this.$el.__artistRichEditor
                                if (!editor || editor.isDestroyed || !this.spacingSelection) return
                                const { from, to } = this.spacingSelection
                                const changed = editor.chain().focus()
                                    .setTextSelection({ from, to })
                                    .updateAttributes(this.spacingNodeType, {
                                        artistSpacing: this.spacingValue,
                                    }).run()
                                if (changed) {
                                    this.spacingOpen = false
                                    this.spacingSelection = null
                                    this.commitEditorChange()
                                }
                            },
                            onRichEditorFocusOut() {
                                if (!this.$el.closest('.admin-dialog--edit')) return
                                const shell = this.$el
                                this.$nextTick(() => {
                                    if (!shell.isConnected || shell.contains(document.activeElement)) return
                                    shell.dispatchEvent(new Event('change', { bubbles: true }))
                                })
                            },
                            closeRichTextPanels() {
                                this.closeImagePicker()
                                this.linkOpen = false
                                this.captionOpen = false
                                this.colorOpen = false
                                this.spacingOpen = false
                            },
                            imagePickerOpen: false,
                            closeImagePicker() {
                                const input = this.richElement('richImagePicker')?.querySelector('.fi-select-input')
                                const select = input ? window.Alpine?.$data(input)?.select : null
                                if (select?.isOpen) select.closeDropdown()
                                this.imagePickerOpen = false
                            },
                            pickerLeft: 0,
                            pickerTop: 0,
                            toggleImagePicker(event) {
                                if (this.imagePickerOpen) {
                                    this.closeImagePicker()
                                    return
                                }

                                const trigger = event.target.closest('.fi-fo-rich-editor-tool')
                                if (!trigger) return

                                this.imagePickerOpen = true
                                this.linkOpen = false
                                this.captionOpen = false
                                this.$nextTick(() => {
                                    const picker = this.richElement('richImagePicker')
                                    if (!picker) return
                                    const shell = this.$el.getBoundingClientRect()
                                    const bounds = this.$el.closest('.fi-modal-content')?.getBoundingClientRect() ?? shell
                                    const anchor = trigger.getBoundingClientRect()
                                    const minLeft = Math.max(shell.left, bounds.left) + 8
                                    const maxRight = Math.min(shell.right, bounds.right) - 8
                                    this.pickerLeft = Math.max(
                                        minLeft,
                                        Math.min(anchor.left, maxRight - picker.offsetWidth),
                                    ) - shell.left
                                    this.pickerTop = anchor.bottom - shell.top + 4
                                    picker.querySelector('.fi-select-input-btn')?.click()
                                })
                            },
                        }
                        JS,
                    // One shared blur-to-change bridge, including image tools.
                    // A focus change inside the editor/picker never commits.
                    // The focus check runs after Alpine's state update without
                    // a timer, but only when the editor genuinely loses focus.
                    // Popup open/close toggles existing content padding without
                    // intercepting editor focus, input, or save operations.
                    'x-bind:style' => '`--artist-rich-panel-space: ${linkOpen || colorOpen || spacingOpen ? inlinePanelSpace : 0}px`',
                    'x-on:focusout' => 'onRichEditorFocusOut()',
                    'x-on:artist-rich-link-toggle' => 'openLinkEditor($event)',
                    'x-on:artist-rich-color-toggle' => 'openColorEditor($event)',
                    'x-on:artist-rich-spacing-toggle' => 'openSpacingEditor($event)',
                    'x-on:artist-rich-image-changed' => 'commitEditorChange()',
                    'x-on:artist-rich-content-changed' => 'commitEditorChange()',
                    'x-on:artist-rich-caption-toggle' => 'openCaptionEditor($event)',
                    'x-on:artist-rich-image-toggle' => 'toggleImagePicker($event)',
                    'x-on:artist-rich-image-inserted.window' => 'closeImagePicker()',
                    'x-on:keydown.escape.window' => 'closeRichTextPanels()',
                ])
                ->columnSpanFull(),
        ];
    }
}
