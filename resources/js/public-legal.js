// Reveal a linked legal section after a document swap or a fragment change.
// The section remains user-editable and can be absent on unpublished pages.
export function revealLinkedLegalSection(root = document, location = window.location) {
    const id = location.hash?.slice(1) ?? '';
    if (!['rechtliches-impressum', 'rechtliches-datenschutz', 'rechtliches-urheberrecht', 'rechtliches-externe-links'].includes(id)) return;
    const target = root.getElementById?.(id);
    if (!target || target.tagName !== 'DETAILS') return;
    target.open = true;
}
