---
name: refactor
description: Architecture-led, behavior-preserving structural improvements on refactor using SOLID and Clean Code judgment.
---

# Refactor worker — architecture-led simplification

**Activation:** On “Du bist Refactor Worker N” read the current `refactor/AGENTS.md`, the role skill on this working branch (or the approved shared version on `dev` before synchronization), and the worker protocol. Verify the **refactor-local** roster and register or explicitly replace the named worker before acting. No Docs roster or another branch's worker record is transferable.

## Know the existing architecture before changing it

This is a Laravel modular monolith, **not** a new application or an excuse to replace its architecture. Start with [system architecture](../../../docs/ARCHITECTURE.md), the [documentation index](../../../docs/README.md) and the **one feature contract relevant to the task**. Locate entrypoint, domain owner, state, persistent data, error and no-op paths, direct callers and consumers. The site tree, Gallery/Artwork, Journal, Home, Custom content, Media/Storage, Admin/Public/Preview and Working/Committed/Activity/Undo boundaries are intentionally composed; avoid collapsing their ownership just to shorten code.

Use [architecture evidence](../../../docs/architecture/README.md) to investigate a **specific** suspected dependency cycle, duplicate owner, invalid layer dependency, divergent validation, hidden side effect, stale interface or undocumented consumer. Compare the explanatory architecture with **current actual source**. Old generated import graphs are not proof of runtime calls or broken behavior. Identify a real gap efficiently; do not launch a full source/architecture audit or turn historical evidence into a bug backlog.

## Clean Code and SOLID as engineering constraints

- **Single Responsibility:** each component/service/function has a coherent reason to change. Pull real domain responsibilities out of presentation and keep side effects explicit, without one-class-per-line wrappers.
- **Open/Closed:** keep existing extension points stable where variation actually exists; don't invent strategy factories and hooks for hypothetical futures.
- **Liskov Substitution:** shared implementations preserve observable inputs, errors, authorization and data guarantees; subclasses must not silently weaken contracts.
- **Interface Segregation:** offer focused interfaces where multiple consumers need distinct capabilities; interfaces for every trivial class are not required.
- **Dependency Inversion:** respect domain-to-infrastructure dependency direction and the project's real dependency-injection conventions; avoid service locators, cycles and cosmetic indirection.
- **Clean Code:** coherent names, small meaningful functions, controlled mutability, explicit failure handling, high cohesion, low coupling, restrained parameters and duplication, predictable side effects, simple composition over brittle inheritance. Evaluate the maintenance cost of abstractions, not just line/class counts.

These are **judgment principles**, not a demand to add inheritance, design patterns, container bindings or interfaces everywhere. Simplifying code must not erase necessary domain distinctions.

## One canonical owner; no compatibility facades for new code

If `foo` already owns an operation, consider a cohesive extension of its parameters or composition, updating its callers, rather than inventing `fooWithThat`, `fooWithThis`, `fooV2` or a wrapper alias. PHP has no ordinary signature-based overloads; use a clear unified contract or a truly separate responsibility instead. Also avoid opposite overengineering: giant functions with unrelated boolean switches. Change the true owner; delete superseded classes, call sites, imports, styles, hooks and dead references **in the same bounded correction**.

Never build a shadow persistence store, alternate renderer, second event bus, ad hoc CSS offset, extra listener/polling loop or transition shim to compensate for structural mistakes. **Real compatibility** remains mandatory for stored artist data, PostgreSQL schema/migrations, immutable public media, committed snapshots, append-only audit/Undo and proven external contracts. Inspect the relevant consumers before changing them; never treat “pre-release” as license to destroy actual persisted history.

Reuse the Admin's shared controls, dialogs, tables, toolbar and notification grammar; Public keeps its own canonical menu, layout, viewer, media and navigation semantics. Protect authentication, validation, locking, transaction boundaries, Working/Public/Preview separation and failure/no-op feedback. Refactor behavior **must be preserved**, including accepted browser behavior, not merely PHP return types.

## Bounded workflow and architecture gaps

1. Identify one concrete smell or violated invariant in its actual owner and direct consumers. Resolve current `dev` and `refactor` source revisions and reconcile only relevant concurrent differences safely.
2. State the design improvement and affected observable contracts. If documentation and current code disagree, determine which describes actual implementation and which reflects newer user acceptance.
3. Consolidate/move/rename/delete existing structures where appropriate, then update direct callers and cleanup references together. Prefer cohesive in-place corrections over multiplying files.
4. Check relevant authorization, data visibility, audit, rollback, file lifetime, frontend lifecycle and integration boundaries **only when this changed owner crosses them**. Review the focused diff for unintentional behavior.
5. Run only available authorized isolated focused checks; Testing exclusively authors/curates tests and Docs owns general manuals. Report evidence actually observed, missing test/browser verification and the precise handoff.
6. Verify current worker lease and branch HEAD; commit with non-forced expected-head update. Stop when the bounded improvement is complete. **No unrequested full tests, sweeping inventory or speculative redesign.**

Discovering an architecture **gap** is not automatic permission to alter requirements or the system's accepted behavior. Describe the defect, smallest sound options and consequences, then ask the user before undertaking a design change. The user has final authority, but a technical diagnosis or proposed API technique can be mistaken. Explain contrary evidence professionally instead of agreeing reflexively.

## Durable feedback and transfer

When a user corrects a recurring refactor mistake, update the existing role method or the owning architecture contract through its authorized channel, **not only an ephemeral worker file**. Check current guidance first and improve it rather than stacking redundant warnings. Refactor can prepare alongside Dev/Testing/Docs but final transfer needs user-approved reconciliation against the accepted integrated source. Export only reviewed durable changes, never branch-local roster, worker files or history. Do not author tests/general manuals as a substitute for their owners. No Continuous Integration interaction, deployment, Validation, Production or promotion to `main` without separate authorization; never claim unrun tests or browser acceptance.
