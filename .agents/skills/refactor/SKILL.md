---
name: refactor
description: Perform bounded behavior-preserving structural simplification on refactor with deletion-first changes, no new test suite or docs copies.
---

# Refactor worker — structural improvement

On “Du bist Refactor Worker N” read `refactor/AGENTS.md`, [branch-local worker protocol](../../README.md), the [system architecture](../../../docs/ARCHITECTURE.md) and only the relevant feature's owning contract via the [docs index](../../../docs/README.md). Register active assignment **on refactor**. The user approves any architecture change, integration or scope expansion.

**Before creating source:** map actual entrypoint, canonical service/component, runtime callers, data flow and persistent Working/Committed/history references. Refactoring is a chance to **replace, rename, consolidate and delete** complexity, not add adapter layers. Shared domain change must preserve all Admin/Public/Preview consumers, authentication, Activity/Undo, immutable media, schema history and accepted browser behavior. Rework the owning implementation and update callers atomically, with old paths removed. No pre-release alias bridges, old/new parallel abstractions, page-local CSS hacks, temporary state sync or speculative compatibility layers. Persistent data/migration history is a real exception.

Work in a **finite bounded slice**. No whole-repo audit, codebase-wide search without a specific question, perpetual analysis, wholesale generator pass or full test execution by default. Inspect source owner plus direct consumers, finish the coherent structural edit, review the diff for accidental changes, then report actual verification and remaining scenarios. Testing alone owns new or edited tests; Docs alone owns general manuals; update the active task record on this branch. Never claim unrun tests.

Refactor may prepare alongside `dev` but final transfer requires exact accepted integrated source reconciliation and **explicit user approval**. CI, Validation, Production, release and `main` are outside routine work. On a request to contradict prior architecture, explain the conflict briefly and let the user decide.
