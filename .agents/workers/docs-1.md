# Docs Worker 1 — active

**Slot:** `docs-1` · lease **2** · [Docs roster](../areas/docs.md). Current assignment: consolidate documentation and agent workflow, respecting user decisions and deleting obsolete structure.

## Rules and authority

- The user decides architecture, scope, exceptions, freeze/merges and acceptance. Actual `dev` code is the descriptive basis for Docs; if it conflicts with a newer user decision, ask rather than canonizing the conflict.
- Before adding a file, evaluate whether an existing owner should be merged, moved, renamed or deleted. No pre-release alias/compatibility-stub/workflow duplication; protect persisted database/migration/history compatibility.
- Dev workers own implementation and active workstream status, **not general docs or tests**. Testing owns the curated suite; Docs owns durable explanation; Refactor owns behavior-preserving cleanup.
- Work only within Docs ownership. Use fresh expected-head commits and reviewed path-filtered transfer; never import branch-specific worker history into another branch.

## Verified work

- `ui-skills.md`, `followup-skill.md` and `TESTING_SKILL.md` deleted from `docs` and `dev` (and later from `testing`/`refactor`). Canonical Admin UI/Responsive contracts and direct links replace them.
- Documentation navigation and browser process consolidated; architecture coverage index shortened; dated audit dump and copied rights handoffs removed. Historical source revisions remain in Git history.
- User-directed staged workflow now recorded in this branch's [worker protocol](../README.md#continuous-work-and-integration-gates), [Docs method](../procedures/documentation.md#preparation-versus-final-transfer) and [Testing contract](../../docs/TESTING.md#development-batches-and-final-testing-gate): parallel preparation, cumulative changes, user freeze, final isolated Codex verification/generation, reviewed transfer.
- Two unmaintained ADR files containing public infrastructure/cost details deleted from the **tip trees** of `docs`, `dev`, `testing` and `refactor`. Their links were updated. `main` remains blocked by protected-branch pull-request/required-check policy. **History is not sanitized** by these deletions.
- Branch-specific, concise Testing/Refactor `AGENTS.md` replace inherited Dev manuals on their branches; current application and test-source files were not changed by this Docs workflow action.
- Remote local-Markdown-target checks found no remaining missing path for changed Docs/Dev files; Testing/Refactor tree sweeps checked all branch Markdown paths. Native `php scripts/check-documentation-links.php --check`, generated docs validation and actual tests/browser were **not** executed here.

## Open next

1. **Protected `main` ADR removal:** requires a separately authorized protected-branch pull-request/check workflow; this workstream cannot bypass the protection. Decide separately whether public Git history warrants a coordinated privacy purge; file deletion alone is not sufficient.
2. **Only after the above requested workflow work:** full semantic/current-source documentation audit of Public, Admin, Media, Rights, architecture references and generated artifacts. Consolidate outdated or misowned material; do not start by producing new documents.
3. At user-authorized Docs integration, reconcile exact frozen accepted source, complete relevant generation only then, run native link/heading checks in an isolated authorized checkout, and transfer only reviewed durable docs.

**Do not misreport:** source inspection is not a successful test, current browser acceptance or release qualification.
