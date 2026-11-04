# Testing Worker 1

- Branch: `testing`. Lease: 3. Status: active final stabilization.
- The user authorized this worker to correct confirmed application, migration, test and test-infrastructure defects on `testing` for final qualification. Dev is frozen at the previously checked candidate `33f598f94afd6717cc3bb234b0d85c14e758491f`. Selective transfer to Dev requires separate user authorization.
- Published checkpoint: `1c8c151c` includes the verified resumable upload fixture lifetime correction. Subsequent local changes to Rich Text and browser tests remain uncommitted and require investigation and verification.
- All database-writing tests must use a positively verified disposable PostgreSQL 17 instance with independently isolated storage, cache, sessions, media and temporary paths. The PowerShell runners fail closed on isolation-preflight failure. Never use persistent local databases.

## Recorded execution evidence

- At earlier checkpoints: 97 Unit cases and 359 assertions, 43 JavaScript tests, 21 Migration cases and 131 assertions, 155 Integration cases and 1011 assertions, 10 separate Publication concurrency cases and 518 assertions, 3 separate Public Session concurrency cases and 125 assertions, and 7 Chromium component cases passed. The Integration run delegates 13 cases to the separate concurrency runners and reported missing `.env` warnings.
- PHPStan and repository-wide Pint passed at their respective checked snapshots.
- `RunPublicationConcurrency.ps1 -QualificationSuite Feature -Linux --compact --filter ResumableMediaUploadTest` passed 5 cases and 77 assertions on the local fixture correction, with missing `.env` warnings. Its effective PostgreSQL connection and disposable paths were verified before execution.
- The latest broad Feature run covered 533 cases, with 11 errors and 8 failures. It predates the published Home and upload fixture corrections. Remaining cases include Activity media readiness, Gallery materials and settings, Home notification behavior and a missing Public publication fixture helper.
- The latest focused Rich Text run had eight failures across Chromium and desktop WebKit. Mobile Gallery drag and some public browser checks also remain unresolved. Local browser edits are experimental and must not be presented as verified.

## Continuation

1. Inspect and resolve each remaining Feature failure at its canonical owner, retaining full assertions. Repeat affected tests.
2. Diagnose Rich Text modal Escape, focus, media selection and browser setup failures, then resolve remaining public and mobile browser cases without changing accepted interactions through workarounds.
3. Review, test, commit and fast-forward push coherent corrections regularly. Preserve other local modifications and reconcile remote changes before each push.
4. Run the complete authorized qualification on one final committed source tree: Unit, Migration, Integration, Feature, JavaScript, Chromium component and end-to-end, desktop and mobile WebKit, profiling, both isolated concurrency runners, PHPStan and Pint. Record exact commands, counts, warnings, skips, environment evidence and final SHA.
5. Do not start workflows or deployments. Do not transfer changes to Dev without separate authorization. Qualification remains open until the full consistent-tree run succeeds or a demonstrated external blocker prevents it.
