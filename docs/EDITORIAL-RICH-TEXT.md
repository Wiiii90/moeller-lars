# Editorial Rich Text contract

Source implementation is browser-unverified. Test in isolated local preview before artist acceptance.

## One common editor

`AdminRichText` owns the shared Tiptap/Filament RichEditor for Journal,
Custom Page, Home and nested text fields. No separate CV editor, Markdown
toolbar, hidden second uploader or rich-text-specific media filesystem.

The editor accepts historic Markdown source without rewriting untouched
records. Editing converts it to visual HTML, which is then stored in the
existing body fields. Public and editorial validation explicitly recognize
both historical Markdown and new visual HTML; public rendering sanitizes the
new HTML via Filament's server-side RichContentRenderer rather than echoing
untrusted text.

## Canonical media

Images are identified by existing MediaAsset IDs, never by raw Storage paths.
The rich-editor field explicitly wires Filament's attachment save/URL callbacks to the same provider used by Public rendering, and the provider delegates uploads to MediaIngestService
and resolves file URLs through the established admin/public media routes.
A shared media asset must not be deleted when its reference is removed from
one rich-text body: global media deletion/reference cleanup owns that operation.
No external image can bypass the permitted URL/protocol checks. Public
publication still requires valid ALT information and an available thumbnail.

The image dialog permits selection of an existing Storage image. Filament's
native image upload uses the same canonical ingestion provider; the visual
editor keeps supported image resizing. Alignment tools apply to text paragraphs.
An `ArtistImageLayout` Tiptap extension persists explicit image
placement and caption attributes in the same document. It offers inline,
left, centered, right, float-left and float-right placement, plus a
dedicated caption field. Public uses a conservative, escaped image/caption projection based on those attributes, and wraps floats on
narrow layouts. The existing Filament image resizer owns dimensions.
The JavaScript module is a Vite-bundled external Tiptap extension; no
new popup or parallel editor lifecycle was introduced. Browser acceptance
for sizing, caption editing, nested text and text wrapping remains pending.

## PDF paste

Ordinary clipboard paste uses Tiptap's rich-content parsing. For plain copied
PDF text, the explicit Clean PDF text toolbar action works on a selection,
shows an editable proposed result in a modal and inserts it through Tiptap's
normal Undo history only after the user confirms. The conservative cleaner
joins soft wraps and preserves empty-line paragraph boundaries; ambiguous
poetry and list boundaries require human review. There is no PDF file importer.

All editorial saves continue through the existing owning action, append-only
audit, notification and Publication paths. No timers and no auto-save per
keystroke. Tests and actual browser acceptance remain outstanding.

## Image selection

The artist image layout toolbar must not rely on a text cursor remaining
on the image after a toolbar click. The bundled Tiptap extension records
selected image nodes, uses ProseMirror NodeSelection and applies whitelisted
attributes through a normal document transaction. This preserves Undo/Redo
and enables an image-specific floating toolbar. Image Caption is an optional
caption displayed immediately beneath the image, not its ALT text.

The Storage image picker is an inline panel in the owning Edit dialog. It uses
MediaAssetSelect's existing searchable media choices, with no second modal.
Selection inserts a canonical media reference into the active rich editor;
the transient picker field is never dehydrated as editorial content.
