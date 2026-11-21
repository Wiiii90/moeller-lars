# Dev Worker 6 — Gallery Hero

**Working branch:** `dev`
**Current lease:** 32 (replacement chat, 2026-11-21).
**Current state:** The redundant "Use as Hero source" form toggle is removed from regular Hero source Edit. Contextual Enable/Disable now uses the canonical Edit dialog header action immediately left of the native X, for both regular and existing Dummy sources. Browser/user acceptance outstanding.

## Ownership and accepted design

- Gallery → Hero Artworks (`/admin/pages/gallery/hero/1`) configures Home Hero from regular Galleries and named negative-ID Dummy Galleries. Source modes: Automatic (enabled regular), Semi-Automatic (regular plus enabled Dummy), Manual (enabled Dummy only).
- Regular Gallery identity, contents and settings belong to the existing Gallery workspace and editorial service, not to the Hero source editor. Dummy Gallery can add/remove its own references, not mutate an underlying regular Gallery.
- Gallery-first source ordering, source ranking and limits (Total N or Per Gallery N), Display (Top Only/Random/Sequential) persist. Do not restore individual manual candidate sorting.
- Coordinate broader Gallery UI with Dev Worker 4, public Gallery loading with Dev Worker 11, and shared controls with current owners.

## Current correction — source Edit semantics and Admin geometry

- User requirement is now explicit and binding: **collapsed or first-expanded regular Gallery Edit opens an in-place Hero-only source dialog**, never a Gallery-workspace link and never the first Artwork's editor. The distinct Gallery title link continues to navigate; individual expanded Artwork rows may still use the canonical Artwork Edit action.
- Regular Hero source settings use existing `GalleryHeroConfigurationService::setRegularGalleriesEnabled` and `setAutomaticCandidateEnabled` only. The editor shows read-only Gallery identity and current/excluded Hero candidate rows with Disable/Enable only. The former Dummy-only action trait is renamed to the canonical Gallery Hero source action owner, and the previous member view is replaced rather than duplicated. No upload, Add, Remove or mutation to the real Gallery model.
- Dummy Edit retains its own name, insert member, title edit, member Enable/Disable and Remove. Both types now use one canonical `gallery-hero-source-members` Date/Actions layout; no inline date modification.
- The source table's stable label order and action slots share the canonical `admin-row-actions--hero-source` / ranked semantics, with consistent **Edit** label, compact Enable/Disable width, terminal Delete placeholder for regular sources and named compact icon-only transitions. The obsolete half-working Gallery link and mismatched row-rail variants are removed.
- The Stage candidate layout has no retired empty Drag track and the final Actions track is right-aligned; remove leftover empty drag placeholder markup/styles.
- No change to the underlying Artwork/Gallery editorial services, publication or saved data schema; no general tests/docs/CI/deploy. Browser acceptance is required for collapsed/expanded regular/Dummy rows, dialog state persistence, candidate controls, long names, compact widths and Stage axes.

## Contextual Hero source Enable/Disable (2026-11-21)

- Removed the redundant inline `enabled` toggle and corresponding `fillForm` state. It had duplicated the existing Hero source table's Enable/Disable action.
- Existing regular and Dummy Gallery Edit dialogs now expose the same canonical `AdminDialog::headerAction` with `AdminRowAction::Publish/Unpublish` icons and user-facing Enable/Disable labels, placed immediately left of the native close X. No new HTML button, extra style rule or parallel mutation was introduced.
- The regular action reuses `setRegularHeroGalleryEnabled`, including the unpublished-Gallery enable guard. The Dummy action reuses `toggleDummyGallery`. Both refresh the current modal's action/header after a state transition and preserve the underlying Hero-scoped persistence/audit/notification paths.
- Add Dummy Gallery's Create dialog does **not** show an Enable/Disable action because no source record exists yet.
- Verify source-enabled and disabled states in Edit, click header Enable/Disable twice, return to table, save/reopen, inspect role/icon label and persistence; check unpublished regular Gallery disabled-action case and retained X/Escape/scroll behavior. Browser/runtime acceptance remains outstanding.

## Previous source/bug history

- Earlier `onInformationChanged` invalid named argument crash was fixed in the actual Artwork action.
- Worker wrongly connected regular Edit to `GalleryWorkspace::getUrl` and then reconnected it to the first Artwork. Both violated the Hero-only source Edit contract. Current source fixes the missing ownership instead of repeating either wrong fallback.
- The Random, media picker, Filter popup and Hero Stage earlier fixes remain source-only, not browser-accepted.

## Verification and next steps

- **Source checks only** after publishing on checked dev HEAD; no browser, PHP runtime or automated tests were performed in this chat.
- Verify regular Gallery Edit opens without navigation (including zero active Hero candidates), with no redundant source toggle. Its only whole-Gallery Enable/Disable action appears in the dialog header left of X, matching the row's current state. The same applies to existing Dummy Gallery Edit; Create has no state button. Check no duplicate notifications, original Artwork Edit and candidate/member actions.
- Review wide, compact, narrow and minimal source table alignment, Edit/Disable/Delete rails, source order controls, selection checkbox and right-aligned Stage candidate actions. Review empty/disabled groups and date placeholder.
- Reconcile user feedback and hand durable accepted semantics to Docs/Testing owners as appropriate. Always re-check lease 32 and fresh dev HEAD before new writes.

**Next:** Publish the header-action correction against the checked dev HEAD. Browser review must verify close/reopen, header icons and source persistence; no new feature or CSS changes.
