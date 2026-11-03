# Dev Worker 4 — branch-local progress

**Lease:** 7
**Branch:** `dev`
**State:** Active — implementing user-approved Gallery/Artwork corrections; source and browser acceptance separate.

## Assignment and scope
- User commissioned corrections to Gallery/Artwork remove/recovery, bulk selection, Materials table/autosave, ordering/drag, public presentation; narrowly scoped tests are explicitly authorized for this worker.
- Scope: Gallery-workspace actions, Artwork assignment and presentation, material preset owners, directly affected views/styles, scoped browser tests. Do not touch Dev Worker 2 media ingest/feedback, Dev Worker 3 rich text or Dev Worker 1 exhibitions.

## Evidence and progress
- Removed redundant Remove action from Gallery cards, Edit header and Selected; removed arbitrary Restore removed artwork selector from Add artwork. Gallery source now has one Delete dialog (slot-only default, original file optional), and Media file selection remains a separate unlink action. Hidden standalone Artwork resource no longer permits creating new unassigned records. Code source only, not runtime verified.

- Material dialog table-only Viewer now uses explicit central dialog spacing variant; no page-specific negative margins or wrapper overrides. Requires visual review.
- Public Artwork description is now safely rendered once and its divider is omitted when only empty rich-text markup is present; other image/paper geometry remains unverified rather than patched speculatively.
- Restored shared lockedTarget resolution in the simplified generic Undo path; no historical extra-media reversal remains available.
- Retired secondary Artwork images: removed GalleryImagesRelationManager UI and all active additional-media commands. Legacy Audit labels remain readable; historical Undo receipts no longer offer the retired operation. Existing stored links and Storage media remain untouched pending data inventory. Generic audio upload untouched.
- Material table: replaced proportional Position/Actions tracks with canonical role-width columns, delayed compact icon-only rail until real narrow pressure, removed duplicate Add Row seam, made drag handles non-native draggable and decoded legacy HTML entity display. Browser screenshots still required.
- Corrected reconstructed Edit save request's gallery assignment inputs immediately; the create action now derives a missing slug from the submitted title, avoiding a race with the title-blur suggestion. Neither behavior is browser verified.
- User browser feedback: removed unrequested Add existing toolbar action and the extra Artwork upload action (row/editor/form). Existing Media file picker now precedes Material/Year; ALT text override renamed. Only Gallery dropzone/Storage upload media; Add artwork creates media-less drafts or reuses an unassigned slot via optional select inside same dialog. Bulk Unpublish now direct, as bulk Publish. Runtime not verified.
- Public Gallery action links now derive exclusively from Committed publication state/slug rather than Working. Added Working → Commit → pending Unpublish regression sequence; browser still unverified.
- Scoped lifecycle tests now cover remove → reassign same Artwork slug/media, filter-pruned selections and exact-date versus year-only ordering.
- Reconciled the dedicated GalleryMaterialPresetsTest (old sync() expectations were stale) and registered the new reorder action in the canonical AdminActionCatalog; kept GallerySettingsTest only on Gallery settings.
- Added dedicated public Artwork fit browser matrix with portrait/landscape fixtures and three viewport shapes, targeted to new WebKit/mobile projects; isolated seeder only, not production data.
- Material inline Add now awaits the same canonical server mutation result before clearing the input; invalid duplicate/empty names remain editable, no second autosave listener.
- General admin live preview favicon now uses canonical MediaPreviewVariant rather than dead public-v1 thumbnail path; old profile remains historical until proven data cleanup.
- Explicit exception: replaced obsolete Gallery Settings/Materials and upload tests with current stateful editor and resumable queue coverage; scoped Playwright WebKit Desktop, iPhone emulation and Android Chromium projects added. Not executed; disposable environment required.
- Root-cause source fix for Artwork viewport fitting: consistent viewport measurement and image sizing across narrow/wide layouts; runtime/browser acceptance outstanding.
- Corrected blocked sorting explanations (Manual versus filters) and Working publication metric wording; preserved filter-independent absolute Position editing.
- Aligned PublicArtworkQuery thumbnail profile with the canonical current MediaPreviewVariant, removing obsolete profile acceptance from the public read model. Historical data cutover remains separately unverified.
- Replaced Material preset Repeater/command with canonical modal table and immediate domain mutations; introduced persistent manual position and retained IDs on rename. Migration/runtime/browser pending.
- Implemented source-level recovery via Gallery Add existing: unassigned Artworks retain identity and media; filter-hidden selections are pruned and guarded before batch operations. No runtime verification available.
- Read current `dev/AGENTS.md`, `dev/.agents/skills/dev/SKILL.md`, `dev/.agents/README.md` and `dev/.agents/roster.md` before registration.
- Initial registration on dev at base `b5a7f186e3192e0e7739582ab03844b505b1b03a`.
- No application changes, tests, browser review, deployment or workflow execution.

## Deferred decisions / handoff
- **Remove versus Delete — accepted and implemented in Gallery code:** Only Delete remains. It deletes a draft Artwork slot and keeps the source file by default; optional checkbox deletes the original through the media domain after reference-impact review. To unlink only the file while retaining the Artwork slot, clear Edit → Media file. Removed standalone/selected Remove and the artificial Restore selector. Existing previously detached, unassigned Artwork rows must be inventoried before deliberate data cleanup; never delete them silently.
- **Delete cross-reference issue (Dev Worker 2 ownership):** The current MediaAssetEditorialService::removeCanonicalReferences() additionally detaches other Artworks when their primary file is deleted; review whether these other Artwork slots should instead remain as media-less drafts under the newly agreed semantics. Do not patch Dev Worker 2's active media domain here.
- **Global future refactor:** concurrent multiple-admin editing may overwrite newer changes across editors; revisit after Gallery work under a separately assigned worker. Consider version/conflict contracts globally, not a Gallery-local patch.
- **Testing Worker (not yet requested):** reconcile previously created Gallery tests after rejected upload/extra-image actions, test Safari/WebKit sorting and Working/Committed public-link isolation. User explicitly does not want Testing handoff prompt yet.
- **Upload Worker 2:** owns legacy media-profile cleanup, no work here.
- **Public image sizing:** still reported visually broken across portrait/landscape and Menu/Page combinations; only empty-description divider corrected. Do not claim image geometry accepted without real browser evidence.
- **Materials:** retain user screenshot as failed visual acceptance; after browser acceptance update canonical Dev method/table/dialog UI contract with specific checks against premature icon-only rows, column alignment and wrong spacing.

## Next action
- Implement bounded root-cause slices, read/reconcile latest dev head before each guarded write. No CI/deploy. Browser/runtime acceptance remains unverified without an actual isolated environment.
