# Docsworker — current state

- **Status:** maintaining the documentation-only `docs` branch; never edit `dev`.
- **Objective:** source-align architecture with new `dev` commits, consolidate normative owners and maintain a flexible agent-workspace system so role names load relevant living context without auto-executing prior assignments or forcing a handoff prompt.
- **Source snapshot being reviewed:** `dev@35163591efb98eb51770a9c3061dda260fe25aba` (25 October 2026), 25 commits after prior `dev@74e629274a`; check fresh HEAD on resumption. Test-workstream verification: `testing@b7eee549b86ac35eaa6a064ac78ef226182c170e` actively curated, 21 commits ahead and 6 behind `dev` at that snapshot.
- **Current accepted decisions:** user wants normal dialogue or an explicit task after a role greeting; **no automatic backlog execution, no handoff documents**, no old root skill filenames, bounded in-place subject/state updates, infrequent Codex batches only, real system interactions instead of exclusively class diagrams. New Docs source audit is not a rerun of native generators/tests.
- **Canonical docs:** [documentation procedure](../DOCUMENTATION.md), [worker registry](../WORKERS.md), [architecture owner index](../../README.md), [technical evidence](../../architecture/completion-criteria.md).
- **Open integration:** current `dev/AGENTS.md`, `ui-skills.md`, `followup-skill.md`, `TESTING_SKILL.md` and active Rights work must not be overwritten indiscriminately. User expressed future intent, not an instruction to modify `dev` now. Documentation integration must make the new agent entrypoint visible to direct-dev chats; the active `testing` branch and its eventual suite return to `dev` require separate coordination.
- **Safety:** no CI, no Validation, no local Docs worktree unless rare authorized Codex batch, no release.
- **Open questions / possible next subjects:** coordinate an explicitly authorized docs→dev integration (protecting newer dev instructions and existing worker state), preserve the independently active `testing` workstream, and discuss whether `refactor` justifies an additional branch. The user may instead discuss a different subject; **no automatic next executable action**.
- **Evidence:** GitHub branch comparison and remote source/relative-reference review; no new native PHP, application or browser tests for this batch.

Keep this subject/state file current by replacing stale paragraphs, not by appending a session log. It may contain open discussion and questions without a task to execute.
