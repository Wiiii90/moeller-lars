# Artist admin performance budget

This document defines the durable performance contract for the authenticated artist admin. It is about request/query/runtime behavior, not visual design or a historical performance investigation.

## Normal warmed transitions

For representative data on Validation, ordinary warmed navigation and actions should remain comfortably interactive.

Target server response time for normal warmed Dashboard, Pages, Artwork/Gallery, Media, cached Analytics, cached Storage and ordinary Livewire actions:

- **target:** <= 500 ms;
- repeated warmed responses >= 1 s require investigation;
- repeated warmed responses >= 2 s are a release-blocking regression unless the action is explicitly documented as an external/authoritative operation.

Browser wall-clock behavior remains authoritative. Fast server timings do not prove good UX if asset/request queueing still makes the page visibly stall.

## Query behavior

- List/tree rendering must not introduce query counts proportional to rendered rows when required data can be eager-loaded or aggregated.
- Relationship labels, media thumbnails, usage counts and placement/order affordances should use eager loading, aggregate queries or a bounded request projection rather than per-row lookups.
- Presentation helpers must not hide database I/O where callers expect a pure projection.
- Repeated expensive aggregates on one render path should be consolidated or cached when their freshness contract permits it.
- Performance tests should protect query/fanout behavior, not fragile wall-clock thresholds.

## Site navigation and Pages

The typed Site Node tree is projected once from the required SiteSection data and eager-loaded presentation relations.

- Sidebar and Pages should not independently rebuild incompatible tree/domain rules.
- SiteNodePresentation remains query-free.
- Ordering capabilities should reuse a bounded projection rather than issue one query per row/action affordance.

## Filesystem and storage accounting

- Normal Dashboard/Storage navigation must not recursively walk the complete media filesystem.
- Display capacity may use a bounded cache/snapshot.
- Explicit refresh and upload admission may perform authoritative measurement.
- Upload admission must never trust a stale display cache when enforcing quota.

## Media

- Media list/grid views must be bounded/paginated.
- Usage counts and thumbnail selection must not create N+1 queries.
- The inspector/original preview should load expensive media only when requested.
- Integrity verification and authoritative capacity scans are explicit operations and are not part of ordinary navigation.

## Analytics

Matomo remains the canonical human-analytics source.

- A fresh cached Analytics render belongs to the normal warmed-transition budget.
- A live Reporting API cache miss is an external-dependency operation and is measured separately.
- Reporting uses bounded timeouts and may show a bounded stale aggregate/explicit unavailable state as defined by the Analytics contract.
- Matomo unavailability must not delay unrelated admin pages.
- Do not replace a bulk-report contract with many sequential external requests without measurement evidence.

## Operational telemetry and audit

Security and audit correctness are not performance switches.

- Authentication/authorization/audit recording remain mandatory.
- Normal request telemetry should keep synchronous database work bounded.
- Additional non-critical telemetry should be aggregated/deferred rather than adding one synchronous write per metric.

## Runtime assumptions

The immutable production image is allowed to optimize for deployed read-only application code:

- OPcache remains enabled in the release runtime;
- Laravel optimization occurs only after runtime configuration/secrets are injected;
- build-time optimization must not freeze environment-specific secret values into the image;
- production/Validation proxy and container resource behavior are platform-owned and must be measured in the deployed topology rather than guessed from local PHP timings.

Exact runtime resource assumptions used by the application image are documented in [RELEASE.md](RELEASE.md).

## Validation measurement protocol

For a performance-sensitive release candidate, measure the exact deployed candidate SHA/image:

1. Confirm runtime release identity and health.
2. Use representative migrated/editorial data.
3. Warm the admin once.
4. Measure at least five normal transitions for Dashboard, Pages, Artworks/Gallery, Media, Analytics and Storage plus a representative Livewire mutation.
5. Record browser wall-clock and server/proxy timing separately.
6. Measure Analytics fresh-cache behavior separately from an intentional live cache miss.
7. Measure Storage cached display separately from explicit authoritative refresh/admission behavior.
8. Include one hard-load/parallel-asset pass.
9. Review query/filesystem telemetry for row-scaled fanout or unexpected recursive media scans.
10. Treat a visibly slow candidate as a regression even when individual backend requests look acceptable.

Performance findings should produce a narrowly evidenced fix. Do not introduce architecture changes based only on speculative bottlenecks.
