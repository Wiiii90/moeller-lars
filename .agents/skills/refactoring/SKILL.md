---
name: refactoring
description: Perform user-authorized behavior-preserving structural refactoring on the refactor branch.
---

# Refactor task entry

The authoritative refactoring work rules are in this branch's [AGENTS.md](../../../AGENTS.md). This is an entry to that existing procedure, not an independent policy or second worker registry. The user assigns actual scope; no historical role number or branch automatically grants a task.

1. Confirm latest `dev` and `refactor` source revisions and the affected accepted behaviors.
2. Map real consumers, persistence/migration/history constraints and the net diff; remove duplicate owners rather than adding adapters.
3. Preserve cross-feature behavior and give the Testing owner precise missing regression scenarios; do not modify tests or general documentation here.
4. Final reviewed transfer occurs only after user authorization and reconciliation with the latest integrated source.

Read the relevant canonical [system architecture](../../../docs/ARCHITECTURE.md) and [feature contract index](../../../docs/README.md), not duplicated source instructions.
