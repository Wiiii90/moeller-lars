// Single Tiptap extension for editor and Public image layout attributes.
// Filament owns the editor lifecycle; this adds only canonical image metadata.
export default () => {
    const tiptap = window.FilamentRichEditor?.tiptap;
    if (!tiptap?.core?.Extension) {
        throw new Error('Filament RichEditor extension API is unavailable');
    }
    const { Extension } = tiptap.core;
    const { Plugin, PluginKey, NodeSelection } = tiptap.pmState;
    const selectedImages = new WeakMap();

    const selectedImage = editor => {
        if (!editor || editor.isDestroyed) return null;
        const selection = editor.state.selection;
        const direct = selection instanceof NodeSelection && selection.node?.type.name === 'image'
            ? selection.from : null;
        const pos = direct ?? selectedImages.get(editor);
        const node = Number.isInteger(pos) ? editor.state.doc.nodeAt(pos) : null;
        return node?.type.name === 'image' ? { pos, node } : null;
    };

    // Mutation acts on a selected image node, not on the toolbar's focus or
    // the surrounding paragraph. TipTap/ProseMirror records normal undo history.
    window.ArtistRichImage = {
        hasSelection: editor => Boolean(selectedImage(editor)),
        setPlacement(editor, value) {
            const selected = selectedImage(editor);
            if (!selected) return false;
            const placement = placements.has(value) ? value : 'inline';
            const transaction = editor.state.tr.setNodeMarkup(
                selected.pos, undefined, { ...selected.node.attrs, artistPlacement: placement },
            );
            transaction.setSelection(NodeSelection.create(transaction.doc, selected.pos));
            editor.view.dispatch(transaction);
            selectedImages.set(editor, selected.pos);
            editor.view.focus();
            return true;
        },
    };
    const { Decoration, DecorationSet } = tiptap.pmView;
    const placements = new Set(['inline', 'left', 'center', 'right', 'float-left', 'float-right']);
    const placement = value => placements.has(value) ? value : 'inline';

    return Extension.create({
        name: 'artistImageLayout',
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
            const editor = this.editor;
            return [new Plugin({
                key: new PluginKey('artistImageCaptions'),
                props: {
                    handleClickOn(view, pos, node, nodePos) {
                        if (node.type.name !== 'image') return false;
                        selectedImages.set(editor, nodePos);
                        view.dispatch(view.state.tr.setSelection(
                            NodeSelection.create(view.state.doc, nodePos),
                        ));
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
                view() {
                    return {
                        update(view) {
                            const selection = view.state.selection;
                            if (selection instanceof NodeSelection && selection.node.type.name === 'image') {
                                selectedImages.set(editor, selection.from);
                            }
                        },
                        destroy() { selectedImages.delete(editor); },
                    };
                },
            })];
        },
    });
};
