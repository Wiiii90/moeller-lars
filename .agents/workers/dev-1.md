# Dev Worker 1 — Journal

- **Working branch:** `dev`
- **Lease:** 42 (replaced previous lease 34 on 2026-11-22)
- **Assignment:** Continue the actual Journal workstream (Blog and Exhibitions), particularly Exhibition Stage Settings and user-approved defaults simplification; propose, but do not prematurely implement, new layout/effect options.
- **Ownership:** `JournalWorkspace`, Journal Stage and its private Public preview, exhibition-specific settings and organizer/venue catalogs. Dev Worker 5 owns shared Page/Journal template architecture, Dev Worker 4 Gallery shared surfaces, Dev Worker 8 dialog chrome; update direct consumers without creating second systems.

## Current implemented baseline (source integrated; browser not accepted)

- Journal New is a direct-entry editor with Discard and Submit; Selected edits persistent entries with batch Discard/Submit, conflict checks and a protected in-memory Public preview; Settings uses Journal presentation settings. The session-staged queue has been retired, with legacy session data preserved for manual recovery.
- Selected Stage stays on the **first selected** row. Additional checkbox selections must not navigate/reload the editor or preview. New draft form changes must refresh the private Public preview. Stage New/Settings heights and scroll ownership must remain stable.
- Blog detail URLs are ID-based with legacy redirects; Blog Draft remains an explicitly supported state; Rich Text Blog excerpt exists. Do not reintroduce global Journal auto-publish or Journal entry slug inputs.
- Organizer and Venue are **independent named catalogs**, with optional website/address, alphabetic ordering, directly editable manager-table fields, delete only, and canonical quick-create dialogs. Selecting a Venue transfers its address snapshot into an Exhibition, never couples it to Organizer. Historic Exhibition snapshots remain independent of subsequent catalog edits.
- Exhibition Description and per-Exhibition Gallery use the shared `AdminPanelControl`, retaining content when disabled. Gallery presentation is optional, individual to each Exhibition, with no required-field star. Video playback controls inherit defaults unless per-media explicitly overridden.
- Source correction committed on dev on 2026-11-22 in `bffb99be`: Journal Stage selection/preview and Organizer/Venue catalog simplification, including forward migration `2026_11_22_150000_simplify_exhibition_directories_and_description_visibility.php`. This migration remains **unexecuted/unverified** here.
- Historical schema caution: original `2026_11_21_180000` organizer-linked Venue migration may already have executed. Preserve that published migration and use the later forward schema reconciliation, not a rewrite/reset. The artist previously reported a local Preview duplicate Venue relation failure; source correction was published, not runtime-verified.
- All current integration claims above are **source-only**. No local runtime, migrations, automated tests, Continuous Integration, deployment or real browser acceptance is established in this chat.

## User decisions and ongoing proposal (2026-11-22)

- Remove **Default Gallery Presentation** completely from global Exhibition/Journal Settings. Gallery presentation belongs to each Exhibition, not the global defaults.
- Group the remaining global toggles inside the **central AdminPanelControl** labelled **Activate Defaults**: **Enlarge images on click** and **Show video playback controls**. Avoid duplicated controls/parallel state.
- Remove **Enable Exhibition Gallery** from global defaults; gallery activation remains a per-Exhibition panel.
- **Show vernissage until it begins by default** is pending clarification: do not remove or repurpose the stored behavior until explaining its exact current semantics and the difference between event date visibility and pre-start special presentation.
- Artist wants restrained additional visual layout/effect options in Stage Settings and the corresponding settings dialog, but first proposals and template-vs-primitive boundary. No visual modes are approved for implementation yet.

## Remaining acceptance / constraints

- Review New/Selected/Settings transitions and first-selected checkbox behavior, New/Selected in-memory preview, Publishing/Draft state, long text, gallery/video handling, Organizer/Venue manager inline edits and quick-create dialogs across Wide/Compact/Narrow/Minimal.
- Verify existing migration chains on disposable fresh/incremental databases before exposing persistent data to schema changes. No unapproved migration, build, broad tests, deployment or data changes.
- Reconcile current `dev` head before writes; preserve cross-worker ownership and canonical shared controls. Implementation and direct consumers only; test changes belong to Testing worker.
- Pending durable docs consolidation: Journal editor lifecycle, first-selected focus, independent organizer/venue catalogs, per-Exhibition presentation and no global gallery enablement. Keep here until Docs owner synchronizes.

## Next

Read current Journal Settings source, actual Stage/form entrypoints, current defaults persistence/consumers and shared `AdminPanelControl`; perform user-approved global settings simplification, then propose a small visual-settings model with explicit user choice before adding new options. Update this state for a clean next-chat takeover.
