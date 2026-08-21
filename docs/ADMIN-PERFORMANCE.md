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

The remaining cross-page/browser stall is not yet attributed to one proven root cause. One real Chrome HTTP/2 hard load with Apache `KeepAlive On`, `KeepAliveTimeout 5` and four `mpm_prefork` workers behind Caddy showed five-second-quantized request waves, including static assets and a much longer Media preview queue. A temporary Validation-only A/B changed Apache to `KeepAlive Off`; individual Caddy request durations no longer showed the same obvious five-second quantization, but the browser was still observed to be very slow end-to-end. Therefore that experiment does not establish a user-visible speedup and does not prove Apache keep-alive starvation as the root cause.

`KeepAlive Off` remains a source-controlled Validation candidate to test because it directly changes the internal Caddy-to-Apache hop implicated by the queueing pattern. Browser wall-clock behavior is authoritative: if the permanent candidate does not clearly improve the real page load/navigation experience, roll Validation back and reject this candidate rather than merging it.

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
- cache changes must not freeze environment-specific secrets or configuration into the image build;
- Apache keep-alive behavior behind Caddy is currently under Validation investigation. `KeepAlive Off` must not be promoted to the release contract until the source-controlled candidate shows a clear browser-visible improvement.

## Validation measurement protocol

After performance-sensitive admin work is merged into one candidate SHA, measure the protected Validation deployment of that exact SHA/image.

1. Confirm release SHA/image identity and health.
2. Use the representative migrated dataset.
3. Warm the admin once, then record at least five normal transitions for Dashboard, Pages, Artworks, Gallery, Media, Analytics and Storage plus one representative Livewire action.
4. Record browser wall-clock behavior and server/Caddy timing separately. Fast individual request timings are not sufficient evidence when the page remains visibly blocked.
5. Measure Analytics once from fresh cache and, separately, once as an intentional live cache miss.
6. Measure Storage from cache and, separately, an explicit refresh/authoritative measurement.
7. Include one hard-load/parallel-asset pass and compare end-to-end browser completion with the prior slow baseline, not only per-request Caddy durations.
8. Review query/filesystem telemetry for any row-scaled fanout or unexpected recursive media scan.
9. If the candidate does not produce a clear user-visible improvement, roll Validation back to the prior known-good release before continuing diagnosis.

## Integration re-check

Dashboard, Analytics, Media and Storage are active parallel work areas. Re-run the full protocol after those workers are merged because consumer changes can alter query composition even when the underlying services remain unchanged.
