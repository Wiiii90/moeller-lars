# Testing Worker 1 — active assignment

- **Branch:** `testing`
- **Lease token:** `2` (user-authorized chat replacement of token 1)
- **State:** active
- **Assignment:** Consolidate new Dev worker handoffs into the canonical existing suite first, then inspect remaining cumulative Dev behavior. Testing owns tests only; no application changes on Dev, no CI/deploy or final qualification without user instruction.

## Source parity and ownership

- **Last exact source comparison:** `dev` `570f9f987757022655003bea8fc73393880204e2` versus `testing` `544566d265948ff5d65f36a669de2ad725c3fb10`: **zero differing tracked paths outside Testing-owned `tests/`, `playwright.config.mjs`, root `AGENTS.md` and branch-local worker files**. This is source parity, *not* a passing test result.
- Integrated current non-test Dev sources in bounded reviewed batches, excluding Dev worker files. Preserve Testing-owned deletions and edits and check both branch heads/lease before every write. Recheck any later Dev commits.
- Past curated work is retained in Git: Gallery settings and Working/Committed selection tests, Journal/video rights, Home/Pages selection, migration and Publication preservation, media lifecycle and original bytes.

## Current handoff — Dev Worker 2 media/uploads (prepared, not run)

- Existing media lifecycle tests now check persistent originals, thumbnails and video posters, WebM/VP9, baseline vs large progressive JPEG. Retired `MediaVariantRegenerationService` and `transform_profile` usages removed across feature/integration/support fixtures. `tests/Migration/MediaVariantSchemaMigrationTest.php` intentionally reconstructs the historic field.
- Reused existing Free Storage, Publication and media usage suites for orphan reclamation, protection of required previews and unassigned registered media, historical restoration/release, and Home plus nested Custom Page Rich Text ownership. Private incoming-media staging is redirected into owned temporary storage and original application storage root restored at teardown.
- New distinct HTTP owner `tests/Feature/Admin/Storage/ResumableMediaUploadTest.php`: authorization, true ingest and SHA-256/preview, resumable offsets, chunk rollback, finish replay, deduplication, session ownership/cancel and incomplete preview failure.
- New distinct JavaScript owner `tests/js/admin-media-upload.test.mjs`: changed middle file bytes despite equal filename, size, timestamp and first/last sample cannot reuse a staged upload receipt. Existing notification suite covers truthful saved/reused/failed/partial/no-op feedback.
- New distinct Migration owner `tests/Migration/MediaVariantSchemaMigrationTest.php`: populated public/committed cutover, safe duplicate-preview abort, independent media migration despite site_sections drift, and full Publication parity rejection.
- Existing browser projects broadened to WebKit desktop/mobile Gallery, public media, navigation and touch cases. Playwright WebKit **does not** prove native macOS/iOS Safari acceptance; separate real-browser evidence needed.

## Pending

- Further Dev worker handoffs expected from user, followed by a bounded whole-change test-architecture consolidation. The latest Gallery/Artwork `year/month/day` source change has initial caption, Home and Gallery fixture updates, but remaining date-model/migration acceptance is not yet curated.
- Review lost-final-response and true concurrency under isolated execution; confirm all new or changed assertions and fixtures through actual test runs. Complete historical Publication version payload restore coverage before cutover.
- **Final gate only upon user-declared Dev freeze:** check exact source parity at frozen SHA, positively verify independent disposable PostgreSQL 17, media/tmpfs and effective Laravel test connection, then authorize targeted/full Codex/Pest/Node/Playwright runs. Fix test defects here; report real product defects back to Dev. Transfer only curated tests/support after explicit approval. No CI, production/preview/validation database writes or protected storage access authorized.

**Execution:** Only GitHub source/tree reviews and authored commits. No PHP/Pest, PostgreSQL, Node, Chromium/WebKit or native Safari tests executed. No coverage measured.

## Current handoff — Rich Text / Dev Worker 3 (2026-11-03)

- Resumed active lease 2 on `testing` after user-requested continuation; expressly **do not import later `dev` revisions** until the user requests the next reconciliation. Source baseline remains `570f9f987757022655003bea8fc73393880204e2`.
- User-observed acceptance: native Storage insertion once, editor caption beneath image, popovers, tables, colors and spacing. Previously alleged duplicate insertion was **not reproducible**; abandoned alternate insertion implementation is not a contract.
- Added pure output tests in canonical Rich Text Unit suites: repeated-image/caption association with all placements and escaping, intentional caption removal, image count mismatch, public table/spacing/color/link rendering and unsafe-link rejection. Source edits only, never executed.
