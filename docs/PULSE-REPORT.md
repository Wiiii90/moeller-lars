# Laravel Pulse report export

`pulse:report` is the copy/paste and machine-readable companion to the protected Laravel Pulse dashboard. It reads the same stored Pulse aggregates that back the dashboard cards; it does not install another monitor, enable another recorder, poll external systems or write telemetry.

The report exists for two use cases:

- a compact Markdown snapshot that can be pasted into an issue or review chat without manually transcribing the Pulse UI;
- a stable JSON snapshot for scripts, CI artifacts or later comparison tooling.

It is deliberately an aggregate report, not a request tracer. Use the browser/Debugbar/Playwright workflow in [`ADMIN-PROFILING.md`](ADMIN-PROFILING.md) when one exact interaction needs root-cause attribution.

## Commands

From an application runtime containing this command:

```sh
php artisan pulse:report --hours=24
php artisan pulse:report --hours=24 --format=json
```

Defaults:

- `--hours=24`;
- `--format=markdown`;
- `--limit=50` rows per aggregate section.

Bounds are intentionally conservative:

- `--hours` accepts `1` through `168`;
- `--limit` accepts `1` through `100`;
- `--format` accepts only `markdown` or `json`.

A requested window can be longer than the configured Pulse retention. In that case the report can only contain data that still exists. The report always includes the configured storage and ingest retention so that this limitation remains visible in copied evidence.

On the managed Validation environment, `server-platform` exposes the same command through its lifecycle helper once the platform release containing that wrapper is active:

```sh
server-platform-moeller-lars-validation pulse-report --hours=24
server-platform-moeller-lars-validation pulse-report --hours=24 --format=json
```

The wrapper executes the application-owned command inside the already-running Validation application container. It does not start Validation, enable Pulse or mutate the database.

## Report schema

JSON reports use `schema_version: 1`. Consumers should check that value before depending on field semantics.

Top-level metadata includes:

- generation timestamp;
- Laravel application environment;
- whether Pulse recording is enabled in this runtime;
- configured Pulse server name;
- requested lookback window and row limit;
- storage and ingest retention;
- recorder enablement, sampling and applicable thresholds.

Telemetry sections are:

- `servers` — the latest `system` values recorded by `pulse:check`, including CPU percentage, memory MiB, configured storage filesystem values and last update time;
- `queues` — queue lifecycle counts grouped by Pulse's `connection:queue` key;
- `slow_requests` — route/method/action aggregates that exceeded the configured slow-request threshold;
- `slow_queries` — SQL pattern/location aggregates that exceeded the slow-query threshold;
- `exceptions` — exception class/location aggregates, count and latest recorded time;
- `slow_jobs` — job aggregates that exceeded the slow-job threshold;
- `slow_outgoing_requests` — outgoing HTTP aggregates that exceeded the configured threshold.

An empty array means Pulse has no retained aggregate rows for that section in the requested window. It does not automatically mean the corresponding activity never happened. For thresholded recorders it means no retained event crossed the threshold. For a disabled recorder it means the recorder was intentionally not collecting that signal; recorder status is therefore emitted alongside the data.

## How counts should be interpreted

Pulse cards and this report are thresholded aggregate telemetry.

For example:

```text
GET /admin/dashboard | count=4 | slowest_ms=2107
```

means four retained `/admin/dashboard` requests exceeded the configured slow-request threshold in the selected window, and the slowest of those four took 2107 ms. It does not mean the route was requested only four times and it is not an average latency.

Likewise:

```text
select * from "sessions" where "id" = ? limit 1 | count=8 | slowest_ms=777
```

means eight executions of that normalized query pattern exceeded the slow-query threshold. Pulse records `QueryExecuted::$sql`, which contains placeholders rather than bound values.

Exception counts identify recurring exception class/location pairs. Pulse does not retain the exception message, SQLSTATE or complete stack trace in this aggregate, so a `pulse:report` entry can establish recurrence but cannot by itself establish the underlying cause.

## Recorder policy in this application

The runtime configuration remains authoritative; the report prints the effective recorder state instead of assuming defaults.

The application defaults currently keep these broad/high-volume recorders disabled:

- cache interaction details;
- per-user request usage;
- per-user job usage.

The relevant operational recorders default to enabled when Pulse itself is enabled:

- exceptions;
- queues;
- slow jobs;
- slow outgoing requests;
- slow queries;
- slow requests;
- server values supplied by `pulse:check`.

See `config/pulse.php` for the exact thresholds and environment overrides.

## Privacy and security boundary

The report adds no data beyond the keys/aggregates already stored by Pulse and intentionally does not query application sessions, users, request bodies or arbitrary domain tables.

In particular it does not add:

- database query bindings;
- HTTP request or response bodies;
- session IDs or cookies;
- authentication tokens;
- Contact-form contents;
- exception messages or complete stack traces;
- full outgoing URLs beyond what the configured Pulse grouping policy already stores.

This application's outgoing-request recorder groups requests by dependency hostname to avoid retaining tokens or private query parameters in Pulse grouping keys.

Treat a report as operational diagnostic evidence nonetheless. Do not paste it into public channels without reviewing the contained route names, SQL patterns and source locations.

## Read-only and load characteristics

`pulse:report` performs bounded reads through Laravel Pulse's public `values`, `aggregate` and `aggregateTypes` APIs. It does not write to the Pulse tables, clear/trim telemetry, dispatch jobs or refresh external data.

The default 50-row section limit and maximum 100-row explicit limit keep an accidental export bounded. Generating a report creates short-lived database read work only while the command is running; there is no background process associated with the exporter.

## Recommended evidence workflow

For normal Validation performance work:

1. keep Pulse enabled only for the intended Validation review window;
2. reproduce the representative workflow a small number of times;
3. inspect `/pulse` visually for trends and server graphs;
4. export a `1h` or `24h` Markdown report for review/issue evidence;
5. use JSON only when structured comparison or tooling is useful;
6. move a suspicious exact interaction to Playwright/DevTools/Debugbar when aggregate Pulse data cannot identify the cause.

Do not interpret historical entries from an older deployed image as evidence against the current image without checking their latest timestamp. Pulse retention intentionally allows old failures to remain visible for a bounded period.

Production Pulse remains an explicit rollout decision. The existence of this exporter does not enable Pulse in Production and does not change the application default `PULSE_ENABLED=false`.
