# Dev Worker 2 — active session

**Working branch:** `dev`  
**Lease:** 6 (previous Dev Worker 2 session retired with lease 2)  
**State:** Active; registered 2026-10-31 by explicit user assignment.  
**Current assignment (2026-10-31):** Investigate a reported 28 MB JPEG upload failure on the artist's site; own only canonical media ingest/derivative-processing diagnosis and any subsequently evidenced correction. Do not alter adjacent Admin dialogs, Public display or other worker claims.

## Startup completed
- Read `AGENTS.md`, `.agents/skills/dev/SKILL.md`, `.agents/README.md`, `.agents/roster.md` on `dev` and the previous Dev Worker 2 handoff record.
- Registration baseline (before this commit): `2b24eaadf6919922797b9420511b56a4e91b51d2`.
- Respect other active worker claims: Dev Worker 1 owns Public Exhibitions and dialog-lifecycle tasks; Dev Worker 3 owns shared Admin Rich Text editor work. Coordinate any overlapping future scope.
- No application source, tests, durable product documents, Continuous Integration workflows, deployments or runtime environments changed during onboarding.

## Previous Dev Worker 2 — context, not an inherited assignment
- Previous session's complete historical progress remains recoverable from Git history of this file. It is **not** an active task list for the new session.
- Previous worker reported a healthy Validation application after deployment of application commit `cdc7761f85b08c2e9d15b3e021fd9c3608c6b830`; the user observed significant visual divergence between Validation and local, including Admin. Root cause remains **unverified**; do not silently change styling or claim the deployment is visually accepted.
- Correct existing local preview command on the user's Windows PowerShell: `& 'P:\moeller-lars\scripts\local-preview.ps1'`. The local Preview is the required browser comparison baseline; a healthy container is not visual acceptance. Do not operate the user's local machine from this connector-only environment.
- The previous session implemented quota-driven, resumable/chunked Gallery and Storage uploads and several Gallery/Admin refinements. Actual large-file transfer, interruption/resume, progress and notification behavior, image/video processing, current Validation parity and local browser acceptance remain unverified.
- A prior database migration moving Gallery meta description ownership to Pages was reported as source-only; no migration execution is established here. Production is not to be altered without explicit authorization.
- No automatic tests, new deployments, code fixes or follow-up tasks are authorized by this onboarding request.

## Operational gate for next task
Check fresh `dev` HEAD, own lease and other claims before every write. Scope the task to its canonical implementation owner, reconcile parallel changes, commit only to `dev` using a checked non-forced head update, record actual verification and distinguish source inspection from runtime/browser/user acceptance. No CI, deployment, Test-owned test edits, broad audits or cross-branch transfer without explicit authorization.

## 2026-10-31 — JPEG upload failure triage
- Artist reports a roughly 28 MB JPEG rejected with a generic `could not be processed` message. Runtime source and original file are not available in this worker's environment; do not claim a fixed decoder, quota or memory issue without evidence.
- At current source, `MediaIngestService::prepareForIngest()` wraps non-validation exceptions into generic `The uploaded media could not be processed.`; a second generic wrap exists around quota admission, so UI wording alone cannot identify the failing stage. `MediaDerivativeGenerator` runs `vipsthumbnail` (libvips) with 120-second timeout, one worker and 960px WebP output, but currently discards subprocess error details; no cause is reported.
- Allowed images include JPEG, pixel ceiling 500 megapixels, file-byte allowance is derived from site quota; 28 MB by itself is not a rejected size. The current release image has a finite container memory budget; exact runtime outcome unverified.
- Resumable upload finalization leaves a failed `.part` original in private staging for up to 48 hours; safe inspection of its actual dimensions, container memory events and independent media processor result can distinguish image decode failure from resource or quota failure without modifying application source.
- **Next:** Obtain read-only Validation runtime evidence before implementing a correction. Preserve media originals/staging and do not rerun heavy image transformations inside the active serving container without isolation. No source fix, runtime check, CI, deployment or browser acceptance completed.

## 2026-10-31 — Upload Notification precision correction
- The artist's 28 MB JPEG failure exposed two source-confirmed message defects: the derivative generator discarded stderr/exit cause and the ingest wrapper suppressed unexpected exceptions without logging; Storage notifications also prepended meaningless zero counters before the file-specific error.
- Source correction keeps the canonical MediaDerivativeGenerator, MediaIngestService and AdminNotifier pipeline. The generator now logs bounded processor stderr/exit/timeout and emits stage-specific, safe ValidationException messages (memory confirmed by stderr, JPEG decode confirmed by stderr, timeout, invalid preview, otherwise explicit unclassified preview failure). Unexpected ingest/capacity errors are logged separately and labeled with their actual stage. A lone failed Storage selection shows filename and reason without 0/0/1 counters; mixed results keep nonzero counts. No UI notification engine was added.
- **Runtime evidence still needed:** server logs and memory events for the 14:29 failed JPEG, original dimensions/processor stderr, browser display of notification and one-notification cardinality. User's Validation image is not automatically updated by this source commit. Don't claim actual JPEG upload repaired or diagnosed until checked.
