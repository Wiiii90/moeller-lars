# Dev Worker 6 — Gallery Hero

**Working branch:** `dev`
**Current holder:** Dev Worker 6, lease 32; replacement chat activated on 2026-11-21.
**Current state:** Registered and oriented; waiting for the user's next concrete task or browser feedback. Prior changes are source-committed, not browser- or user-accepted. No new feature implementation, deployment, Continuous Integration, server action or runtime test has been authorized by this identity assignment.

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

## Outstanding acceptance, only when requested or when user supplies feedback

1. Hero page opens without the prior render exception; inspect existing Dummy Gallery 1, Disable/enable/remove persistence and reload.
2. Dummy Gallery Create/Edit picker: consistent image tiles against Exhibition, scroll and dialog growth, search, clear/reselect, pagination beyond 30 entries, editorial eligibility, save/reopen.
3. Random: distinct-media counts 1/2/3+, duplicate references, zero weights, editable/current versus **Next** odds, option enablement and separate visitor/Preview histories.
4. Shared Filters popup: first open without flash at Activity, then directly affected users (Storage, Gallery, Pages, Journal, Custom Page, Analytics), across responsive widths, close/reopen, Escape, click-away, scroll, resize and Livewire update.
5. Treat only reproducible regressions as fix tasks; source-only review cannot establish browser acceptance.

## Coordination and deferred consolidation

- Dev Worker 4 owns Gallery table/Stage redesign; Dev Worker 11 owns public Gallery loading. Do not overwrite their concurrent source changes.
- Once product behavior is user-accepted, route Gallery Hero and Dummy Gallery durable semantics to Docs. Shared popup/picker lessons belong to the existing canonical Admin control owners.
- Testing/Docs integration, deployment, migration, release and protected-branch promotion remain out of scope without explicit direction.

**Next:** Await a concrete user-directed task or fresh browser feedback. Before any new write, verify lease 32 against the branch-local roster, retrieve current `dev` HEAD and reconcile overlapping commits.
