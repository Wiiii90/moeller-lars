# Dev Worker 4 — Gallery/Artwork

**Lease:** 8  
**Branch:** dev  
**State:** Active — Gallery triptych Stage and staged Artwork create/review paths committed; targeted PHP runtime and visual/browser acceptance still pending.

## Owner and working boundaries

- Dev Worker 4 owns Gallery/Artwork forms, gallery presentation, Artwork position/ranking, Material presets and public Artwork. Narrow shared `ArtworkSort` semantics used by Home are in scope; broader Home Hero Stage belongs to Dev Worker 6. Storage/media lifecycle and deletion belong to Dev Worker 2. Exhibitions belong to Dev Worker 1, General Appearance to Dev Worker 7.
- Commit on `dev` only, with fresh HEAD and active roster lease check. No unapproved tests, test file changes, general docs rewrites, CI, merges, deployment or Validation changes.
- All branch-local worker documentation is English. Avoid parallel data stores, compatibility shims and feature-specific controls where canonical owners exist. Preserve immutable historical publication snapshots.
- User's local preview database **is already isolated**. Do not reopen prior backup/schema-audit loops without a concrete new cause.

## Accepted pre-Stage baseline

- Canonical Artwork date in active Working and Committed: `year`, nullable `month`, nullable `day`. The `artwork_date` form value is virtual. Undated Artwork is accepted; Clear leaves it in the Gallery and sorts it last in newest-date mode. Dimensions are one full-width same-row `height × width × depth | unit` control.
- Local Artwork migration `2026_11_03_150000_replace_artwork_date_with_components` and committed SiteSection parity migration `2026_11_03_160000_align_committed_site_section_meta_description` ran locally. Fifteen publication tables passed schema parity; historical checkpoints 9/10 passed read-only preflight. The Stage/Revert execution was not verified; Validation has not been migrated or deployed.
- Gallery Settings: central `AdminControl` two-column large dialog and central `AdminHelp`. Home alone edits existing `ArtworkCategory.show_on_home` preference. The one-time `Sort once by artwork date` was entirely retired.
- Sort modes: Manual, newest/oldest Artwork date, newest/oldest primary Media Added. `ArtworkSort` owns actual SQL ordering and a pre-insert estimate; manual drag/Move/Position adopts visible ranked order. A conditional canonical confirmation previews changes to every affected rank, with snapshot signature guard; browser-level nested modal acceptance still pending.
- Gallery source audit removed retired `exclude_from_home` Create input, redundant date-sort forwarding, unused CSS, fixed missing `DB` facade import in Add and Artwork View publication state owner. Original historical DB fields and actually-used `ArtworkResource` pages remain.
- Home Semi-Automatic shared candidate ranking lives with the existing Home configuration/resolver. Do not overwrite other workers' Home visuals.

## Current three-part Gallery Stage, committed 2026-11-17

**User-accepted concept:** one visual Stage with equal upload / editor / preview thirds; left continually accepts additional images or videos; middle switches between concise Gallery Settings and staged Artwork media (one selected entry edited with the complete Add Artwork schema, a queue of multiple media, create one or all); right previews the Gallery and navigable published Artwork pages, or currently selected unsaved media, and can be switched back manually. Completed queue items disappear; unchanged normal Add Artwork action must remain usable in parallel. Use existing canonical stage/layout/controls. Do not copy General's device-frame preview; General belongs to Dev Worker 7.

**Source commits:**
- `547fcc8`: GalleryWorkspace owns explicit Stage middle-pane / preview selection state and a second **form instance of the same existing ArtworkEditDialog schema**, not a second editor implementation. Preview navigation uses the current ordered Artwork dataset.
- `b32a24e`: The existing shared resumable media upload/Storage ingest appends media ids to a Livewire Stage queue instead of immediately opening the obsolete batch dialog. `GalleryWorkspaceDirectUpload` now owns per-asset unsaved form values, selection/removal, its preview and single/all creation. Selected creation calls the existing ArtworkDraftService → ArtworkPrimaryMediaService → ALT override → optional ArtworkPublicationService in an enclosing transaction; bulk creates drafts atomically. Completed entries are removed; originals remain in Storage. The old batch modal and its one-off media helper functions were removed rather than retained as parallel paths.
- `4768ebb`: concise Stage quick settings mutate the existing GalleryEditorialService; sort mode uses the previously implemented, guarded `changeGallerySort` confirm action, not a separate mutator. Full Gallery Settings modal remains.
- `dd1c12b`: GalleryContext projects the existing quick-setting values and the Artwork dataset exposes slug for preview route.
- `21909ef`: Blade Stage triptych and scoped feature CSS use shared `admin-visual-stage--triptych`, `admin-focus-stage`, `x-admin.stage-switcher`, existing uploader, a switchable queue/settings middle pane and an undecorated Gallery/Artwork iframe preview. No custom laptop/phone outline.
- `f6a9f90`: additional uploads count **only new items** in the existing queue; Gallery iframe receives a keyed refresh after an editorial mutation; previous/next only navigate published Artworks (draft links would return 404); the active Gallery panel keeps its vertical flex/scroll layout even in the shared Minimal focus-stage selector.
- `8c7328a`: Stage uses the same `ArtworkEditDialog::schema` with an explicit compact one-column option (no inherited Filament lg two-column default); disabled media field's explanation now correctly distinguishes a staged Create from a published Edit, and a selected queue asset id always replaces disabled field state.

**Current architecture and safety:**
- Queue state is unsaved, browser-session-local Livewire component state; MediaAssets are already durably stored through the canonical upload service. Removing an item from Stage never deletes the original. No separate draft/queue DB table or shadow MediaAsset lifecycle was introduced.
- `createSelectedStagedArtwork` uses the same full form schema validation as normal Add, and the selected queue id owns the media link (a disabled input cannot silently switch media). `createAllStagedArtworks` wraps all items in one transaction, preserving queue on validation failure; its action explicitly says **Create all drafts**. An explicitly edited Position is handled by the existing Gallery owner and may switch mode to Manual.
- A staged media preview shows the selected original/thumbnail and current edited title; a Gallery/Artwork preview shows the authenticated Working preview route. After successful completion of the final queue entry, Stage returns to middle Settings and right Gallery.
- The General preview is currently **not** an already shared standalone renderer: it owns homepage-specific device transforms. Do not move or duplicate General during this Gallery assignment.

## Continued Stage fixes — 2026-11-17

- `bda5cc4`: Stage Artwork-order select immediately restores the persisted mode in the HTML when a confirmation is requested, so cancelling does not leave a visually selected but uncommitted mode. Quick Settings provides a direct action to the canonical full Gallery Settings modal. Preview previous/next buttons now use central `AdminIcon` artwork navigation icon definitions instead of arbitrary glyphs.
- `77fcab4`: Staged preview now renders Artwork title, optional normalized regional Artwork date, composed dimensions, material and the ALT override, using **only the existing `ArtworkDate` and `ArtworkDimensions` canonical domain formatters**. Incomplete in-progress date/dimensions are ignored in preview until valid; form validation still owns errors. Image/video preview and caption share one component; no separate renderer or persisted field.
- `c9126f4`: Single Stage submit catches domain validation, reports via the existing central Gallery error notifier and preserves selected media/form on failure. All-drafts submit catches per-item validation, reports which filename/row caused failure and rolls back **every** create in its enclosing transaction. Nothing is dequeued before successful completion. Existing editor's `getState()` validation is still evaluated normally.
- Source structural review after these changes: PHP delimiter balance inspected in GalleryWorkspace, GalleryWorkspaceDirectUpload, ArtworkEditDialog and GalleryWorkspaceUploadSettings; Stage Blade matched 29 div pairs, 2 section/header pairs, 22 if/endif, 6 foreach/endforeach; Gallery CSS brace count balanced. **These are source-level checks only**, not PHP parser lint, Filament/Livewire runtime validation or browser acceptance.

## Acceptance gates and remaining risks

1. **Source is committed, not browser-accepted**. Confirm PHP/Filament parsing, Livewire `stagedArtworkForm` init/fill and field dehydration, uploader-to-queue state, stage mobile switcher, input scrolling, iframe Content Security Policy, thumbnail/video rendering, and rapid selection/race behavior in refreshed local preview. No PHP lint/browser/test suite was run through GitHub-only access.
2. Exercise multiple image and video uploads; add more while existing unsaved edits remain; switch 2+ queued items; Create one, Create all drafts, remove from Stage, then ordinary Add Artwork independent of queued uploads. Verify failures preserve queue and every successful save removes only completed entries.
3. Verify sort-confirm cancel/apply/no-difference behavior, Gallery quick controls shared persistence and preview refresh; preview next/previous for published Artwork only; Stage Preview back to Gallery. Watch for exact form field spacing, responsiveness and interaction consistency with Home/Storage Stage.
4. Consider existing full-form autosave and queued form values on Livewire requests: unsaved edits are held in memory until explicitly saved. There is no persistence guarantee across page reload; uploaded media remains in Storage. Do not make a hidden persistence model without user decision.
5. Review bulk Create with explicit positions/duplicate slugs and auto-publish semantics before declaring this fully functional. Preserve the user-facing **Create all drafts** contract or add a clearly explicit choice, not a silent publishing side effect. Keep Media/Storage cleanup out of Gallery ownership.
6. The existing public Preview and General device-frame preview are separate contexts; a future shared conceptual preview belongs to a coordinated design pass, not an unapproved General refactor. No Validation rollout or DB migration authorized.

## Next
- Continue focused Stage integration repairs, prioritizing correctness over UI additions.
- When source issues are resolved, request targeted browser acceptance without unnecessary audits, tests, backups or deployment.
