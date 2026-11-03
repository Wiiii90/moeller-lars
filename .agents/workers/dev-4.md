# Dev Worker 4 — branch-local progress

**Lease:** 7
**Branch:** `dev`
**State:** Active — implementing user-approved Gallery/Artwork corrections; source and browser acceptance separate.

## Assignment and scope
- User commissioned corrections to Gallery/Artwork remove/recovery, bulk selection, Materials table/autosave, ordering/drag, public presentation; narrowly scoped tests are explicitly authorized for this worker.
- Scope: Gallery-workspace actions, Artwork assignment and presentation, material preset owners, directly affected views/styles, scoped browser tests. Do not touch Dev Worker 2 media ingest/feedback, Dev Worker 3 rich text or Dev Worker 1 exhibitions.

## Evidence and progress
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

## Next action
- Implement bounded root-cause slices, read/reconcile latest dev head before each guarded write. No CI/deploy. Browser/runtime acceptance remains unverified without an actual isolated environment.
