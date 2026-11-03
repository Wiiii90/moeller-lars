# Dev Worker 4 — Gallery/Artwork

**Lease:** 8  
**Branch:** dev  
**State:** Active — 2026-11-03; historical checkpoints 9 and 10 passed local read-only preflight. Actual restore and browser acceptance outstanding.

## Ownership and boundaries

- Own Gallery/Artwork controls, metadata, sorting, Materials and public Artwork presentation. Own the bounded historical publication read-time projection needed for Artwork date cutover; coordinate any shared Exhibition or media boundary.
- Dev Worker 1 owns Exhibition dates/address/maps, Dev Worker 2 owns Storage/media lifecycle and cleanup; Dev Worker 3 previously owned Rich Text. Testing owns test code; Docs owns durable product documentation after acceptance.
- Direct `dev` only. Never initiate Continuous Integration, deployment, release, Validation mutation, real database migration or destructive staging without express permission. Verify the current head, roster and lease before every write; account for concurrent changes.

## Canonical accepted implementation

- **Artwork date:** Persistent `year`, nullable `month` and nullable `day` are the sole representation; no `work_date`, `work_year` or `date_precision` in active Working/Committed state. `artwork_date` is a virtual Admin picker. `ArtworkDate` owns localized parsing/display. Gallery, Home ranking, sorting, Public, import/export and the historical reader use canonical fields. A date can have year only, year/month or complete day precision; never invent an absent day. Source cutover commits `20ddb05`, `ae7c9fe`.
- **Local migrations and schema:** User executed `2026_11_03_150000_replace_artwork_date_with_components.php` (Batch 76), then `2026_11_03_160000_align_committed_site_section_meta_description.php` (Batch 77), repairing the earlier missing Committed counterpart. User confirmed all 15 tracked tables passed `PublicationSchemaGuard::assertParity()`. Locally Working Artworks: 137 (136 fully dated); Committed Artworks: 138 (137 fully dated); neither retains old date columns. These observations are local only; no Validation migration has been executed.
- **Admin:** Artwork draft may exist without a Storage file. Add/Edit never uploads directly; Gallery dropzone or Storage owns uploads. Clearing its media selection unlinks without deleting Artwork or original. Delete removes the slot, preserving original by default; optional file cleanup goes through central media owner. No redundant Remove/Restore actions. Artwork ALT override, Materials/Position and responsive height × width × depth/unit controls. Material presets use the canonical persistent Admin table. Gallery manual order, newest-date mode and one-shot Sort once.
- **Shared/Admin and Public:** A cross-component Table/Add Row divider suppression was removed at the owning style rule; review Materials and Exhibitions together if touched. Additional Artwork image editing/relation retired; historical records remain for separate inventory. Public links reflect Committed visibility, not private Working. Empty description divider fixed; public portrait/landscape image fit, menu width and surface modes still require real browser acceptance.
- **Already accepted by user:** Gallery filtered multi-selection. Do not repeat browser verification unless its implementation changes.

## Historical publication checkpoints — local evidence (2026-11-03)

- Historical payload data and original snapshot hashes remain immutable. `PublicationVersionService` reads source-era dates into canonical Artwork year/month/day; projects known older Exhibition dates, absent Gallery `artwork_menu_surface_wide` (historical false default), absent SiteSection `meta_description` (from historic Gallery description), and removes retired `media_variants.transform_profile` only from projected rows. Reject all unknown shape differences, invalid historical dates or conflicting media-variant uniqueness; validate original snapshot checksum. Publication History uses the same eligibility rule for staging/revert; this is not a parallel restore engine.
- `verifyCheckpointReadOnly()` verifies schema parity, restore eligibility, original snapshot checksum and full projected row differences without mutating Working, Committed, history or media. Only the currently Live checkpoint also compares projected Artwork rows with Committed.
- **Checkpoint 10: PASS** — user ran PHP syntax check successfully, then `verifyCheckpointReadOnly(10)`: `historical_date_projection=true`, `differences_from_working=98`, `committed_artwork_differences=0`. Zero verifies projected Artwork equality with Committed according to that method; 98 indicates different current editable rows, not invalid history.
- **Checkpoint 9: PASS** — user ran corrected one-line `verifyCheckpointReadOnly(9)`: `historical_date_projection=true`, `differences_from_working=96`, `committed_artwork_differences=null`. Null is expected for a checkpoint that is not current Live. The earlier PowerShell `>>` prompt was caused by an unfinished closing quote, not service latency.
- These are **read-only preflights**, not evidence that a full destructive Stage/Restore and rights/media reconstruction succeeded. Before any actual restore, construct and independently confirm a disposable database **and** isolated media store. **Never** stage/revert on persistent `local-preview`: it can overwrite Working and queue media cleanup. No Validation deployment.
- User made a local `pg_dump -Fc` backup at `C:\Users\Wiiii\moeller-lars-checkpoint.dump` (680686 bytes, 2026-11-03 16:25:58); restore viability is untested. Avoid standalone `git pull`, `git reset` and manual stash instructions; `local-preview.ps1` already handles source synchronization and an unnecessary pull previously disturbed Windows index state.

## Remaining acceptance and delegated work

1. **Isolated restore qualification:** Testing/appropriate user-authorized execution must verify checkpoint 9 and 10 staged restore, full row parity, Working/Committed isolation, rights/media effects and safe rollback on fully disposable database+media data; keep immutable historical hashes untouched. No destructive trial on persistent local preview.
2. **Gallery/Artwork browser:** Empty draft/slug, file selection and unlink preservation, ALT, Home eligibility; year-only/month-only/full-date locale picker, invalid/Clear, reopen and correct newest/order/Sort once. Delete with optional media cleanup on disposable assets. Materials presets including escaped markup and responsive Add Row borders; compare Exhibition boundary. Confirm accepted filtered selection unaffected.
3. **Public fit:** Check portrait/landscape sizes across menu width and Artwork surface modes. This is still artist-reported broken; do not claim browser approval based on source inspection.
4. **Testing handoff:** Canonical date fixtures/import/export, migration atomicity, range/leap day/null semantics, snapshot projection/checksum, historical isolated restore, Safari/WebKit/touch ordering, Working/Committed and notification regression. Dev does not write or modify test files.
5. **Deferred:** Multi-admin stale-form concurrency refactor; orphan Artwork records left by retired Remove; historical additional-media audit. Media deletion behavior belongs to Worker 2, Exhibition scope to Worker 1. Docs to publish final accepted product contracts after browser/runtime gates.

## Next

- Both historical checkpoint read-only checks are complete. Do not rerun without a new reason.
- Await explicit authorization and independent disposable database+media isolation before destructive restoration qualification.
- Continue the scoped Artwork/Gallery browser acceptance and public portrait/landscape sizing work when an executable preview is available, keeping source-only and browser-tested conclusions distinct.
