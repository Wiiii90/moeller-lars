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
`media:` references. The image picker is an inline panel *inside* the current
Edit form, using the existing searchable Storage selector. It does not open
a second action modal.

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

Image selection, floating toolbar, sizing, and wrapping remain subject to
browser acceptance on the actual Filament version.

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
