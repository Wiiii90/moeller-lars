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
not a nested Filament Action. The existing Edit change boundary is reused. Table creation and structural
table operations also notify the same change boundary; ordinary typing
retains the shared blur-to-change lifecycle. Floating image controls resolve
the clicked image or its adjacent inline cursor and dispatch the change from
the editor's own DOM, not from the potentially detached floating toolbar.

The shared `MediaAssetSelect` is the selection and query owner for all
Storage media choices, including Gallery, Journal, Home, Custom Pages and
the site icon. Its default visual presentation uses the accepted Storage
contact-sheet grammar. It shows a bounded initial set (up to 24 recently
added assets for ID-backed selects) and server-side search by filename and
alternative text (up to 30 results). Relationship-backed site-icon choices
use Filament's bounded relationship preload. All options retain available
media type constraints and native Filament selection and keyboard behavior.

The Rich Text insert-image control presents the same shared Select inside
its existing toolbar-anchored panel. The panel and Select have a single
close operation that shuts both together on Cancel/X, outside click,
Escape and insertion. Popup-owned click-away replaces the too-wide
editor-shell click-away listener. No alternative media model, catalog,
upload or autosave flow is added. `MediaAsset` has no separate title field.
Popup geometry and selection remain subject to browser acceptance.

## Text color and spacing

Text color is set from an inline native color input in the existing edit dialog.
The built-in nested TextColorAction is not mounted. The single existing editor
document stores the sanitized text-color mark; public rendering uses Filament's
registered color extension.

Paragraphs and headings support default, no spacing, compact, and relaxed
spacing via one shared Tiptap attribute. The PHP and JavaScript extensions
use the same fixed preset names; editorial and public styles match.
A single shared `resources/css/rich-text-spacing.css` is imported by both the
admin and public entries so spacing is visible when editing and publishing.
The inline color and spacing popovers keep their Tiptap editor references
nonreactive and suppress native change bubbling; changes persist only through
the existing Edit dialog's single explicit mutation event.

## Editor transport

Filament edits the same structured Tiptap document for links, nested lists and
tables. Livewire's standard maximum dot-path depth of 10 rejects legitimate
nested editor updates. `config/livewire.php` permits 24 levels while retaining
the vendor's request-size, call-count and component-count limits. The
editor continues to store validated rendered content; this is not an
independent synchronization or persistence path.

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
