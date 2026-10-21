# Editorial Rich Text contract

The canonical editable text surface is `AdminRichText`, built on the installed
Filament 5.7.6 / Tiptap implementation. It is shared by Journal, Custom Pages,
Home and nested text content. There is no separate CV editor or second
editor-specific file store.

## Content and publication

Existing Markdown values remain readable. The editor converts legacy values
to supported visual HTML on editing, not in an unconditional backfill. Both
formats use `SafeRichTextRenderer` with editorial validation; HTML public
output is sanitized server-side before publication. Every save retains the
existing owning editorial action, audit, notification and publication paths.
The editor does not own a second auto-save timer or alternate change history.

## Image media and layout

Images reference canonical available `MediaAsset` IDs, including historical
`media:` references. The image picker is a searchable Storage dropdown anchored to the Insert
Image toolbar control *inside* the current Edit form. It does not open
a second action modal or introduce a second media catalog. The selection
callback holds its owning RichEditor component directly; it does not
search the entire schema during Livewire updates.

The generic `Attach Files` control and its independent upload capability are
disabled. Uploads are performed via the existing Storage workflow. Deleting
an image from text must not delete a shared MediaAsset. Public resolution
honors existing media rights, visibility and ALT checks.

`ArtistImageLayout` is one Tiptap extension shared between browser editor
and PHP public rendering. It stores exactly one of: inline, left, center,
right, float-left, float-right. The image toolbar operates on a selected
image node, not a paragraph cursor, and retains normal Undo/Redo behavior.
Float-left and float-right allow *subsequent paragraphs* to flow alongside
the image while desktop width permits; narrow layouts clear the float.
An optional 500-character Caption appears below the image; it is not
alternative text for screen readers and does not replace ALT.

Paragraph and alignment controls remain separate, icon-only toolbar buttons.
Image placement, text wrapping and Caption appear only in the contextual
image floating toolbar, never duplicated in the general toolbar. All image
actions remain enabled on an image selection and use the existing Tiptap
image node. The floating toolbar uses Filament's Floating UI boundary,
constrained to the visible native dialog scrollport, with native flip,
shift and size handling. Caption entry is inside the same editor dialog,
not a nested Filament Action. The existing Edit change boundary is reused.

The shared `MediaAssetSelect` remains the only selection and query owner.
Its reusable visual variant shows a bounded set of up to 24 recently added
Storage images as soon as the dropdown opens, in a contact-sheet layout
matching Storage. Additional searches query filename and alternative text
server-side with at most 30 results. The choice UI retains Filament's normal
keyboard navigation, focus and selection handling. No duplicate media
catalog, extra upload flow, or background polling is introduced.
`MediaAsset` has no separate title field. Image selection, popup geometry,
wrapping and saving remain subject to real browser acceptance.

## Clipboard and PDF

Normal rich clipboard paste is handled by Tiptap. The experimental
`Clean PDF Text` action and its unused cleaner have been removed pending
the artist's explicit decision about paragraph/line-wrap semantics.
There is no PDF-file importer and no automatic destructive cleanup of text
copied from PDFs.

## Acceptance

Verify in the actual Edit component dialog: parent/heading and editor layout,
insert image within the current dialog, image-node selection and floating
toolbar behavior, all six positions, paragraph wrapping, caption, Undo/Redo,
close/reopen, public preview and an ordinary publication checkpoint.
Source review, authored tests and committed code are not browser acceptance.

## Inline links

The shared artist RichEditor overrides Filament's built-in `link` tool; the
framework `LinkAction` is never mounted by the toolbar. A lightweight
editable link panel belongs to the *existing Edit dialog* and
operates on the original Tiptap text selection, including existing links,
removal, new label insertion and an optional new-tab target. Only http(s)
and mailto links are accepted; public rendering still enforces the shared
`SafeLinkPolicy`. The canonical Edit-dialog change boundary owns persistence.
No extra nested modal, duplicated editor, second link storage or timer.
