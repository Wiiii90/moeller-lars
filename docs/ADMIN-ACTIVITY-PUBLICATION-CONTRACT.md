# Admin Activity and publication contract

## Scope

Activity and Publication are related but deliberately different systems.

- **Activity** answers what administrative change happened, when and by whom.
- **Working state** is the current editable state in `public.*`.
- **LIVE state** is the current published snapshot in `committed.*`.
- **Commit history** answers which complete website versions were published and how they relate.

Notification/inbox state is not part of this contract. See [ADMIN-NOTIFICATION-CONTRACT.md](ADMIN-NOTIFICATION-CONTRACT.md).

## Activity

`audit_events` is append-only factual history. Successful mutations create new events; undo/reset/restore/revert operations never rewrite or delete the events that came before them.

Activity uses the shared `AdminActivityFeed` read model. The normal Activity view has four semantic table roles:

```text
Activity | Publication | Who / when | Actions
  2/6    |    1/6      |    1/6     |  2/6
```

Visible row actions are `Details` and, when a target exists, `Open record`. A safe per-event Undo is offered inside Details only when `AdminActionReceiptService` has a currently valid actor-scoped receipt.

### Date and time filters

Calendar and clock are not decorative secondary controls.

- Clicking a calendar day applies the actual Activity date filter.
- Clicking an hour applies that hour in addition to the selected date.
- Date/Time are reflected in the normal filter toolbar and can be cleared there.
- Without an explicit date filter the table uses the normal bounded Activity window; today's calendar highlight is only visual focus.
- Calendar density retains year context so selecting one day does not collapse the visualization itself.

## Publication source of truth

The application intentionally has two current database states:

```text
public.*       = working/admin state
committed.*    = current LIVE state
```

`PublicationSnapshot::TABLES` is the closed tracked-table set. Publication delta is the row-level semantic difference between those two states, ignoring framework-only `created_at`/`updated_at` differences.

`PublicationService::pendingSummary()` is the source of truth for **Pending changes**. Pending audit-event count is separate context and must never be presented as the size of the next commit.

## Preflight

`PublicationService::preflight()` reports only rules the current publication path genuinely enforces. Today this includes:

- whether any tracked delta exists;
- `public`/`committed` schema parity.

Do not invent UI-only readiness rules that the Commit path itself does not understand. Domain write services remain responsible for their own mutation invariants before state reaches publication.

## Commit identity and lineage

Every new publication Commit is a permanent `publication_checkpoints` record with:

- a full 64-hex SHA-256 `hash`;
- a full `snapshot_hash` of the complete tracked publication state;
- the publication `schema_hash` used by the snapshot;
- parent commit relation;
- optional source commit relation for restore/revert operations;
- actor, message, row-delta count and publication time;
- operation kind (`commit`, `restore`, `revert`, or initial legacy state).

The UI normally displays the first 10 hex characters while retaining the full hash as identity/integrity metadata.

Commit hashes are application-level logical publication identities. They are not Git repository commit SHAs.

## Full restorable snapshots

`publication_version_rows` stores the complete tracked database snapshot for every new Commit as one JSONB payload per table row. Media binaries are **not** duplicated per version; snapshots reference the canonical MediaAsset identities/storage keys.

This project intentionally favors full database snapshots over reconstructing historical state from Activity deltas. The data set is small enough that simplicity and deterministic restore are more valuable than delta-chain compression.

Historical checkpoint metadata that predates this snapshot system remains visible in Commit history. Such legacy checkpoints are explicitly `metadata only`; the UI must not offer Restore when a real compatible snapshot does not exist.

A retained snapshot is restorable only while its stored `schema_hash` matches the current publication schema. Schema-incompatible history remains inspectable rather than being restored through unsafe best-effort coercion.

## Commit permanence

Commit metadata is permanent history.

- There is no flush/delete action in the admin UI.
- PostgreSQL rejects deletion of `publication_checkpoints` at the database boundary.
- Reverting/restoring creates later working state and, once explicitly committed, a new Commit. Existing commits remain in the chain.

The current implementation retains full version snapshots indefinitely together with commit history. If bounded snapshot retention is introduced later, it must preserve permanent commit metadata and must integrate with Media cleanup before any snapshot payload is pruned.

## Working changes

The Commits view begins with **Working changes** rather than pretending the latest Commit is the only relevant state.

It exposes:

- true pending row count;
- grouped affected publication areas/entities;
- current preflight status;
- `Review changes`;
- `Reset staged changes`;
- `Commit`.

### Reset staged changes

`Reset staged changes` means exactly:

```text
committed.* -> public.*
```

The replacement is transactional under the publication advisory lock and schema guard. It discards all current staged content state while preserving Activity and Commit history. The reset itself is a new Activity event.

Reset is not implemented by replaying individual Undo receipts.

## Restore this version

`Restore this version` applies a retained historical snapshot to **Working** only:

```text
LIVE/committed = current HEAD
Working/public = selected historical snapshot
```

The live website does not change. The resulting current-vs-working delta is reviewed through the normal publication surface and requires an explicit later Commit.

The later Commit gets operation `restore`, retains the selected source Commit and has the previous LIVE Commit as its parent.

Restore replaces all current staged work. The confirmation copy must say so explicitly.

## Revert commit

`Revert commit` is intentionally available only for the current LIVE Commit and only when its parent is a compatible retained snapshot.

It stages that parent snapshot as Working state. It does not delete the current Commit and it does not publish automatically.

Given:

```text
A -> B -> C (LIVE)
```

staging a revert of C loads B into Working. After review and explicit Commit the history becomes:

```text
A -> B -> C -> D
              D = revert of C, content equivalent to B
```

This is safer than pretending an arbitrary old Commit can be inversed across unknown later edits. Older historical states use `Restore this version` instead.

The later Commit gets operation `revert`, retains the reverted LIVE Commit as its source and keeps normal parent lineage.

## Three reversal levels

The admin therefore has three deliberately separate reversal mechanisms:

```text
One supported editorial action
Activity Details -> Undo

All unpublished work
Working Changes -> Reset staged changes

Published state
Commits -> Restore this version / Revert current commit -> review -> Commit
```

They must not be collapsed into one generic Undo button.

## Media retention

Publication snapshots reference canonical media instead of cloning binaries.

Physical Media files may be deleted only when none of these still require the asset:

- current Working state;
- current LIVE/committed state;
- any retained restorable publication version;
- canonical current media references.

`PublicationMediaCleanupService` checks retained `publication_version_rows` before physical deletion. This ensures a historical version cannot become formally restorable while its binary assets have already disappeared.

## Activity integration for publication controls

Reset/Restore/Revert staging operations create normal immutable Activity events in the `Publication` area:

- `publication.stage_reset`;
- `publication.version_restored`;
- `publication.commit_revert_staged`.

Those events target the concrete publication Commit when possible and link to the Commits view. A restore/revert staging event becomes part of the next Commit generation when it leaves Working different from LIVE.

## Transactions and locks

Commit, Reset, Restore and Revert use the shared publication advisory lock. Replacement of tracked Working/LIVE tables happens transactionally.

Unsafe partial restore is not acceptable. If schema validation, FK constraints or snapshot hydration fail, the transaction rolls back rather than using `CASCADE`, silently skipping rows or publishing partial state.

## UI terminology

Use these labels consistently:

- `Activity`
- `Commits`
- `Staged`
- `Committed`
- `Pending changes`
- `Working changes`
- `Current live version` / `LIVE`
- `Review changes`
- `Reset staged changes`
- `Restore this version`
- `Revert commit`
- `Commit`

Avoid `flush`, `checkpoint` as artist-facing primary terminology, `staged for next publish`, or permanently disabled Undo actions.

## Verification

Durable tests should prove at least:

- Commit creates a 64-hex SHA-256 identity and complete snapshot rows;
- current LIVE state equals the committed snapshot after Commit;
- Reset restores Working exactly to LIVE while preserving Activity;
- Restore changes Working but not LIVE until Commit;
- a restore Commit records parent and source lineage;
- Revert stages only the current LIVE parent and publishes later as a new Commit;
- Commit history cannot be deleted at the database boundary;
- media referenced by any retained version cannot be physically deleted;
- Activity publication-control events project concrete Commit targets;
- date/hour Activity filtering works beyond the default Activity window;
- Activity aggregate query cost remains bounded.
