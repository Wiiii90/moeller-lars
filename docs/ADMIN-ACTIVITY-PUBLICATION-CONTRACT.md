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

Activity uses the shared `AdminActivityFeed` read model. The current Activity table exposes the factual row roles directly:

```text
Change | Who | When | Target | Area | Type | Publication | Actions
```

The Activity workspace uses the shared six-unit admin alignment system, but semantic table columns may subdivide or span those units according to the ordinary table contract. Do not preserve an older four-column `Activity | Publication | Who / when | Actions` layout as a compatibility surface.

`Details` is the stable first row action. When `AdminActionReceiptService` exposes a currently valid actor-scoped receipt, `Undo` appears immediately after Details and applies the inverse as a new audited editorial action. Rows without a safe current receipt simply omit Undo; they do not expose a permanently disabled undo control.

Activity and Commits are two views of the same workspace and switch through the normal View control. Both retain the applicable Search/Area/Type/Date/Time filter context. Activity and Commits use bounded pagination with the shared page-size choices `25`, `50` and `100`.

Both table views implement the shared trailing Selection contract: row checkboxes occupy the far-right rail, the visible select-all checkbox sits directly above them, and the Selection trigger's count badge shares that horizontal axis. The Activity multi-action menu supports Details for exactly one selected event, Undo for selected events that still expose safe receipts, and Clear selection. The Commits menu supports Details for exactly one selected Commit plus Restore or Revert only when exactly one selected Commit is semantically eligible for that operation. Selection never weakens the existing row/domain safety checks.

### Date and time filters

Calendar and clock are not decorative secondary controls.

- Clicking a calendar day applies the actual Activity/Commit date filter for the active view.
- Clicking an hour applies that hour in addition to the selected date.
- Date/Time are reflected in the normal filter toolbar and can be cleared there.
- Without an explicit date filter the table uses the normal bounded Activity window; today's calendar highlight is only visual focus.
- Calendar density retains year context so selecting one day does not collapse the visualization itself.

The top visualization and publication stage is shared context for the active Activity/Commits view. Switching views must not create a second Activity page or a parallel publication surface.

## Commits view

The current Commit-history table exposes:

```text
Commit | Who | When | Summary | Publication | Actions
```

`Details` is the stable first action. A compatible historical snapshot may then expose `Restore`; only the current LIVE commit may expose `Revert` when its parent is a compatible retained snapshot. Those contextual actions follow Details rather than shifting it between rows.

Commit history is permanent factual publication history. Working-state controls live in the shared publication stage above the table rather than as a second independent history system.

## Publication source of truth

The application intentionally has two current database states:

```text
public.*       = working/admin state
committed.*    = current LIVE state
```

`PublicationSnapshot::TABLES` is the closed current tracked-table set. Publication delta is the row-level semantic difference between those two states, ignoring framework-only `created_at`/`updated_at` differences.

`PublicationService::pendingSummary()` is the source of truth for **Pending changes**. Pending audit-event count is separate context and must never be presented as the size of the next commit.

Historical migrations must not import `PublicationSnapshot::TABLES` or other mutable runtime publication constants to define their own past behavior. A migration freezes the publication tables/entity types that existed when that migration was introduced; later changes to the current publication contract are applied by later forward migrations.

`blog_settings` is not legacy-source evidence and is not part of the runtime Publication source of truth. It was an early application-owned Blog-settings table whose canonical values were normalized into `site_sections` / `journal_settings`; a forward migration retires it after the obsolete foreign-key coupling is removed. Historical migrations may still mention it solely because they reconstruct the application's actual schema evolution. It must never return as a Publication fallback or tracked source.

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

Every new Commit still exposes a logically complete row snapshot through the transparent `publication_version_rows` interface, but those rows are not stored as repeated JSONB copies. `publication_version_row_manifests` stores each Commit/table/row identity and references immutable content-addressed payloads in `history_payloads`. Undo snapshot receipts reference the same `history_payloads` store through `snapshot_payload_id`, so identical JSON payloads shared by Undo and Publication are physically stored once.

This project intentionally favors logically full snapshots over reconstructing historical state from Activity deltas. Content-addressed payload sharing is an implementation/storage optimization beneath that deterministic restore contract. Media binaries are **not** duplicated per version; snapshots reference canonical MediaAsset identities/storage keys.

Historical checkpoint metadata that predates this snapshot system remains visible in Commit history. Such legacy checkpoints are explicitly `metadata only`; the UI must not offer Restore when a real compatible snapshot does not exist.

A retained snapshot is restorable only while its stored `schema_hash` matches the current publication schema. Schema-incompatible history remains inspectable rather than being restored through unsafe best-effort coercion.

## Commit permanence

Commit metadata is permanent factual history.

- PostgreSQL rejects deletion of `publication_checkpoints` at the database boundary.
- Reverting/restoring creates later working state and, once explicitly committed, a new Commit. Existing commits remain in the chain.
- The explicit Storage action `Free storage now` may relinquish **restore payloads**, not Commit metadata. Released checkpoints remain visible as history with `snapshot_available=false` and cannot be offered as restorable versions.
- The current LIVE checkpoint and the checkpoint referenced by `publication_working_context.source_publication_checkpoint_id` are protected from that reclaim action.

Undo receipts are intentionally bounded recovery data rather than permanent history: actor-scoped receipts expire after 365 days, retain at most 5,000 receipts per user and are capped at 256 MiB logical payload budget per user. `Free storage now` may clear them immediately. Activity events remain append-only and are never deleted for storage reclamation.

Shared `history_payloads` are garbage-collected only after neither a Publication manifest nor an Undo receipt references them. This lets recovery roots be released without duplicating or prematurely deleting payload data still required elsewhere.

## Working changes

The shared publication stage exposes Working-vs-LIVE state above both Activity and Commits. It shows:

- true pending row count;
- grouped affected publication areas/entities;
- current preflight status;
- current LIVE version context;
- `Review changes` when staged state exists;
- `Reset` for the `Reset staged changes` operation when staged state exists;
- `Commit` when preflight allows publication.

The logical operation name remains **Reset staged changes** even where the compact action label is `Reset`.

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
Activity row -> Undo

All unpublished work
Publication stage -> Reset staged changes

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

`PublicationMediaCleanupService` checks retained logical `publication_version_rows` before physical deletion. This ensures a retained historical version cannot remain formally restorable while its binary assets have already disappeared. When `Free storage now` releases an older snapshot root, cleanup may finally remove media that has no Working/LIVE/current-reference or other retained recovery dependency.

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
- `Reset staged changes` (compact action may read `Reset`)
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
- date/hour filtering works in both Activity and Commits views beyond the default Activity window;
- Activity and Commit pagination retain filter/view context and support the shared 25/50/100 page-size choices;
- Activity aggregate query cost remains bounded;
- historical publication migrations do not depend on mutable runtime snapshot constants.
