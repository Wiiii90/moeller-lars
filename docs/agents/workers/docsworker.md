# Docsworker — current state

- **Status:** maintaining the documentation-only `docs` branch; never edit `dev`.
- **Objective:** source-align architecture with new `dev` commits, consolidate normative owners and construct low-friction agent continuation so "Du bist Devworker 1, arbeite" resolves a persisted assignment instead of a prompt.
- **Source snapshot being reviewed:** `dev@b289a9102a3b91c15ad2e182372d074fb64ce239` (25 October 2026), 23 commits after prior `dev@74e629274a`; check fresh HEAD on resumption.
- **Current accepted decisions:** user requests no long task prompts, no old root skill filenames, bounded in-place state updates, infrequent Codex batches only, real system interactions instead of exclusively class diagrams. New Docs source audit is not a rerun of native generators/tests.
- **Canonical docs:** [documentation procedure](../DOCUMENTATION.md), [worker registry](../WORKERS.md), [architecture owner index](../../README.md), [technical evidence](../../architecture/completion-criteria.md).
- **Open integration:** current `dev/AGENTS.md`, `ui-skills.md`, `followup-skill.md`, `TESTING_SKILL.md` and Rights temporary handoff must not be overwritten indiscriminately. User expressed future intent, not an instruction to modify `dev` now. Documentation integration also must make the new agent entrypoint visible to direct-dev chats.
- **Safety:** no CI, no Validation, no local Docs worktree unless rare authorized Codex batch, no release.
- **Next executable step:** after the current Docs-only commit, review any newer `dev` HEAD and implement a separately authorized controlled docs→dev integration, including retiring legacy root names on `dev`; if not authorized, continue source-based doc audits only.
- **Evidence:** source and remote relative-link review; no new native PHP or browser tests for this batch.

Replace this record at each meaningful Docs update; do not append a running log or a new Docs worker file.
