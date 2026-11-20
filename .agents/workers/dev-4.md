# Dev Worker 4 — Gallery/Artwork

**Lease:** 23
**Branch:** dev
**State:** Active — compact Staged Artwork form and private preview description contract corrected in source; runtime/browser acceptance pending.

## Gallery Staged Artwork form and private Preview — 2026-11-20

- User-supplied 500 trace: `Array to string conversion` in `resources/views/pages/artworks/show.blade.php` on `/preview/gallery-stage/{gallery}/{token}`. The Stage Rich Text form stored an array in the unsaved `description`; the canonical public view and SafeRichTextRenderer require a string. Fixed the Stage projection in `StagedArtworkPreviewController`: Stage no longer has Description, so the in-memory Artwork explicitly uses `null`. Do not weaken the public renderer or cast the structured value.
- The **existing** `ArtworkEditDialog::schema(compact: true)` Stage variant now presents Title and Artwork Date side by side, immutable Media File across the next row, Material and Position side by side, then the existing full-width Dimensions control. The Stage omits Public URL slug, Description and optional ALT text override; the full Add/Edit dialogs retain the prior schema and order.
- Both single and batch Stage creation preserve the queued Media Asset identity, discard retired old-queue fields and delegate collision-safe Slug generation to the existing `ArtworkDraftService::create()`. Position remains explicitly overridden only by the existing Position event path. No new source owner, persistence representation, tests or migration.
- Source inspected only. Local PHP/Laravel and browser verification not executed. Verify Stage queue selection, two-column geometry at wide/mobile Stage sizes, date → suggested Position changes, Media File display, Preview after each edit, Create Selected and Create All with repeated titles/partial dates. Add Artwork dialog should remain visually/behaviorally identical.

## Public link versus Working publication — 2026-11-20

- An Artwork marked unpublished in Working can remain visible in the public Gallery and keep its Public action until the next explicit website Commit. That is the intended Working/Committed boundary; the protected Preview can also show viable drafts.
- Found a separate source mismatch: the Gallery row's committed-Public-link query checked publication flags only, whereas the actual public detail route also requires viable media and may redirect when Artwork detail pages are disabled. Replaced the duplicate raw committed SQL with one bounded `CommittedRead` call to the canonical `PublicArtworkQuery` eligibility shared by the detail route; enabled links still preserve the committed slug after Working edits/unpublication.
- Source only; no database changes, runtime or browser tests, deployment or Continuous Integration. Verify all Working/Committed publication combinations, incomplete media, disabled detail pages, and pending slug changes.

## Latest incident and correction — 2026-11-20

- Reported error: `Undefined array key "date"` in Gallery List view. The concurrent Gallery Preview/focus commit lost `date` from `GalleryWorkspaceDataProjection` and restored obsolete Gallery table CSS while retaining the newer Blade consumer. This was a render projection regression, not a database migration.
- On the latest shared dev tree, restored `ArtworkDate::display()` and formatted date search in the canonical projection and restored only the Gallery table CSS section from the previously accepted List/Dense source; kept the other worker's `can_preview`, Preview/focus behavior and Gallery Preview/Stage CSS.
- Amended the existing Dev role skill to require explicit producer-to-consumer field checks and overlapping CSS-section reconciliation before a commit. Source contract checked; PHP/Laravel runtime, browser, suites, Continuous Integration and deployment not executed.
- Independent acceptance needed: load both Gallery views with partial and complete Artwork dates and evaluate actions, compact table layouts and auto Preview focus.

## Current Gallery table assignment

- Latest request: List shows the canonical full Artwork date under its title, not a Year column. Dimensions and Material share one Details column, with dimensions on the first line and material on the second.
- Dense keeps separate compact Date, Dimensions and Material columns; Date uses year/month/day precision actually supplied. Neither table has a redundant Status column; publication actions remain the authoritative state control, and filtering still supports status/readiness.
- List uses the existing centrally labeled seven-action row rail at genuine wide-table widths, with collective icon-only compression at table pressure. Dense explicitly uses that same central rail's compact variant. Preserve Position/Drag, optional List Media preview, title inline editing, Analytics priority, Actions and trailing shared Selection checkbox axis.
- Reuse `ArtworkDate::display()` in the existing Gallery projection, no new date persistence. Search includes the formatted date. The actual Gallery Grid remains unchanged by this slice.
- Source owners for this slice: `GalleryWorkspaceDataProjection.php`, `gallery-artwork-table.blade.php`, `gallery.css`, and shared `table-contract.css`. No tests, deployment or site publication authorized.

## Current preserved Gallery contracts

- Admin Stage: Upload/Staged/Settings tabs on the left, one shared true Public Live Preview on the right. Stage Settings and Edit dialog reuse one Gallery form schema; no parallel persistence path or persistent helper paragraphs. Preview includes staged unsaved Artwork through protected session-backed Public rendering. Gallery Artwork selection, sorting, actions and preview belong to existing GalleryWorkspace owner.
- Gallery Settings: Artwork order and Add Artwork placement use the existing ordering policies. `ArtworkDraftService::create()` owns first/last and date/media-added placement; explicit Position overrides. Shared Working/Committed migration exists; migration and historical snapshot compatibility remain independent acceptance gates.
- Public: Artwork detail pages use the accepted Expanded layout and canonical centered, natural-proportion fitted image-plus-info group. Gallery index drift/spacing, public modal zoom/navigation/cyclic behavior, caption widths, and public rights are outside the current table change. Do not change Home Hero, General or shared dialogs while editing this table.
- Earlier Gallery/Home Hero startup failure from missing `GalleryWorkspace` namespace import has been fixed in `GalleryHeroWorkspaceProjection.php`; the artist's local runtime must verify it after synchronizing the current branch.
- Recent accepted table/control work includes canonical `x-admin.table`, `x-admin.row-action`, `x-admin.view-options`, the shared trailing selection pattern and blank Select placeholders. Preserve the separately owned Storage/Exhibitions behavior.

## Verification and follow-up

- Source only through the connected GitHub repository. No local application runtime, actual database migration, PHP/Blade execution, browser review, test run, Continuous Integration or deployment performed by this worker.
- Browser acceptance: Gallery List/Dense at Wide/Compact/Narrow/Minimal; full date for year-only/month-only/day-precise records; Details order and truncation; label availability at full table width; compact seven-action icons; analytics fold; Position/Drag, selection count/header/row checkboxes and action states. Check Storage and Exhibitions after shared rail threshold change.
- Docs/Testing follow-up: reconcile dated Gallery table semantics in the canonical Admin tables contract and test-owner scenarios only after reviewed behavior. The present worker record retains earlier Public/Stage boundaries until the appropriate durable owners are confirmed reconciled.
- Next: review actual Gallery browser feedback from the artist, then fix only reproduced in-scope regressions and keep this record current.
