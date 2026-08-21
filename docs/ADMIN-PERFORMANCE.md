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

The remaining cross-page stall was isolated at the Apache boundary. With Apache `KeepAlive On`, `KeepAliveTimeout 5` and four `mpm_prefork` workers behind Caddy, a real Chrome HTTP/2 hard load produced five-second-quantized static-asset queues and Media preview queues reaching roughly 14 s, 19 s and 24 s, with later requests cancelled around 25 s. A Validation-only single-variable A/B changed Apache to `KeepAlive Off`: the five-second quantization disappeared, Pages/Media/Activity/final-Dashboard HTML completed in about 0.216/0.292/0.256/0.295 s, normal static assets stayed subsecond, and the same uncached Media preview burst topped out around 2.27 s instead of 24+ s. The temporary runtime override was removed after measurement.

This establishes the deployment contract: Caddy owns client-facing persistent HTTP/2 connections; the internal Caddy-to-Apache HTTP/1.1 hop must not keep scarce mod_php prefork workers pinned between requests.

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
- Caddy remains the public persistent-connection/HTTP2 endpoint, while Apache `mpm_prefork` runs with `KeepAlive Off` on the internal reverse-proxy hop so idle upstream connections cannot occupy the four-worker mod_php pool.

## Validation measurement protocol

After performance-sensitive admin work is merged into one candidate SHA, measure the protected Validation deployment of that exact SHA/image.

1. Confirm release SHA/image identity and health.
2. Use the representative migrated dataset.
3. Warm the admin once, then record at least five normal transitions for Dashboard, Pages, Artworks, Gallery, Media, Analytics and Storage plus one representative Livewire action.
4. Record server timing separately from browser/client gaps so delayed hydration is not misdiagnosed as backend latency.
5. Measure Analytics once from fresh cache and, separately, once as an intentional live cache miss.
6. Measure Storage from cache and, separately, an explicit refresh/authoritative measurement.
7. Include one hard-load/parallel-asset pass and confirm there is no recurring five-second quantization or cross-page Apache worker starvation.
8. Review query/filesystem telemetry for any row-scaled fanout or unexpected recursive media scan.

## Integration re-check

Dashboard, Analytics, Media and Storage are active parallel work areas. Re-run the full protocol after those workers are merged because consumer changes can alter query composition even when the underlying services remain unchanged.
