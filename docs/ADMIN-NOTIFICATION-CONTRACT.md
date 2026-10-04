# Admin Notification contract

## Scope

A project Notification is one durable administrative message with two projections:

- the **Dashboard mailbox** stores the user-scoped `AdminNotification` record;
- the **header Notification surface** shows a newly created Notification immediately when its recipient is the admin currently using the browser.

Activity and Publication remain separate domain systems. The header is never a persistence source and the Dashboard mailbox is never reconstructed from browser state.

## Canonical authority

`App\Domain\Admin\AdminNotifier::notification()` is the single Notification write API.

A Notification call:

1. normalizes bounded plain-text title, body, status and context;
2. resolves the recipient from an explicit user or the authenticated admin;
3. creates one idempotent `AdminNotification` row when a recipient exists;
4. projects a newly created row into the current header surface when that recipient is the current browser actor;
5. otherwise leaves the durable row available in the Dashboard mailbox.

There is no parallel `inbox()`, `both()`, framework-toast or compatibility API.

Background jobs and other server-side processes use the same method with an explicit recipient and stable `source_id`. Without a matching browser actor, only the durable mailbox projection is produced.

Pre-authentication messages cannot have an admin mailbox recipient. They may use the same immediate session/header transport, but they do not fabricate an `AdminNotification` owner.

## Source identity and idempotency

Every durable Notification has a `source_id`. The database uniqueness contract `(user_id, source_id)` is authoritative.

Callers should provide a stable source id for retryable external/domain conditions, for example:

```text
publication-failed:attempt-481
media-processing:asset-72:job-938
storage-capacity:critical:2026-09-14
```

Ordinary one-off admin messages receive an origin-generated Notification source id.

When a successful admin mutation produced Activity in the same request, `AdminActivityNotificationContext` supplies the canonical Activity presentation and audit event id. The resulting mailbox Notification uses that audit event as its durable source/context rather than inventing a second success description.

## Header shell

The header surface is a persisted SPA-shell element rendered through the panel-level `BODY_START` hook. It is deliberately outside Livewire page/topbar components so Livewire `@persist` can keep the same DOM while the one-time `admin-notifications.js` controller keeps queue, timer and remaining-lifetime state across `wire:navigate` visits.

Its only ingress is the project-owned `admin-header-notification` event plus the bounded session queue used for real full-document/redirect boundaries.

The runtime owns only presentation state:

- current message;
- bounded FIFO queue;
- absolute `expiresAt`;
- bounded seen ids;
- status;
- remaining visible lifetime.

It does not own persistence. Navigation must not restart the visible lifetime. Queue order must survive normal SPA navigation.

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

- an authenticated admin Notification creates one `AdminNotification` row and one immediate header event;
- explicit `source_id` values remain idempotent per recipient;
- background delivery persists without requiring a browser session;
- mailbox reads/mutations remain user-scoped;
- Activity-backed success Notifications use canonical Activity presentation/context;
- the header surface is rendered at `BODY_START`, outside Livewire page components, and uses `@persist`;
- the header queue and remaining lifetime survive SPA navigation;
- no parallel notifier API or framework notification renderer remains.
