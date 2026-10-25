# Editorial Rich Text contract

**Source boundary:** Scoped inspection of `dev@dee05e4d7bc0ac70081a4c8f998442701bc7d630`: `AdminRichText`, `RichTextEditorPlugin`, `artist-rich-image.js`, `artist-rich-spacing.js` and the shared spacing stylesheet. This describes implemented source behavior, **not** an executed test or accepted browser review.

The **single editable visual editor** is `AdminRichText` built on Filament RichEditor/Tiptap. Journal, Home, Custom Pages and nested text content use it rather than separate CV editors, media stores or page-specific editor variants. [Admin controls](../admin/controls.md) own the shared field geometry, [dialogs](../admin/dialogs.md) the mounted Edit dialog and its persistence boundary, and [media](../media.md) the underlying Storage assets. This document owns editor content, tools, image layout and rendering semantics.

## Content and publication

- Stored legacy Markdown remains readable. Editing converts a legacy value to supported visual HTML without a blanket data migration.
- `RichTextDocument` validates the editorial representation and `SafeRichTextRenderer` sanitizes HTML before public rendering. Rich Text does not bypass the owning page/Journal editorial service, audit, notifications or Working-to-Committed publication.
- Changes run through the **one existing Edit action**. Ordinary typing persists on genuine focus loss; explicit tool operations notify that same change boundary. No separate timer, parallel document state, nested Action save or independent undo history.

## Inline links

The shared editor overrides Filament's built-in link tool so a framework `LinkAction` modal is not mounted. Its toolbar opens an inline panel **inside the current Edit dialog** with the selected Tiptap range. The panel can edit and remove a link, insert a link label when nothing was selected and choose an optional new-tab target.

Only allowed HTTP, HTTPS and mailto destinations are accepted; `SafeLinkPolicy` enforces the public policy independently. Closing the panel without a genuine edit does not create a separate domain mutation. Link tools share the editor's normal save boundary, not another dialog or timer.

## Image media and layout

The image picker is the existing `MediaAssetSelect`, shown as a searchable, toolbar-anchored Storage selector inside the **same Edit dialog**, not another modal or media catalog. It selects available image `MediaAsset` identifiers; historical `media:` references remain supported. A reused asset is referenced, never copied or owned by the Rich Text field. Uploads belong to Storage; the generic editor's `Attach Files` upload path is disabled. Removing an image from text does not delete its shared asset. Public delivery still checks the [media/rights eligibility contract](../media.md).

`ArtistImageLayout` is the one image-node extension for the editor and the public renderer. It supports exactly these persisted placements: `inline`, `left`, `center`, `right`, `float-left` and `float-right`. Float variants permit subsequent paragraphs to wrap beside an image at sufficient width; narrow layouts clear that float. Image controls act on the **selected image node** or the image adjacent to the current cursor, including the existing selected-node fallback, rather than the floating toolbar's focus position. This matters after the image or toolbar takes focus.

Image position/wrapping and the optional **500-character caption** appear only in the contextual image toolbar; ordinary paragraph/alignment tools remain separate. Caption is display text, not an alternative-text replacement. The image toolbar stays inside the visible native dialog scrollport using the existing Floating UI placement. The caption panel stays in the owning Edit dialog; its successful operation dispatches the one canonical change notification **from the editor's own connected shell**, never an orphaned popup.

The same `MediaAssetSelect` handles eligible media for Gallery, Journal, Home, Custom Pages, Rich Text and the site icon. An identifier-backed select offers up to 24 recent available assets initially, and searches filename/alternative text on the server with up to 30 results; relationship-backed choices may use bounded native preload. The native Filament keyboard and selection lifecycle is retained. The insert-image picker closes its selector and panel together on insertion, Cancel/X, Escape or click-away. Clicking outside this popup does **not** close the parent Edit dialog.

## Text color and spacing

Text color uses a small **inline native color input** within the existing editor dialog; the framework's nested `TextColorAction` is not mounted. It changes the text-color mark in the same Tiptap document; server-side rendering applies the registered extension.

Paragraphs and headings support `default`, `none`, `compact` and `relaxed` line-spacing presets. One `ArtistTextSpacing` attribute and the shared `resources/css/rich-text-spacing.css` stylesheet implement both editor and public output; neither surface creates a page-specific spacing override.

Color and spacing popovers retain their Tiptap editor references outside Alpine reactive proxying. Their own native change bubbling is suppressed, so a deliberate tool application emits **exactly the existing Edit dialog's change event** rather than racing it with an accidental extra autosave. Image placement, captions and table structure commands likewise signal the shared mutation boundary; merely entering or opening a popover is not a save.

## Editor transport

Filament maintains a single structured Tiptap document for links, images, nested lists and tables. Deep nested editor updates exceed Livewire's former default dot-path depth of 10; `config/livewire.php` permits depth 24 while preserving the framework's other request-size, call-count and component-count limits. This extends valid transport depth, **not** editor authorization, HTML sanitization or persistence ownership.

## Acceptance

Review actual Journal/Home/Custom Page Edit dialogs, not source alone: native panel focus and click-away; image insertion, selected/adjacent image actions, all six layouts, float wrapping and caption; color/spacing choice, undo/redo and table persistence; links and nested content; blur versus deliberate changes, validation/no-op, close/reopen, Preview and an ordinary publication checkpoint. An authored test or source review is **not** evidence that the browser flow or public publication was executed and accepted.
