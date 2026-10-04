const actionSelector = '.admin-action, .admin-add-row, .admin-drag-handle';

function normalizedText(value) {
    return String(value ?? '').replace(/\s+/g, ' ').trim();
}

function semanticActionLabel(action) {
    const explicitLabel = action.querySelector('.admin-action__label')?.textContent
        || action.querySelector('.admin-selection__trigger-text')?.textContent;

    if (normalizedText(explicitLabel) !== '') {
        return normalizedText(explicitLabel);
    }

    if (action.classList.contains('admin-add-row')) {
        const addLabel = action.querySelector(':scope > span:last-child')?.textContent;
        if (normalizedText(addLabel) !== '') {
            return normalizedText(addLabel);
        }
    }

    const text = normalizedText(action.textContent);
    if (text !== '' && ! /^[↑↓+⋮\s]+$/u.test(text)) {
        return text;
    }

    return normalizedText(action.getAttribute('aria-label'));
}

function enhanceAction(action) {
    if (! (action instanceof HTMLElement)) return;

    const label = semanticActionLabel(action);
    if (label === '') return;

    if (! action.hasAttribute('title')) {
        action.setAttribute('title', label);
    }

    if (! action.hasAttribute('aria-label')) {
        action.setAttribute('aria-label', label);
    }
}

function enhanceActionTree(root) {
    if (! (root instanceof Element || root instanceof Document)) return;

    if (root instanceof Element && root.matches(actionSelector)) {
        enhanceAction(root);
    }

    for (const action of root.querySelectorAll(actionSelector)) {
        enhanceAction(action);
    }
}

enhanceActionTree(document);

new MutationObserver((records) => {
    for (const record of records) {
        for (const node of record.addedNodes) {
            if (node instanceof Element) {
                enhanceActionTree(node);
            }
        }
    }
}).observe(document.documentElement, {
    childList: true,
    subtree: true,
});
