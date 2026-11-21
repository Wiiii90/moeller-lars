# Dev Worker 6 — Gallery Hero, handoff for replacement chat

**Working branch:** `dev`
**Current holder:** Dev Worker 6, lease 16. This chat ends after the checked handoff; a new chat assigned Dev Worker 6 must re-read the branch rules and take over the slot with a new checked lease before editing.
**Current state:** Implementations committed; **local-browser and user acceptance outstanding**. The user intends to evaluate the recent Hero and shared Filters fixes in the replacement chat. No deployment, continuous integration, server changes or repository test runs were authorized or performed.

## Scope and accepted architecture

- Home is a protected `SiteSection` page identity, not a standalone page type; its Hero Artworks presentation now belongs to the **Gallery → Hero Artworks** template. Current Admin route for the user's local review: `/admin/pages/gallery/hero/1`.
- Dev Worker 6 owns the Hero Artwork source, Dummy Gallery member workflow, Random behavior, Stage and directly required shared-control fixes. **Dev Worker 4 owns broader Gallery** presentation and must not have concurrent Gallery source changes overwritten. Shared Admin filter behavior belongs in the central `x-admin.controls` owner.
- Source modes: Automatic (enabled regular Galleries), Semi-Automatic (regular plus enabled Dummy Galleries), Manual (Dummy Galleries only). Dummy Galleries are named negative-ID source groups with editable Artwork and direct Storage references; avoid creating a second member/picker/rendering system.
- Ranking: existing date/media sort rules and Gallery-first source priority. **Keep source/Gallery ordering; do not reintroduce manual ordering of individual Artworks.** Candidates support Total N / Per Gallery N under the configured cap; Display supports Top Only, Random and Sequential. These are retained accepted semantics, not new work for browser review.

## Implemented changes and source evidence

1. **Hero Random anti-repeat** — `c27664e1`. `GalleryHeroResolver` excludes the last **visual Media Asset** from the next weighted draw if at least two distinct positive-weight visuals exist. Artwork and direct Storage references to one asset are the same visual. Basis weights remain persisted unchanged; only the next draw is renormalized. Public Hero saves last-visual history per visitor session/page; Preview history stays separate. Stage displays original editable percentages and live **Next** percentages. Random choice is disabled with fewer than two distinct positive-weight images, without deleting a previously saved Random strategy. Owned sources: `GalleryHeroCandidate.php`, `GalleryHeroResolver.php`, `GalleryHeroPresentationResolver.php`, `GalleryHeroWorkspaceProjection.php`, Gallery Hero Stage JavaScript/Blade and Settings.
2. **Canonical visual picker** — `dcb97e21`. Dummy Gallery switched from a generic Filament Select to the same `MediaAssetPicker` Blade renderer as Exhibition. The earlier CSS workaround for native-Select image cards was removed. Shared search, scrolling, card layout and popup lifecycle are reused. The orange search focus underline was removed from the shared media stylesheet.
3. **Genuine paged sources** — `e2fd3a76`. `PublicArtworkQuery` performs bounded, server-side editorial search/count/offset pages; Dummy Gallery combines these with `MediaAssetSelect::pageOptions` without a 30-Artwork/30-Storage cutoff. Create-time Artwork eligibility now agrees with the editorial picker and Edit path. This is a feature-specific data callback to the **same** shared pagination transport, not a second picker.
4. **Central Filters popup first-frame geometry** — `9365f8de`. The shared `resources/views/components/admin/controls.blade.php` keeps an opening popup hidden until the measured viewport position is set, resets old positioning on close, and shares the initial capped width from `resources/css/admin/six-cell-contract.css`. Activity, Storage, Gallery, Pages, Journal, Custom Page and Analytics use the common toolbar component. No page-local CSS or duplicate filter state was introduced.
5. **Home Hero Stage render crash** — `60465aa9ac99870733cea41a60250667c0901d13`. A user-provided GET traceback showed `Undefined array key "disabled"` in `gallery-hero-workspace.blade.php` around the Dummy member action. The resolved Hero group filters out disabled members; the active Stage's stale Enable/Disable ternary referred to a nonexistent `$row['disabled']` field. The view now consistently renders the **Disable** action for active Dummy members; the Edit dialog remains responsible for re-enabling disabled members. The producer/renderer contract was inspected and the nonexistent read removed. The user's earlier Create trace showed a Dummy Gallery was saved and audited *before* the subsequent same template rendering exception. **Check whether “Dummy Gallery 1” already exists before creating another.**

## Verification status

- Source and safe checked GitHub commits were verified for the listed fixes; no real local application or browser execution occurred in this chat.
- Isolated JavaScript Random exercise: 1,000 three-image transitions without consecutive repeats; two-image alternation, duplicate-media exclusion, zero-weight/single-candidate cases, and dynamic odds were checked. The combined source paging boundary was simulated for 100 Artwork/Storage count combinations. The shared Filters Alpine controller was syntactically evaluated and exercised through open/position/close/reopen/resize with a simulated element geometry. **These are isolated checks, not application integration or visual acceptance.**
- The latest Home crash repair was verified at source level against the resolver (only enabled members enter active group) and the view (no `$row['disabled']` read).

## First tasks for the replacement chat: user browser acceptance

1. Re-read fresh `dev` branch rules, same-branch Dev skill and protocol; **take over Dev Worker 6 with a fresh lease**. Compare current `dev` HEAD with the fix and other workers' in-flight changes. Do not deploy or run CI without explicit authorization.
2. Reopen `/admin/pages/gallery/hero/1`: verify no render exception, check whether Dummy Gallery 1 already exists, and validate Stage Disable / Edit re-enable / Remove and data reload.
3. **Dummy Gallery Create/Edit:** validate Artwork and Storage image tile geometry, dialog growth and scrolling, source pagination past 30 results, search and clearing/reselecting, actual candidate insertion, persistence and closing/reopening. Compare to Exhibition's canonical visual picker. Check both available/archived and published editorial eligibility.
4. **Random Stage/public:** validate 1/2/3+ distinct-media cases, zero weights, duplicate Artwork/Storage media, dynamic Next percentages after each Stage simulation/weight change, disabled Random option in Stage and Settings, separate public visitor histories and Preview behavior.
5. **Shared Filters popovers:** check Activity first, then Storage, Gallery, Pages, Journal, Custom Page and Analytics when Filters is active. At Wide/Narrow/Minimal or actual collapse thresholds, verify first opening **without a large flash/shrink**, close/reopen, Escape, click-away, page scrolling, viewport resize, in-page Livewire updates, vertical fitting and accessible controls. The shared reference is `x-admin.controls` plus `six-cell-contract.css`.
6. Report only genuine regressions with reproducible browser evidence. Preserve the central owners and avoid page-specific workarounds. Later transfer to Testing/Docs remains separate and requires their branch workflow.

## Pending durable consolidation

- Once the user accepts behavior, reconcile Gallery Hero page/template semantics and Dummy Gallery controls with the existing Admin/Content product documentation via the Docs owner. This worker record is not the lasting documentation owner.
- Shared toolbar/popover and media-picker principles already have canonical Admin control guidance; browser findings should update that owner rather than create another control family.

## Out-of-scope findings

- Unrelated Dev Worker 4 Gallery list/stage redesign and Dev Worker 11 public Gallery loading remain with those workers.
- No deployment, data migration, test-suite changes, protected branch promotion, or server action is included in this handoff.

**Next:** User starts replacement Dev-6 chat, claims a new lease, and performs the browser review against current `dev`. Fix only demonstrated regressions, then consolidate this same record once more.
