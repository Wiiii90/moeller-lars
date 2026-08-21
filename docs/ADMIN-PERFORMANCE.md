# Artist admin performance budget

This document defines the performance contract for the protected artist admin. It is intentionally about server/query/runtime behavior, not visual design.

## Current evidence baseline

Validation investigation on 2026-08-21 established the following baseline before this budget was written:

- warmed protected admin requests were typically about 125–215 ms server-side;
- the former Dashboard regression was a client-side lazy-hydration gap, not a slow Dashboard query path, and was removed by rendering the Dashboard widget eagerly;
- an uncached live Matomo 30-day report completed in about 0.399 s and a subsequent fresh-cache read in about 0.002 s;
- normal Storage rendering uses a cached capacity snapshot, while upload admission performs an authoritative fresh measurement;
- normal operational request telemetry is aggregated into at most one synchronous database upsert per request;
- the production image enables OPcache and runs `php artisan optimize` after runtime environment injection.

A separate 4–5 second first navigation after idle has been observed even though subsequent requests are fast. Treat that as a cold/host-path symptom until evidence attributes it to application work. Host memory, swap and major-fault behavior must be captured alongside that first request rather than hidden by application caching.

## Budget

### Warm normal admin transitions

For Dashboard, Pages, Artworks, Gallery, Media, cached Analytics, cached Storage and ordinary Livewire actions on the representative Validation dataset:

- target server response time: **<= 500 ms**;
- repeated warmed responses **>= 1 s** require investigation before release acceptance;
- repeated warmed responses **>= 2 s** are a release-blocking regression.

The budget is for warmed normal navigation. A deliberate external refresh or authoritative storage measurement is measured separately and must not be inserted into unrelated navigation paths.

### Query behavior

- List/tree rendering must not introduce query counts proportional to the number of rendered rows when the required data can be eager-loaded or aggregated.
- Relationship labels, thumbnails, usage counts and ordering affordances must use eager loading, aggregate queries or request-scoped summaries rather than per-row lookups.
- Repeated expensive aggregate queries on one render path should be consolidated or cached when their freshness contract allows it.
- Performance regression tests should assert behavior or bounded query fanout, not fragile wallclock thresholds.

### Filesystem and storage accounting

- Normal Dashboard and Storage navigation must not recursively walk the complete media filesystem.
- Display capacity may use a bounded cache and explicit refresh.
- Upload admission remains authoritative and fresh. Performance work must never make quota enforcement depend on a stale display cache.

### Analytics

- Matomo remains the canonical human-analytics source.
- A cached analytics navigation is part of the normal warmed-transition budget.
- A live cache miss is an external-dependency operation and is measured separately. It must use bounded timeouts and stale-cache fallback rather than making unrelated admin pages depend on Matomo availability.
- Do not split the existing bulk-report contract into sequential HTTP calls without measurement evidence.

### Operational telemetry and audit

- Normal request telemetry may perform at most one synchronous aggregate database write per request.
- Audit recording, authentication and authorization are not optional performance switches.
- If additional telemetry is introduced, batch or defer non-critical work rather than adding synchronous per-metric writes.

### Runtime

Production releases assume immutable application code inside the container:

- OPcache stays enabled;
- timestamp validation may stay disabled because a deployment replaces/restarts the release container;
- Laravel config/route/view/event optimization must happen only after runtime environment configuration is injected;
- cache changes must not freeze environment-specific secrets or configuration into the image build.

## Validation measurement protocol

After performance-sensitive admin work is merged into one candidate SHA, measure the protected Validation deployment of that exact SHA/image.

1. Confirm release SHA/image identity and health.
2. Use the representative migrated dataset.
3. Warm the admin once, then record at least five normal transitions for Dashboard, Pages, Artworks, Gallery, Media, Analytics and Storage plus one representative Livewire action.
4. Record server timing separately from browser/client gaps so delayed hydration is not misdiagnosed as backend latency.
5. Measure Analytics once from fresh cache and, separately, once as an intentional live cache miss.
6. Measure Storage from cache and, separately, an explicit refresh/authoritative measurement.
7. Reproduce one first request after idle while simultaneously capturing host memory, swap activity and major faults.
8. Review query/filesystem telemetry for any row-scaled fanout or unexpected recursive media scan.

## Integration re-check

Dashboard, Analytics, Media and Storage are active parallel work areas. Re-run the full protocol after those workers are merged because consumer changes can alter query composition even when the underlying services remain unchanged.
