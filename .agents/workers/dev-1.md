# Dev Worker 1 — Journal

- **Working branch:** `dev`
- **Lease:** 42 (replaced previous lease 34 on 2026-11-22)
- **Status:** Exhibition Settings simplification committed as `cd8036a8b9c19719a8d97dd0496f555743d49cbf`; static source inspection only. Migration, runtime and browser acceptance remain pending.
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

- **Implemented in source:** The Exhibition Settings Stage and dialog no longer offer Default Gallery Presentation. Blog continues to own its separate default.
- **Implemented in source:** One central **Activate Defaults** panel holds Enlarge images on click and Show video playback controls. The new persisted switch defaults on for existing Journals and off restores native values (image viewer off, video controls on).
- **Implemented in source:** Global Exhibition Gallery and Vernissage defaults are retired and dropped through a new forward migration; each Exhibition still owns its own switches.
- **Vernissage semantics inspected:** The Exhibition-level visibility is shown only until its structured Vernissage timestamp (or only while upcoming for legacy text), provided explicitly enabled; Starts/Ends remain visible. The user has not chosen any change to this per-Exhibition behavior.
- **Still only proposals, not approved:** Separate **Exhibition page presentation** from per-Exhibition gallery presentation. Prefer one page-layout preset rather than a cluster of unrelated toggles: current **Catalogue**; alternative **Editorial rhythm** (stable alternating left/right media and restrained larger/smaller emphasis); optional later **Gallery-led** (more prominent gallery). A single **Regular / Varied** choice might be warranted only if the preset itself cannot express rhythm. Any "randomness" must be deterministic per entry and stable between page loads; no jumping layout, no media-order changes and no per-item surprise overrides.
- **Vernissage follow-up for user:** Current `show_vernissage` means show only before the start; it is not "hide after start" versus "show throughout". Consider a future explicit per-Exhibition three-way choice **Hidden / Until start / Through exhibition end**, retaining old data semantics. The Stage currently exposes Starts/Ends but not its existing per-Exhibition Vernissage datetime/toggle, unlike the canonical Edit dialog; decide whether that belongs in the Stage before adding another control.
- Artist wants restrained visual settings mirrored between Stage and corresponding Journal dialog, with no proliferation of global gallery options. No visual mode or Vernissage behavior change has been authorized yet.

## Remaining acceptance / constraints

- Review New/Selected/Settings transitions and first-selected checkbox behavior, New/Selected in-memory preview, Publishing/Draft state, long text, gallery/video handling, Organizer/Venue manager inline edits and quick-create dialogs across Wide/Compact/Narrow/Minimal.
- Verify existing migration chains on disposable fresh/incremental databases before exposing persistent data to schema changes. No unapproved migration, build, broad tests, deployment or data changes.
- New forward migration `2026_11_22_160000_make_exhibition_defaults_explicit.php` must be checked on disposable fresh/incremental schemas. Reconcile `dev` before writes; test changes belong to Testing worker.
- Pending durable docs consolidation: Journal editor lifecycle, first-selected focus, independent organizer/venue catalogs, per-Exhibition presentation and no global gallery enablement. Keep here until Docs owner synchronizes.

## Next

Review published Exhibition Settings simplification in browser (Stage/dialog and new Exhibition creation), validate the forward migration, and propose restrained layout/effect presets versus per-Exhibition options before implementation; reconcile Blog media and Testing expectations. Update this state for a clean next-chat takeover.
