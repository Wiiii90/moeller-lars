# Dev Worker 4 — Gallery/Artwork

**Lease:** 17
**Branch:** dev
**State:** Source-updated — compact Gallery Settings redesign and Gallery auto-publication retirement on dev; runtime/browser acceptance pending.

## Current assignment and owner

- Continue the existing Dev Worker 4 lease 17 on branch `dev`; the prior lease 8 is superseded. Own Gallery/Artwork, its Stage, its shared Settings schema, canonical Artwork information and public Gallery/Artwork consumption. User's latest screenshots of Gallery Stage and Arrangement are the current UI evidence.
- Adjacent owners: Home Hero Stage/selection is Dev Worker 6, General Appearance is Dev Worker 7, Media/Storage lifecycle is Dev Worker 2, Exhibitions is Dev Worker 1 and shared dialog/scrollbar lifecycle is Dev Worker 8. Do not change their ownership accidentally. No deployment, CI, test-source changes or protected database operations without explicit permission.
- Source is the current `dev` HEAD, followed by the exact user screenshots and permanent `AGENTS.md`, `.agents/skills/dev/SKILL.md` and `docs/admin/{controls,tables,dialogs,responsive,ui}.md`. All authored repository prose remains English. Use guarded non-force commits and check the active roster lease before update.

## Retained product and architecture decisions

- Gallery's Stage is one shared-height triptych: Storage upload and transfer status; a switchable Gallery Settings / selected Staged Artwork editor; interactive Gallery/Artwork Live Preview. The source owns an unsaved queued-media workflow, not a second media database. Single and batch Create use the canonical `ArtworkDraftService` and the existing primary-media/ALT owners, with rollback before queued items disappear on validation error. Storage originals remain intact.
- Gallery Settings Stage and large Edit modal instantiate the **same** `gallerySettingsFields(bool $stage)` schema and use the existing `GalleryEditorialService`; real successful mutations call `refreshWorkspaceAfterMutation()` and dispatch `gallery-preview-updated` for the one shared browser-local interactive iframe. Invalid/no-op saves must not emit success. The browser-local Preview supports responsive CSS viewport profiles, orientation, zoom/pan and accurate embedded overflow observation; it does **not** simulate an iOS Safari engine or start a VPS-side browser.
- Gallery order is independent: Manual, newest/oldest Artwork date, newest/oldest primary Media Added. `ArtworkSort` owns ordering and estimation, manual movement adopts the displayed sequence; sort-mode changes use the guarded conditional confirmation action. No one-time Sort Once mode. Artwork date is canonical nullable `year/month/day` and dimensions use a shared single-row height × width × depth/unit control. Home owns its own shared ranking/eligibility behavior.
- `ArtworkInformation` owns visibility and a canonical ordered arrangement of Title/Date/Dimensions/Material/Credits/Copyright, Gallery defaults and sparse per-Artwork overrides. Media rights still originate at MediaAsset. `AdminPanelControl` and `AdminArrangementControl` are shared controls, not new persistence controllers. The public card and detail page consume the same information rows. Independent Media Viewer and Individual Artwork Pages exist; Default, Expanded and Museum are layout compositions, not changes to the information data. Expanded fits portrait images to remaining viewport height while its text follows General's normal reading width. Historical published rows and schema parity must remain restorable through `PublicationVersionService`; new migration `2026_11_18_040000_add_integrated_artwork_presentation` is source-only and **has not been run locally**.
- Other appearance settings, Page color/background overrides and future Gallery-color-derived gradients belong to the separately coordinated Pages/General appearance workstream. Do not recreate those settings here.

## Latest user feedback — Gallery Settings redesign, 2026-11-18

The current screenshots show unacceptable large spaces and rounded nested Repeater cards, excessive interior indentation, a top-level Artwork order next to Title rather than Between Artworks, persistent Arrangement label, and unwanted lower toggles. The explicit revised UI contract is:

- Gallery Title uses the entire first row. Artwork order and Between Artworks share the next two-column row. A standalone **checkbox** for optional looping-video sound control remains below the grouped settings.
- Three enabled panels (Artwork information, Media viewer and Individual Artwork Pages) use one flat zero-radius outline with a circular checkbox header. Compact internal rhythm and two-column child fields apply in **both Stage and Settings modal**. No explanatory helper prose, unnecessary red required stars or nested round cards.
- Information arrangement is a dense **real table** with Field, Row and Align visible in each row. Native drag sorting and an equivalent Move up/down button route share the same persisted array. No Accordion/Collapsible Repeater rows and no extra "Arrangement" caption above the table; use one thin divider. The controls use shared Admin table typography/geometry and the same Table-Repeater state in Stage, Settings modal and Edit Artwork.
- **Remove Gallery Auto-publish new Artworks as a feature**, not just a hidden field. Normal Add Artwork, one Staged Create and batch Create must always produce drafts; explicit Publish remains possible. Remove the obsolete Gallery preference scope, form values, notification paths and auto-publish branching; do not alter independent publication settings for Pages/Home/Journal/Custom Pages.
- Looping Gallery videos are always initially muted. The separate Gallery video sound checkbox enables/disables only the visitor's speaker button, not the loop, video controls generally or the Media Viewer. Playback remains browser-policy/reduced-motion constrained.

## Current implementation: compact Gallery controls and manual publication

- `GalleryWorkspaceUploadSettings.php`: reordered shared schema and removed Gallery Auto-publish; Title full width, Artwork order/Between Artworks paired, grouped panels, bottom video checkbox, and panel child fields no longer forcibly full-width.
- `AdminPanelControl.php`, `resources/css/admin/controls.css`: flat square compact panel, two-column children, shorter geometry and central shared component-owned styles. `resources/css/admin/task-surfaces.css` shares the already canonical `admin-drag-handle` geometry with the Filament native drag action; the central control styles render its dotted grab glyph.
- `AdminArrangementControl.php`: Filament 5 native compact `table()` Repeater with inline Information/Row/Align, no nested cards or title, real sortable drag/Move buttons. Domain and existing save callbacks stay unchanged.
- `GalleryWorkspaceArtworkDialogs.php`, `GalleryWorkspaceDirectUpload.php`, `AdminAutoPublishPreferences.php`: retired Gallery preference and create-time publish branches. All new Gallery Artworks are drafts; the explicit Publish action is unchanged.

## Verification and remaining gates

- The Gallery UI screenshots show the **previous** built candidate, not a browser review of the new source. Filament 5 documentation confirms built-in `Repeater::table()->compact()`, `reorderableWithDragAndDrop()` and `reorderableWithButtons()`; verified source already has one Stage/modal Settings schema, one Gallery editorial mutation owner, and the Working preview refresh dispatch.
- Source-only sanity includes read/write path review and basic PHP/stylesheet structure checks; no local PHP parser execution, Composer/Vite build, PostgreSQL migration, Filament/Livewire interaction or browser acceptance has been run through GitHub tools. Do not report visual success without viewing the built implementation.
- Acceptance: reload local Preview after normal build; inspect Panel/Arrangement at Gallery Stage, Settings modal and Edit Artwork, in wide and narrow editor widths; verify no rounded Repeater cards/Arrangement heading or red stars, Drag and Move buttons both persist, all six fields and Row/Align work, no horizontal table overflow, immediate Working Preview update on real change, error/no-op notification behavior, and no Gallery Auto-publish paths. Verify all new Gallery Artworks remain drafts, including uploads; confirm manually publishing still works. Protect long/short dialog scroll lock, historical restoration and Home independence.
- Pending durable consolidation to `docs/admin/controls.md` and `docs/admin/tables.md`: accepted reusable flat panel/table control presentation and the publication policy. Dev does not rewrite those general docs during this feature request.

## Next

1. Verify the guarded source commit and active dev branch/lease, then inspect the next local Preview build with both Gallery Stage and full Settings modal.
2. In browser, verify the two-column visibility controls, real table sorting with both drag and Move buttons, the flat radius-free panel, auto-saving and Live Preview after edits, and focus/scroll behavior at desktop and narrow widths.
3. In the authorized database/browser acceptance gate, verify migration, published-history restore, one/all Staged Artwork creation as drafts, manual publication, and individual Artwork overrides; correct only the canonical owner when a real problem is reproduced.
