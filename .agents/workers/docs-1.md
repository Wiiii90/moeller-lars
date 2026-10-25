# Docs Worker 1 — active

**Slot:** `docs-1` · lease **2** · [Docs roster](../roster.md). Current assignment: consolidate documentation and agent workflow, respecting user decisions and deleting obsolete structure.

## Rules and authority

- The user decides architecture, scope, exceptions, freeze/merges and acceptance. Actual `dev` code is the descriptive basis for Docs; if it conflicts with a newer user decision, ask rather than canonizing the conflict.
- Before adding a file, evaluate whether an existing owner should be merged, moved, renamed or deleted. No pre-release alias/compatibility-stub/workflow duplication; protect persisted database/migration/history compatibility.
- Dev workers own implementation and active workstream status, **not general docs or tests**. Testing owns the curated suite; Docs owns durable explanation; Refactor owns behavior-preserving cleanup.
- Work only within Docs ownership. Use fresh expected-head commits and reviewed path-filtered transfer; never import branch-specific worker history into another branch.

## Verified work

- `ui-skills.md`, `followup-skill.md` and `TESTING_SKILL.md` deleted from `docs` and `dev` (and later from `testing`/`refactor`). Canonical Admin UI/Responsive contracts and direct links replace them.
- Documentation navigation and browser process consolidated; architecture coverage index shortened; dated audit dump and copied rights handoffs removed. Historical source revisions remain in Git history.
- User-directed staged workflow now recorded in this branch's [worker protocol](../README.md#continuous-work-and-integration-gates), [Docs method](../skills/docs/SKILL.md#preparation-versus-final-transfer) and [Testing contract](../../docs/operations/testing.md#development-batches-and-final-testing-gate): parallel preparation, cumulative changes, user freeze, final isolated Codex verification/generation, reviewed transfer.
- Two unmaintained ADR files containing public infrastructure/cost details deleted from the **tip trees** of `docs`, `dev`, `testing` and `refactor`. Their links were updated. `main` remains blocked by protected-branch pull-request/required-check policy. **History is not sanitized** by these deletions.
- Branch-specific, concise Testing/Refactor `AGENTS.md` replace inherited Dev manuals on their branches; current application and test-source files were not changed by this Docs workflow action.
- Remote local-Markdown-target checks found no remaining missing path for changed Docs/Dev files; Testing/Refactor tree sweeps checked all branch Markdown paths. Native `php scripts/check-documentation-links.php --check`, generated docs validation and actual tests/browser were **not** executed here.

- Current source-first product reconciliation from `dev@e8db4295`: `docs/public/site.md` now covers Gallery-only Artwork detail surface override (Follow General / No gap / Separate Surface), separate portrait-height opt-in and concrete video-controls differences. `docs/DATA-MODEL.md` and `docs/architecture/artwork-gallery-publication.md` reflect the two forward migrations/Working/Committed fields. `docs/media.md` and `docs/architecture/media-reference-and-cleanup.md` retain the current source-confirmed Image-child/Home-List/List-direct-media gaps without claiming runtime failures or new tests.
- Narrow remote link check of seven touched documents resolved 122 Markdown-style link destinations; one `media:ID` example is a custom media scheme, not a filesystem link. Sampled affected heading anchors directly; native PHP documentation validator is still pending the final authorized Docs gate.

## Open next

1. **One-time deferred pre-main reminder:** The user explicitly chose to postpone the public ADR/history cleanup **until the next transfer/push to protected `main`**. Before that event, the responsible worker must remind the user and request specific permission for a coordinated all-ref history rewrite/protection-aware update; normal Pull Requests and tip deletions do not purge past commits. This gate is recorded in [the existing release contract](../../docs/operations/release.md#accepted-candidate-promotion-to-main). Once resolved/cancelled, **remove the temporary release paragraph and this item**, not add a reminder file.
2. **Continue full source-first audit on `docs` now:** Public Gallery/Artwork and structured-video settings updated; core Media reference readers rechecked. Next prioritize remaining Admin, Journal/Exhibitions, Rich Text, rights/privacy, architecture and generated inventory evidence against the **current Dev source**. Reconsider file ownership and delete/rename obsolete structures, not new parallel manuals; distinguish actual implementation from user approval. Do not regenerate everything as routine work.
3. At user-authorized Docs integration, reconcile exact frozen accepted source, complete relevant generation only then, run native link/heading checks in an isolated authorized checkout, and transfer only reviewed durable docs.

**Do not misreport:** source inspection is not a successful test, current browser acceptance or release qualification.
