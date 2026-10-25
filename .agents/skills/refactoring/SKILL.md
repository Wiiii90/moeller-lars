---
name: refactoring
description: Review and perform behavior-preserving cleanup of the moeller-lars modular monolith with evidence-based dead-code removal and safe synchronization from dev.
---

# Refactoring workflow

Entry: root [AGENTS](../../../AGENTS.md), shared [Refactor coordination](../../areas/refactor.md), then the **actually activated** numbered worker's current task record under [workers](../../workers/). General worker allocation/replacement is owned by [one protocol](../../README.md).

The `refactor` branch starts from `dev` and periodically reconciles newer `dev` commits. Never assume the two HEADs are identical; check before touching code. The task must remain behavior-preserving, narrowly scoped and coordinated with active Dev ownership.

1. **Map the real consumer graph**, including Laravel/Filament/Livewire reflective registration, routes, templates, event/listener wiring, migrations, cached/history payloads, authorized Public/Preview exposure, tests and feature flags. A text search with zero hits is not proof of dead code.
2. State existing externally observable and persisted contracts, cross-feature callers and migration/rollback constraints *before* deletion or relocation. Contrast unused artifacts with currently active historical compatibility.
3. Prefer removing a redundant implementation or simplifying at its single owner to creating a parallel alternative. No opportunistic product changes, visual rearrangement, persistence rewrites, separate test frameworks or speculative general-purpose abstractions.
4. Use relevant and **meaningful behavior-based tests** from the current architecture, with strict database/runtime isolation. Do not preserve obsolete visual or markup tests merely because they exist; no test execution is claimed without execution evidence.
5. Stage small, reviewable changes; reconcile recent `dev` work without silently overwriting concurrent changes. Record important architectural decisions in [canonical Docs](../../../docs/README.md), and active task/progress in the worker's **own** file. Update Refactor shared coordination at scope changes, and sign out when retired.
6. Return coherent, verified cleanup to `dev` by a separately agreed conflict-reviewed integration. Keep testing branch source/verification reality distinct; CI/Actions and protected environments are out of scope without approval.

This skill owns **method**, not the permanent system design, which belongs to [architecture](../../../docs/ARCHITECTURE.md) and the component/feature contracts.