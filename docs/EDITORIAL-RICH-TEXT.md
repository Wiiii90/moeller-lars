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
The rich-editor file-attachment provider delegates uploads to MediaIngestService
and resolves file URLs through the established admin/public media routes.
A shared media asset must not be deleted when its reference is removed from
one rich-text body: global media deletion/reference cleanup owns that operation.
No external image can bypass the permitted URL/protocol checks. Public
publication still requires valid ALT information and an available thumbnail.

The image dialog permits selection of an existing Storage image. Filament's
native image upload uses the same canonical ingestion provider; the visual
editor keeps supported image resizing. Alignment tools apply to text paragraphs.
**Independent image float/width/caption controls require a dedicated Tiptap
image-node extension and are not yet implemented**. Do not advertise text
wrap or center/left/right image layout as accepted until that extension and
public rendering are browser verified.

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
