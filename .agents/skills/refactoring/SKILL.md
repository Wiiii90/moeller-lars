---
name: refactoring
description: Safely simplify the Lars Möller Laravel application on the refactor branch while preserving accepted runtime, data, publication, and UI behavior.
---

# Refactor branch workflow

1. Read this branch's root [AGENTS](../../../AGENTS.md), then the **current canonical** [worker protocol](https://github.com/Wiiii90/moeller-lars/blob/docs/.agents/README.md) and [Refactor shared coordination](https://github.com/Wiiii90/moeller-lars/blob/docs/.agents/areas/refactor.md). They are on the **docs** branch, not stored or duplicated here. A worker's numbered record is created on `docs` only when the user opens that worker; the shared area must record activation, scope, lease and later departure.
2. Resolve the user's assigned task and its current progress from **that worker's own** active record (if present). A role greeting does not automatically resume old work. Use the record to track task completion and blockers; consolidate durable findings into project contracts and stable methods.
3. Compare current `refactor` with current `dev`, then identify the true source owners and all consumers of the proposed change: Laravel/Filament/Livewire registered entrypoints, routes, Blade views, events, background jobs, migrations, old publication/Undo history, media availability, and test/compatibility contracts. No import reference is not proof of dead code.
4. Refactor only while preserving observable behavior and stored state. Remove proven dead branches, simplify duplicate pathways at the **one canonical owner**, and clean fragile abstractions with evidence. Never introduce parallel frameworks, speculative type systems, timeouts, broad unrelated migrations, or changed visual behavior as a cleanup by-product.
5. Coordinate all overlapping files with current [Dev work claims](https://github.com/Wiiii90/moeller-lars/blob/docs/.agents/areas/dev.md) before editing. Periodically merge/reconcile newer `dev` work into `refactor`. Use meaningful behavioral verification in independently isolated environments; do not freeze cosmetic table pixels. Clearly separate source review, authored tests, executed tests, browser acceptance and user acceptance.
6. Integrate small proven improvements back to `dev` by an expressly agreed conflict-reviewed merge; **do not** directly change `dev` as a side effect of the refactoring task. At departure update the Refactor shared roster, transfer durable conclusions and delete a retired worker's temporary record on canonical `docs`.

Current accepted architecture reference and owner index: [docs architecture](https://github.com/Wiiii90/moeller-lars/blob/docs/docs/ARCHITECTURE.md) and [docs index](https://github.com/Wiiii90/moeller-lars/blob/docs/docs/README.md). The other branch's source must be checked against active code rather than assumed always current.
