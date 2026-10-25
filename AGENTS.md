# Repository agent entrypoint — documentation branch

**Worker orientation:** Read [agent entrypoint](docs/agents/README.md), [worker registry](docs/agents/WORKERS.md), then the relevant living record when one exists. A message such as **"Du bist Dev Worker 1, arbeite."** identifies a conversation/work area; it does **not** automatically authorize execution of the last recorded task. The user's current message determines whether to discuss, investigate, start new work or explicitly continue previous work. Do not invent unsaved chat history or require a task assignment before discussion.

**Branch authority:** This checkout's `docs` branch is documentation-only. Application implementation belongs to `dev`; the active test-curation workstream also maintains `testing` for later integration into `dev`. Each branch requires its own current authority and task authorization. This `docs` checkout writes documentation only. **This Docs copy is not the current direct-development policy and must never overwrite `dev/AGENTS.md` during future integration.** Do not create branches, isolated worker worktrees or Pull Requests without separate authorization. The existing `testing` ref is a historical branch, whereas `refactor` is a **possible new branch**, not yet created or approved. A worker role is not a branch. Future Docs→dev integration requires separate authorization and conflict review.

**Required documents (read selectively):**
- [Product and normative owner index](docs/README.md), [composed architecture](docs/architecture/cross-feature-compositions.md).
- [Durable Admin user interface](docs/ADMIN-UI.md), [Public](docs/PUBLIC-IMPLEMENTATION-CONTRACT.md), [Rich Text](docs/EDITORIAL-RICH-TEXT.md), [Exhibitions](docs/EXHIBITION-EDITORIAL-CONTRACT.md).
- [Documentation procedure](docs/agents/DOCUMENTATION.md) and [Docsworker state](docs/agents/workers/docsworker.md) for Docs work.
- [Tests and safe environment](docs/agents/TESTING.md); living work records and their update rules are described in the [agent entry](docs/agents/README.md).

**Immutable workflow rules:** One canonical owner per domain/editor and per presentation primitive. Identify all affected consumers and preserve accepted interaction before a refactor. Distinguish source review, authored tests, executed tests, browser checks and actual artist acceptance. Do not add duplicate UI/persistence/sort/notification systems, timer workarounds or tests of superficial visual repair details. Preserve multi-worker changes through fresh HEAD, compare, and expected-SHA fast-forward updates.

**Safety:** No CI/Actions interaction of any kind (including status/log reads), no deploy, no release, no Production or Validation modifications. The artist's Validation content is protected cutover data. Local persistent Preview is not a disposable test target. `P:\moeller-lars-docs` is reserved for **rare expressly agreed Codex batches**, not routine documentation updates.

**Current provenance:** Last selected `dev` owner review: `dev@b289a9102a3b91c15ad2e182372d074fb64ce239`; verify a fresh `dev` SHA on every new task. Prior native source-only documentation/generator results remain historically bound to their original `docs` checkout, not the live `dev` code.
