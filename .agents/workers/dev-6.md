# Dev Worker 6 — Gallery Hero

**Working branch:** `dev`
**Current holder:** Dev Worker 6, lease 32; replacement chat activated on 2026-11-21.
**Current state:** Dummy Gallery membership table/date/Actions source redesign committed (`611461b4`, `9e9edd9e`) for browser review; source-only verification, no browser/runtime acceptance. Earlier visual picker inset fix and Hero/Random/Filters changes still require acceptance.

## Scope and accepted design

- Home is a protected `SiteSection` identity; Hero Artworks is edited via **Gallery → Hero Artworks** at `/admin/pages/gallery/hero/1`.
- This worker's established ownership is Hero Artworks, Dummy Gallery member selection, Random behavior, Stage and directly required shared-control corrections. Dev Worker 4 owns the broader Gallery redesign; coordinate overlap.
- Source modes: Automatic (enabled regular Galleries), Semi-Automatic (regular and enabled Dummy Galleries), Manual (Dummy Galleries only). Dummy Galleries use named negative-ID source groups, with Artwork and direct Storage media references.
- Preserve Gallery-first source priority, existing sorting and source ordering, not individual Artwork manual ordering. Candidate limits: Total N / Per Gallery N. Display: Top Only, Random, Sequential.

## Source state inherited from previous holder

- `c27664e1` — `GalleryHeroResolver` avoids immediately repeating the last **visual Media Asset** in Random when at least two distinct positive-weight visuals exist; Artwork and Storage references to the same asset are the same visual. Renormalized next-draw odds are shown as **Next** in Stage, original weights remain editable. Public keeps per-visitor/page draw history separate from Preview; Random option is disabled with fewer than two distinct positive-weight visuals.
- `dcb97e21` — Dummy Gallery uses the canonical `MediaAssetPicker` (also used by Exhibition), replacing the earlier generic select and removing the picker-specific CSS workaround.
- `e2fd3a76` — server-side editorial Artwork counting/search/offset pages plus `MediaAssetSelect::pageOptions` for Storage, eliminating the former 30-item picker limit and aligning create/edit eligibility.
- `9365f8de` — shared Filters popup initial measurement and positioning in `resources/views/components/admin/controls.blade.php` with the capped shared width in `resources/css/admin/six-cell-contract.css`; intended to prevent the first-frame large-panel flash without page-local fixes.
- `60465aa9ac99870733cea41a60250667c0901d13` — fixes the Hero Stage render exception from reading missing `$row['disabled']`; active Dummy members now show **Disable**, with re-enable in Edit. The failed earlier Create request saved and audited a Dummy Gallery before its subsequent render exception; check for an existing “Dummy Gallery 1” before recreating.

These are inherited implementation facts, not evidence of current live browser behavior. Isolated previous-chat checks exercised Random transitions, pagination boundaries and the popup controller; **no application/browser execution was performed**.

## Current source fix — shared visual media picker

- The shared `MediaAssetPicker` option markup had inherited `fi-select-input-option` styling, including forced horizontal padding and extra margins from the ordinary Filament Select controls. The template now uses its own `admin-media-picker__option` class exclusively.
- `resources/css/admin/media.css` owns the option's standard field spacing and visual-card variant. Visual tiles use the same inset on every side (the previously accepted vertical value), while nonvisual picker options retain the shared Select tokens for their existing sizing.
- Direct consumers include Exhibition cover media and Gallery Hero Dummy Gallery pickers. No shared Filament Select styling or picker state/transport changed; browser geometry and selection/reopen remain unverified.

## Current change — Dummy Gallery membership table

- Replaced redundant Artwork / Storage identity and Year / Month / Day columns with one flexible Artwork / Storage identity, one Date and canonical State/Delete Actions.
- Reused the existing partial-date picker in the table for Storage dates, keeping Year, Month/year, Exact day and Clear. Existing Artwork dates remain read-only. Shared picker markup is extracted to one Blade component used by both form fields and table without a second JavaScript controller.
- `ArtworkDate::fromParts/displayParts` handles partial precision consistently. The editor obtains `RegionalFormats::adminRegion()` from saved user preferences. Picker changes normalize once to year/month/day and persist in one existing Hero domain mutation with existing audit/notification.
- Stable action slots, header and trailing inset use the shared table/action tokens; the 38rem table state compacts actions together. The shared `admin-table--static` variant suppresses row hover where no row-level action or ordering exists.
- **Unverified in browser:** wide and narrow table geometry, trailing Remove spacing, date picker teleport inside modal, actual Storage save/reopen, profile-region switching, inactive members and shared Artwork date picker regression.

## Outstanding browser acceptance (when user can review)

1. Hero page opens without the prior render exception; inspect existing Dummy Gallery 1, Disable/enable/remove persistence and reload.
2. Exhibition cover media and Dummy Gallery Create/Edit visual pickers: equal compact top/left/right option insets, unchanged search row, hover/selection, scroll and dialog growth, search, clear/reselect, pagination beyond 30 entries, eligibility, save/reopen. Also check a nonvisual MediaAssetPicker consumer if present.
3. Random: distinct-media counts 1/2/3+, duplicate references, zero weights, editable/current versus **Next** odds, option enablement and separate visitor/Preview histories.
4. Shared Filters popup: first open without flash at Activity, then directly affected users (Storage, Gallery, Pages, Journal, Custom Page, Analytics), across responsive widths, close/reopen, Escape, click-away, scroll, resize and Livewire update.
5. Treat only reproducible regressions as fix tasks; source-only review cannot establish browser acceptance.

## Coordination and deferred consolidation

- Dev Worker 4 owns Gallery table/Stage redesign; Dev Worker 11 owns public Gallery loading. Do not overwrite their concurrent source changes.
- Once product behavior is user-accepted, route Gallery Hero and Dummy Gallery durable semantics to Docs. Shared popup/picker lessons belong to the existing canonical Admin control owners.
- Testing/Docs integration, deployment, migration, release and protected-branch promotion remain out of scope without explicit direction.

**Next:** Check the revised Create/Edit Dummy Gallery table in the current browser build. Fix any observed regressions, then continue prior Hero/Random/Filters browser acceptance. Before writes verify lease 32 and fresh shared `dev` source.