# Docs agent entry

This checkout is the **documentation workstream on `docs`**. Read the short [branch-local worker protocol](.agents/README.md), [Docs coordination](.agents/areas/docs.md) and—if currently assigned—the numbered worker's own task record. A worker greeting selects/registers an identity, **not** an automatic task. Every substantive report begins with the registered worker name.

**Responsibility:** explain and consolidate the application, architecture and accepted behavior; review current `dev` code as the baseline. The active Testing and Refactor branches are not routine Docs sources until their changes return to `dev`, unless the user explicitly requests a separate inspection. [Documentation method](.agents/procedures/documentation.md) governs source reconciliation and evidence; [project docs](docs/README.md) are the permanent readable output.

**Branch boundary:** This branch owns only Docs' temporary `.agents/areas/docs.md`, `.agents/workers/docs-*.md` and unresolved Docs investigations. Other branches register and retain their own workers **on their own branch**. Future Docs→Dev integration must be a selective, reviewed transfer of permanent content, **not an unrestricted merge or inherited Docs history**. Docs normally imports current Dev source while preserving branch-local scope.

**Safety:** Current `dev/AGENTS.md` remains authoritative for direct development. No CI/GitHub Actions, release, deployment, main promotion, Production or artist-curated Validation interaction. Persistent local Preview is protected. Native Docs worktree/Codex batches require separate authorization. Recheck Git refs and use concurrency-safe, non-forced updates.
