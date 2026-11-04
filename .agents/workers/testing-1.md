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

## Current continuation

- Published Rich Text checkpoint: `82912076`. Public policy checks passed in Chromium and desktop WebKit, Artwork viewer checks passed in Chromium, and the targeted Admin dialog and selection checks passed in Chromium.
- The published Rich Text checkpoint covers popover visibility and focus, keyboard handling, image toolbar boundaries, caption handling and the associated browser test. The separate save race remains unresolved and final qualification is open.
- Chromium and desktop WebKit both passed the Rich Text table test after collapsing the selection before inserting the table. The link interaction passed twice in each browser after correcting the pointer focus lifecycle. The 29 Custom Page component Feature cases passed with 316 assertions, including the stale-editor protection. The JavaScript suite passed 43 tests. A prior eight-case Rich Text run had five passes and three failures; two table failures were addressed in the later targeted pass.
- Repeated image insertion can produce a stale-edit validation error and lose a subsequent save. In `storage/framework/testing/qualification-caption-focus.log`, Chromium saved only one of two inserted images after reopening. The retained trace shows the first `callMountedAction` save overlapping the Filament Select `getOptionsForJs` request, which is explicitly sent asynchronously by `vendor/filament/forms/src/Components/Select.php`. The following save returned `This component changed since the editor was opened. Reopen the editor.` A subsequent `--repeat-each=2` run on `9ef09728` produced three passes and one WebKit failure with the same missing second image after reopening. Preserve the existing stale-editor protection. Investigate the canonical coordination of asynchronous read requests and editor saves without weakening concurrent-editor safeguards or introducing a parallel lifecycle.
- Resume by resolving the Rich Text save race and repeating the image, caption, link, keyboard and table scenarios in both browsers. Verify all shared editor consumers and the protected dialog behavior before treating the local application changes as complete. Then address the remaining Gallery and Home Feature failures and mobile browser cases, and execute all qualification suites on one final commit.
