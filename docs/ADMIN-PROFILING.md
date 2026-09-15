# Admin performance profiling

This runbook is the canonical workflow for diagnosing admin interactions that feel slow. It complements the durable budget in `ADMIN-PERFORMANCE.md`; it is not a replacement for browser/product acceptance.

## Diagnostic layers

Keep these questions separate:

1. **Browser/rendering** — is JavaScript, layout, paint or DOM morphing slow after the server responded?
2. **Request fan-out** — did one click create multiple navigations, Fetch/XHR or Livewire requests?
3. **Laravel request time** — is one individual HTTP request itself slow?
4. **Database work** — are duplicate queries, N+1 behavior or expensive SQL responsible?
5. **External/filesystem work** — is Matomo, geocoding, media/storage I/O or another dependency on the critical path?
6. **PHP execution** — if the request is slow but SQL/external I/O do not explain it, which services/listeners/rendering paths consume the CPU time?
7. **Deployed recurrence** — does the same class of slow request/query/job/outgoing dependency recur on Validation or Production?

Do not add page-local `microtime()` logging or speculative caches as the primary diagnostic method.

## Local Debugbar

Laravel Debugbar is a development-only Composer dependency. The Production image installs Composer dependencies with `--no-dev`, so Debugbar is absent from the normal Production runtime.

It is also opt-in locally. In the local `.env` set:

```dotenv
APP_ENV=local
APP_DEBUG=true
DEBUGBAR_ENABLED=true
```

Then clear configuration if it was cached:

```sh
php artisan config:clear
```

The repository hard-disables Debugbar's force-enable escape hatch. Do not change that to profile a public or Production environment.

For one suspicious request inspect at least:

- total request duration;
- SQL query count and cumulative query time;
- repeated/duplicate SQL and N+1 patterns;
- views/models/cache information where relevant;
- redirect/AJAX/Livewire request entries associated with the deliberate action;
- timeline/event information only when deliberately needed for the investigation.

Debugbar itself adds overhead. Compare structure and relative hotspots first; do not treat a Debugbar-enabled millisecond number as the Production latency baseline.

## Playwright interaction profiling

The repository contains a direct Playwright profiling suite in `tests/browser`. It runs separately from Pest because the performance contract needs browser traces, request classification, transfer/payload evidence and machine-readable per-flow metrics.

Run it only against an explicit disposable profiling environment:

```sh
npm run test:browser:profile
```

CI provides its own PostgreSQL database and synthetic admin. The suite records evidence under `artifacts/performance` and traces failures through Playwright. It does not persist request or response bodies.

The representative flows cover warmed navigation, Pages → Add page, Home → Settings, Activity → Commits and idle behavior. Structural assertions are blocking immediately; wall-clock and payload thresholds become blocking only after stable baselines exist.

## Chrome DevTools interaction capture

Use a warmed local admin candidate and perform exactly one deliberate interaction at a time.

### Network

1. Open DevTools → Network and select Fetch/XHR when investigating a Livewire action.
2. Clear the existing requests.
3. Perform one action: for example open Settings, open Edit, change a table filter or navigate Pages → Home.
4. Record:
   - whether a full document navigation occurred;
   - Fetch/XHR/Livewire request count;
   - request ordering and whether requests were serialized or duplicated;
   - status and duration of each request;
   - transferred/request/response size where available;
   - whether the action needed a second click.
5. Check Console for errors or rejected promises during the same interaction.

Do not infer a server bottleneck merely because the visible interaction took a long time. A fast request followed by a long browser task is a browser-side finding.

### Performance

When Network does not explain the visible delay, record one interaction in DevTools → Performance. Inspect:

- long JavaScript tasks;
- Livewire/DOM morph work;
- style recalculation and layout;
- paint/compositing;
- periods where the main thread is blocked after the HTTP response completed.

### Idle check

After the page is fully settled:

1. clear Network;
2. do nothing for at least 15 seconds;
3. verify that no unexplained application Fetch/XHR/Livewire polling loop appears.

A feature that intentionally polls must own and document that behavior; otherwise repeated idle requests are a regression candidate.

## Request-level classification

Correlate the browser request with Debugbar:

- **many browser requests, each fast** → investigate Livewire/event/request fan-out;
- **one slow request dominated by SQL** → inspect duplicate/N+1/aggregate queries;
- **one slow request with little SQL** → inspect filesystem, external HTTP, listeners/events or PHP execution;
- **server request fast but interaction slow** → investigate browser JavaScript/rendering/payload size;
- **large Livewire request/response** → inspect dehydrated public component state before refactoring it.

For Pages/Home specifically, large public Livewire arrays remain a hypothesis until request/snapshot evidence shows that serialization or payload size is material.

## Targeted PHP callgraph profiling

Use a callgraph only when one PHP request is demonstrably slow and Debugbar/Network do not explain the time through SQL or external I/O. Xdebug must be installed only in the local/profiling runtime, never added to the normal Production image.

Start a local PHP process with the profiler in trigger mode, for example:

```sh
mkdir -p /tmp/moeller-lars-xdebug
php \
  -d xdebug.mode=profile \
  -d xdebug.start_with_request=trigger \
  -d xdebug.output_dir=/tmp/moeller-lars-xdebug \
  artisan serve
```

Trigger only the request being investigated with `XDEBUG_TRIGGER=1` (query parameter or temporary browser cookie), then remove the trigger immediately. Inspect the generated Cachegrind file with a compatible viewer such as QCacheGrind/KCacheGrind.

Look for service/resolver/listener/rendering call stacks that account for meaningful inclusive time. Do not optimize functions merely because they appear in the callgraph; optimize the path that explains the measured slow request.

## Laravel Pulse deployed telemetry

Laravel Pulse is the aggregate deployed-observability layer, not the per-request profiler. It is a runtime dependency because Validation/Production may record telemetry, but recording is **off by default** through `PULSE_ENABLED=false`.

The application-owned Pulse configuration intentionally records only signals relevant to current performance work when enabled:

- exceptions;
- queue throughput and slow jobs, including background Matomo refresh work;
- slow application requests;
- slow SQL queries;
- slow outgoing Laravel HTTP-client requests;
- server CPU/memory/storage data when the platform runs `php artisan pulse:check`.

Broad cache-key and per-user request/job recorders are disabled by default. Pulse storage and ingest retention default to three days. Slow outgoing requests are grouped to the dependency hostname so full URLs, query parameters and API tokens are not stored as grouping keys.

The `/pulse` dashboard is protected by the `viewPulse` authorization gate and is accessible only to authenticated administrator accounts. Do not replace that with environment-only authorization.

The application repository owns Pulse dependency/configuration, schema and authorization. Long-running `pulse:check`, process supervision, runtime environment values and deployment topology remain platform-owned. Enable Pulse first on Validation, inspect overhead/data quality, then decide the Production runtime setting. Do not claim that a Pulse aggregate identifies the root cause of one slow browser action; reproduce that action through Playwright/DevTools/Debugbar when exact attribution is required.

## Evidence record

For a meaningful investigation, keep a compact record containing:

- exact Git SHA and environment;
- warmed interaction name;
- browser request/full-navigation counts;
- relevant transferred bytes/payload sizes;
- server request duration;
- query count, cumulative SQL time and duplicate-query finding;
- console errors;
- identified bottleneck class and the evidence supporting it;
- before/after structural evidence for any fix.

Do not publish credentials, auth/session tokens, Contact-form contents or private visitor data in traces, Pulse grouping keys or screenshots.

## How the profiling layers fit together

- **Local Debugbar + DevTools + optional callgraph** decompose one suspicious interaction/request.
- **Playwright interaction tracing** reproduces representative browser actions in CI and preserves request/trace evidence.
- **Laravel Pulse** shows where slow requests, queries, jobs and outgoing dependencies recur on protected Validation/Production deployments.
- **CI performance budgets** are derived from stable measurements, preferring request counts, duplicate-query absence, payload bounds and idle behavior over fragile runner-specific microbenchmarks.

A green CI run is not runtime-performance acceptance. Runtime telemetry is not proof of a specific browser interaction. Final performance acceptance still requires the representative Validation build to be exercised end to end.
