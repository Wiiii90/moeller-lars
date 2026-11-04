# Testing Worker 1

- Branch: `testing`; lease: 3; status: final stabilization in progress.
- For this final qualification, the user authorizes this worker to correct confirmed application, migration, test and test-infrastructure defects directly on `testing`. Dev remains frozen at `33f598f94afd6717cc3bb234b0d85c14e758491f`; selective integration requires separate approval.
- Database-writing tests require a positively verified disposable PostgreSQL 17 instance and isolated storage, cache, sessions, media and temporary paths. Use the fail-closed PowerShell runners; never use persistent local databases.
- Published checkpoint `a976516d8418db7ea0d9564087c521a777cb08e5` includes the Home list no-op correction and the stable dialog geometry test. All four protected Chromium dialog scenarios passed at this checkpoint.

## Execution evidence

- At `e65b04ecbcb8e8079f57a128f39ac19cf69b4190`, `RunPublicationConcurrency.ps1 -QualificationSuite Feature -Linux --compact` passed 532 cases and 4617 assertions. It reported missing local `.env` warnings in the Linux bind. The subsequent published change only concerns the browser dialog test, so this is not a final same-commit qualification.
- At the same checkpoint, the full browser discovery passed 58 of 64 cases. The dialog geometry failure was resolved and all four protected dialog scenarios passed in `a976516d`. The remaining failures cover intermittent Rich Text image saving and mobile Gallery sorting gestures. A full final browser run remains required.
- Earlier runs passed 97 Unit cases (359 assertions), 43 JavaScript tests, 21 Migration cases (131 assertions), 155 Integration cases (1011 assertions), 10 separately isolated Publication concurrency cases (518 assertions), 3 separately isolated Public Session concurrency cases (125 assertions), and 7 Chromium component cases. The Integration run delegates the 13 concurrency cases to their separate runners. PHPStan and repository-wide Pint passed at their recorded earlier snapshots.
- `npm run test:js` passed all 43 tests again during the current continuation.
- Targeted Custom Page component Feature coverage passed 29 cases and 316 assertions, including stale-editor protection. Rich Text link, table and related editor checks passed in targeted Chromium and desktop WebKit runs; image selection remains intermittently affected by the race below.

## Open findings and next actions

1. Reproduce and fix the intermittent Rich Text image save race at its canonical owner. A retained trace in `storage/framework/testing/browser-caption-focus/` shows an asynchronous Filament Select `getOptionsForJs` request overlapping `callMountedAction`; a subsequent save reports `This component changed since the editor was opened. Reopen the editor.` and the reopened component can lack the second image. Preserve stale-editor protection and existing shared editor consumers; do not introduce a workaround.
2. Investigate the Gallery mobile browser sort failures. Prior instrumentation showed the existing Playwright `dragTo` generated trusted mouse events but no touch or native drag events under mobile emulation. Desktop Chromium and WebKit sorting passed. Validate any corrected browser gesture without weakening order, persistence, audit or notification assertions; native touch acceptance must not be inferred from mouse simulation.
3. Review, test, commit and fast-forward push coherent corrections regularly. Keep local modifications and reconcile the remote before publishing.
4. Run the complete authorized qualification on one final committed source tree: Unit, Migration, Integration, Feature, JavaScript, Chromium component and end-to-end, desktop and mobile WebKit, profiling, both isolated concurrency runners, PHPStan and Pint. Record commands, counts, warnings, skips, isolation evidence and final SHA.
5. Do not start workflows or deployments. Do not transfer changes to Dev without separate authorization. Qualification remains open until the complete same-commit run succeeds or a concrete external blocker is demonstrated.
