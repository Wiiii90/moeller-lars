export default () => {
    const { Extension } = window.FilamentRichEditor.tiptap.core;
    const options = new Set(['1.0', '1.15', '1.5', '2.0']);
    const legacy = { default: '1.5', none: '1.0', compact: '1.15', relaxed: '2.0' };
    const normalize = value => {
        const candidate = legacy[value] ?? value;
        return options.has(candidate) ? candidate : '1.5';
    };

    return Extension.create({
        name: 'artistTextSpacing',
        addGlobalAttributes() {
            return [{
                types: ['paragraph', 'heading'],
                attributes: {
                    artistSpacing: {
                        default: '1.5',
                        parseHTML: element => normalize(element.getAttribute('data-artist-line-spacing')),
                        renderHTML: attributes => {
                            const value = normalize(attributes.artistSpacing);
                            return value === '1.5' ? {} : { 'data-artist-line-spacing': value };
                        },
                    },
                },
            }];
        },
    });
};
