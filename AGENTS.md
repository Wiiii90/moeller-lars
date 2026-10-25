# Agent entry — docs branch

Start at [the single worker protocol](.agents/README.md). It resolves **numbered workers** through their shared [Dev](.agents/areas/dev.md), [Docs](.agents/areas/docs.md), [Testing](.agents/areas/testing.md) or proposed [Refactor](.agents/areas/refactor.md) coordination file. An explicit worker replacement resumes the saved worker context; a role greeting alone does **not** execute a past task. Follow the current user's instruction.

**Write boundary:** This checkout writes documentation only on `docs`. Application code is on `dev`; the curated test branch `testing` is active. Never overwrite `dev/AGENTS.md` with this docs-only entrypoint. A `refactor` branch is not yet authorized or created. Future cross-branch rollout or integration requires a separately reviewed decision.

**Durable authorities:** [product contracts](docs/README.md), [architecture compositions](docs/architecture/cross-feature-compositions.md), [Docs method](.agents/procedures/documentation.md) and [test safety](.agents/procedures/testing.md). Prefer the current code and explicit user acceptance over obsolete snapshots; check all consumers of a shared primitive. Distinguish reviewed source, written tests, executed tests, browser review and user acceptance.

**Safety:** No Continuous Integration (CI)/GitHub Actions access, release, deployment, main promotion, Production or artist-curated Validation modifications. Persistent local Preview is not disposable. Use the existing Docs worktree only in rare, expressly authorized Codex batches. Recheck remote branch heads and use concurrency-safe, non-forced updates.
