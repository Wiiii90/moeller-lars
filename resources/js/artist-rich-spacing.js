export default () => {
    const { Extension } = window.FilamentRichEditor.tiptap.core;
    const options = new Set(['default', 'none', 'compact', 'relaxed']);
    const normalize = value => options.has(value) ? value : 'default';

    return Extension.create({
        name: 'artistTextSpacing',
        addGlobalAttributes() {
            return [{
                types: ['paragraph', 'heading'],
                attributes: {
                    artistSpacing: {
                        default: 'default',
                        parseHTML: element => normalize(element.getAttribute('data-artist-line-spacing')),
                        renderHTML: attributes => {
                            const value = normalize(attributes.artistSpacing);
                            return value === 'default' ? {} : { 'data-artist-line-spacing': value };
                        },
                    },
                },
            }];
        },
    });
};
