// Single Tiptap extension for editor and Public image layout attributes.
// Filament owns the editor lifecycle; this adds only canonical image metadata.
export default () => {
    const tiptap = window.FilamentRichEditor?.tiptap;
    if (!tiptap?.core?.Extension) {
        throw new Error('Filament RichEditor extension API is unavailable');
    }
    const { Extension } = tiptap.core;
    const { Plugin, PluginKey, NodeSelection } = tiptap.pmState;

    const selectedImage = editor => {
        if (!editor || editor.isDestroyed) return null;
        const selection = editor.state.selection;
        const candidates = [];
        if (selection instanceof NodeSelection && selection.node?.type.name === 'image') {
            candidates.push(selection.from);
        }
        const $from = selection.$from;
        if ($from?.nodeAfter?.type.name === 'image') {
            candidates.push($from.pos);
        }
        if ($from?.nodeBefore?.type.name === 'image') {
            candidates.push($from.pos - $from.nodeBefore.nodeSize);
        }
        for (const pos of candidates) {
            if (!Number.isInteger(pos)) continue;
            const node = editor.state.doc.nodeAt(pos);
            if (node?.type.name === 'image') {
                return { pos, node };
            }
        }
        return null;
    };

    // Mutation acts on a selected image node, not on the toolbar's focus or
    // the surrounding paragraph. TipTap/ProseMirror records normal undo history.
    const updateSelectedImage = (editor, attrs) => {
        const selected = selectedImage(editor);
        if (!selected) return false;
        if (Object.entries(attrs).every(([key, value]) => selected.node.attrs[key] === value)) {
            return false;
        }
        return editor.chain().focus()
            .setNodeSelection(selected.pos)
            .updateAttributes('image', attrs)
            .run();
    };

    const { Decoration, DecorationSet } = tiptap.pmView;
    const placements = new Set(['inline', 'left', 'center', 'right', 'float-left', 'float-right']);
    const placement = value => placements.has(value) ? value : 'inline';

    return Extension.create({
        name: 'artistImageLayout',
        addStorage() {
            return {
                selectedPosition: editor => selectedImage(editor)?.pos ?? null,
                setPlacement: (editor, value) => {
                    const image = selectedImage(editor);
                    if (!image) return false;
                    const notify = changed => {
                        if (changed) {
                            editor.view.dom.dispatchEvent(new CustomEvent('artist-rich-image-changed', { bubbles: true }));
                        }
                        return changed;
                    };
                    const nextPlacement = placement(value);

                    if (nextPlacement === 'inline') {
                        const $image = editor.state.doc.resolve(image.pos);
                        const paragraph = $image.parent;
                        if (paragraph.type.name === 'paragraph'
                            && paragraph.childCount === 1
                            && paragraph.firstChild === image.node) {
                            const start = $image.before($image.depth);
                            const end = $image.after($image.depth);
                            const nextParagraph = editor.state.doc.nodeAt(end);
                            const previousParagraph = editor.state.doc.resolve(start).nodeBefore;
                            const nextHasText = nextParagraph?.type.name === 'paragraph'
                                && nextParagraph.textContent.trim().length > 0;
                            const previousHasText = previousParagraph?.type.name === 'paragraph'
                                && previousParagraph.textContent.trim().length > 0;

                            if (nextHasText || previousHasText) {
                                const targetPos = nextHasText ? start + 1 : start - 1;
                                const movedImage = image.node.type.create(
                                    { ...image.node.attrs, artistPlacement: 'inline' },
                                    null,
                                    image.node.marks,
                                );
                                const changed = editor.chain().focus().command(({ tr }) => {
                                    tr.delete(start, end);
                                    tr.insert(targetPos, movedImage);
                                    tr.setSelection(NodeSelection.create(tr.doc, targetPos));
                                    return true;
                                }).run();
                                return notify(changed);
                            }
                        }
                    }

                    return notify(updateSelectedImage(editor, { artistPlacement: nextPlacement }));
                },
                selected: editor => {
                    const image = selectedImage(editor);
                    return image ? { caption: image.node.attrs.artistCaption } : null;
                },
                setCaption: (editor, value) => updateSelectedImage(
                    editor, { artistCaption: [...String(value).trim()].slice(0, 500).join('') || null },
                ),
            };
        },
        addGlobalAttributes() {
            return [{
                types: ['image'],
                attributes: {
                    artistPlacement: {
                        default: 'inline',
                        parseHTML: element => placement(element.getAttribute('data-artist-placement')),
                        renderHTML: attrs => ({ 'data-artist-placement': placement(attrs.artistPlacement) }),
                    },
                    artistCaption: {
                        default: null,
                        parseHTML: element => element.getAttribute('data-artist-caption') || null,
                        renderHTML: attrs => attrs.artistCaption
                            ? { 'data-artist-caption': String(attrs.artistCaption).slice(0, 500) }
                            : {},
                    },
                },
            }];
        },
        addProseMirrorPlugins() {
            return [new Plugin({
                key: new PluginKey('artistImageCaptions'),
                props: {
                    handleClickOn(view, pos, node, nodePos) {
                        if (node.type.name !== 'image') return false;
                        const transaction = view.state.tr.setSelection(
                            NodeSelection.create(view.state.doc, nodePos),
                        );
                        const boundary = view.dom.closest('.fi-modal-content');
                        if (boundary) {
                            const padding = 8;
                            transaction.setMeta('floatingToolbar::image', {
                                type: 'updateOptions',
                                options: {
                                    options: {
                                        flip: { boundary, padding },
                                        shift: { boundary, padding, crossAxis: true },
                                        size: {
                                            boundary,
                                            padding,
                                            apply({ availableWidth, availableHeight, elements }) {
                                                elements.floating.style.maxWidth = `${Math.max(0, availableWidth)}px`;
                                                elements.floating.style.maxHeight = `${Math.max(0, availableHeight)}px`;
                                            },
                                        },
                                        scrollTarget: boundary,
                                    },
                                },
                            });
                        }
                        view.dispatch(transaction);
                        return true;
                    },
                    decorations: state => {
                        const captions = [];
                        state.doc.descendants((node, position) => {
                            if (node.type.name !== 'image' || !node.attrs.artistCaption) return;
                            captions.push(Decoration.widget(position + node.nodeSize, () => {
                                const caption = document.createElement('span');
                                caption.className = 'admin-artist-image-caption';
                                caption.textContent = String(node.attrs.artistCaption);
                                return caption;
                            }, { side: 1 }));
                        });
                        return DecorationSet.create(state.doc, captions);
                    },
                },
            })];
        },
    });
};
