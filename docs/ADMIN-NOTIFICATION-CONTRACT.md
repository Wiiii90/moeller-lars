# Admin Notification contract

## Scope

A project Notification **message** has two projections:

- the **Dashboard mailbox** stores a user-scoped `AdminNotification` record;
- the **header Notification surface** delivers the message immediately when its recipient is the admin currently using the browser.

**One `AdminNotifier::notification()` invocation generates one logical Notification.** With several verified Activity presentations from the same successful action, it aggregates their audit IDs into one action message; with a single eligible audit, it uses the canonical Activity Change/Details. Source-reviewed on `dev@fbc93f71`, not runtime-verified.

Activity and Publication remain separate domain systems. The header is never a persistence source and the Dashboard mailbox is never reconstructed from browser state.

## Canonical authority

`App\Domain\Admin\AdminNotifier::notification()` is the single Notification write API.

A Notification call:

1. normalizes bounded plain-text title, body, status and context;
2. resolves the recipient from an explicit user or the authenticated admin;
3. for `status=success`, consumes still-existing Activity presentations: one eligible audit uses its canonical Change/Details and `activity:<id>` source; **multiple** eligible audits use the submitted action title/body, last audit ID as reference/default `source_id` and the complete ordered `metadata.audit_event_ids`; if none exist, the supplied message is used. Each branch calls `recordAndSurface()` only once;
4. for non-success statuses, discards any pending Activity presentation and delivers the explicit message once;
5. each `recordAndSurface()` upserts at most one durable row per `(user_id, source_id)` and surfaces the message only for the matching browser actor (or places it in the bounded session queue). A nonmatching background recipient gets mailbox persistence without an immediate header event.

There is no parallel `inbox()`, `both()`, framework-toast or compatibility API.

Background jobs and other server-side processes use the same method with an explicit recipient and stable `source_id`. Without a matching browser actor, only the durable mailbox projection is produced.

Pre-authentication messages cannot have an admin mailbox recipient. They may use the same immediate session/header transport, but they do not fabricate an `AdminNotification` owner.

**Architekturbelege und offene Runtime-Grenzen:** Der beschreibende [Notification-Transportgraph](architecture/admin-notification-transport.md) verfolgt Audit-Context, `firstOrCreate`, Livewire-/Session-Header, JS-FIFO, benutzerspezifische Dashboard-Reads sowie den aus `pruneFor()` ableitbaren Pin/Retention-Grenzfall. Diese Quellprüfung ersetzt keine ausgeführte Akzeptanz des hier normativen Vertrags.

## Source identity and idempotency

Every durable Notification has a `source_id`. The database uniqueness contract `(user_id, source_id)` is authoritative.

Callers should provide a stable source id for retryable external/domain conditions, for example:

```text
publication-failed:attempt-481
media-processing:asset-72:job-938
storage-capacity:critical:2026-09-14
```

Ordinary one-off admin messages receive an origin-generated Notification source id.

With exactly one eligible Activity, `AdminActivityNotificationContext` supplies the canonical Change/Details and audit ID. With **multiple** eligible Activities, one action-level Notification references the last `audit_event_id` and retains the complete `metadata.audit_event_ids` list; append-only Activity stays the forensic record.

**Idempotency scope:** `AdminNotification::firstOrCreate()` deduplicates only the **mailbox row** for a stable recipient/source pair. The current implementation calls `surfaceNotification()` even when `firstOrCreate()` returns an existing row. Thus a retry with the same source id can still yield an additional header event or pending-session message. This is a **source-observed behavior**, not a claim that duplicate presentation is the intended acceptance contract. A focused runtime test should determine whether header delivery should also be suppressed on a repeated source. Do not claim exactly-once UI transport based on the database uniqueness key alone.

## Admin mutation feedback integration

**Persistence, Activity and Notification are independent**, not an
automatic pipeline. `AdminAuditService::record()` appends Activity and
`AdminActivityNotificationContext` holds a presentation to be consumed if
someone calls `AdminNotifier::notification()`. Neither automatically
sends a Notification after a database write.

Every authenticated **editorial** UI mutation owner must enforce:
`validate → mutate via canonical Domain service → inspect actual changed
result → refresh UI state → AdminNotifier success (if changed)`.

Inbox-only user interactions (mark read/unread, pin/unpin, dismiss or
delete a Notification) are explicitly exempt from creating another
Notification, preventing self-generating messages. Such exceptions must
be documented at the owning user action, not inferred for unrelated edits.
Notification delivery must happen after successful mutation; a no-op,
failed validation or rolled-back operation emits no success message.
For nested/selected actions, put feedback at the single existing
mutation boundary; do not duplicate notifications in outer Actions.
If the operation also generates Activity, the notifier consumes its
canonical Change/Details/audit-event context rather than creating
competing descriptions. Non-admin/background services may need a
different recipient and should not be forced to emit an admin UI event.

**Required proof:** for representative edit/selection/ordering flows
including Pages/Home heading, Home List/Contact child, General, Journal,
Custom Page, Gallery and Storage, exercise the **real** Livewire action/
form-update path. Assert persisted data, Activity and **action-level Notification cardinality**: one for a single-Activity edit, **one aggregate message** for one submitted action with multiple eligible Activities, and full `metadata.audit_event_ids`. Assert the matching mailbox row and header event for the actor; separate user actions remain separate Notification calls. Repeat the same value and
test invalid input: no success Notification and no duplicate audit.
Close/reopen edits to confirm persisted state. A test merely asserting
a Domain service updated a row does not prove Notification delivery.
Source inspection and unexecuted tests are hypotheses, not runtime proof.

## Header shell

The header surface is a persisted SPA-shell element rendered through the panel-level `BODY_END` hook, **after** the page's `.admin-workspace` element in document order. It is deliberately outside Livewire page/topbar components so Livewire `@persist` can keep the same DOM while the one-time `admin-notifications.js` controller keeps queue, timer and remaining-lifetime state across `wire:navigate` visits. This order is required for CSS anchor positioning: Filament's native scroll lock fixes the document body for scrollable pages with an open dialog, and an earlier fixed Notification cannot anchor to a later workspace in that state. Neither the scroll-lock owner nor Notification sizing/animation has an alternate modal-specific path.

Its only ingress is the project-owned `admin-header-notification` event plus the bounded session queue used for real full-document/redirect boundaries.

The runtime owns only presentation state:

- current message;
- bounded FIFO queue;
- absolute `expiresAt`;
- bounded seen ids;
- status;
- remaining visible lifetime.

It does not own persistence. Navigation must not restart the visible lifetime. Queue order must survive normal SPA navigation. The visible Header Notification has one dismiss control after its timer and queued-message count. Dismissing the current message starts the existing leave lifecycle and advances the same bounded FIFO queue. This is presentation-only: it neither marks nor deletes the durable Dashboard mailbox entry. The dismiss button stays on the Header layer above blocking dialog backdrops, without changing Filament's modal open/close or scroll-lock ownership.

Local `setTimeout` callbacks may drive the active presentation/countdown. Do not introduce polling, intervals, requestAnimationFrame loops, observers or periodic Livewire requests for Notification persistence or delivery.

## Dashboard mailbox

`AdminNotification` is the durable user-scoped record and remains a source in `DashboardFeed`.

Mailbox behavior includes:

- read/unread state;
- deletion;
- pinning;
- configured retention;
- filtering/search;
- optional bounded action/entity/audit/publication context.

Deleting or pruning a Notification never deletes Activity or Publication history.

## Activity relationship

Activity is immutable factual history of successful administrative mutations.

For successful mutations, Notification copy should reuse the canonical Activity `Change` and `Details` presentation when available. The Notification may link to the related audit event through `audit_event_id`.

This does not make Notification a second audit log: Activity remains the forensic record, while Notification is the user-facing mailbox message and immediate status projection.

## Publication relationship

Publication state and history remain owned by the Publication domain.

Publication success/failure may create Notifications through the same canonical notifier. A failure can include a stable source id and an action back to Activity/preflight. Notifications never become a rollback or publication-state mechanism.

## User isolation

Every durable Notification belongs to one admin user.

Reads, read/unread changes, deletes, pins, filters and retention must remain constrained by `user_id`. A browser must never receive another user's immediate header projection.

## Transaction semantics

A successful Notification that describes a database mutation must only be created after the mutation is known to have succeeded. No-op or rolled-back mutations must not leave durable success Notifications.

Failure Notifications may be created from a failure path when the failure fact itself is valid to retain.

## Forbidden parallel paths

Do not maintain:

- header-only administrative Notification APIs for authenticated users;
- a separate inbox-only API;
- a `both()` compatibility method;
- Filament framework notification cards for normal project Notifications;
- DOM scraping/observation as a Notification source;
- window mirrors or secondary Notification stores;
- page-local Notification renderers.

## Verification

Coverage should prove at least:

- a simple single-Activity success produces one canonical mailbox message and header event; an action with multiple eligible Activity presentations produces **one aggregated message** and header event with full audit-ID metadata;
- repeated stable `source_id` values deduplicate stored mailbox rows; investigate whether a repeated header event is intended or should be suppressed (not yet runtime verified);
- explicit `source_id` values remain idempotent per recipient;
- background delivery persists without requiring a browser session;
- mailbox reads/mutations remain user-scoped;
- Activity-backed success Notifications use canonical Activity presentation/context;
- the header surface is rendered at `BODY_END`, **after the workspace DOM**, outside Livewire page components, and uses `@persist`;
- the header queue and remaining lifetime survive SPA navigation;
- no parallel notifier API or framework notification renderer remains.
