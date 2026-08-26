# Artist admin performance budget

This document defines the durable performance contract for the authenticated artist admin. It is about request/query/runtime behavior and browser responsiveness, not visual design or a one-off investigation diary.

## Normal warmed transitions

For representative data on Validation, ordinary warmed navigation/actions should remain comfortably interactive.

Target server response time for normal warmed Dashboard, Pages, Artwork/Gallery, Files, cached Analytics, cached Storage and ordinary Livewire actions:

- **target:** <= 500 ms;
- repeated warmed responses >= 1 s require investigation;
- repeated warmed responses >= 2 s are a release-blocking regression unless the action is explicitly documented as an external/authoritative operation.

Browser wall-clock behavior remains authoritative. Fast server timings do not prove good UX if request/asset queueing or repeated Livewire work visibly stalls the page.

## Browser-reported latency is evidence

When the user reports that the first Settings/Edit/selection action takes a long time, treat that as a product/performance finding.

Do not dismiss it as “just Docker”. Local Docker may amplify latency, but the investigation must still inspect the source path for:

- repeated resolver/settings fetches;
- eager MediaAsset option loading/preloading;
- broad content/reference scans;
- N+1 relationship access;
- filesystem walks;
- repeated aggregate queries;
- synchronous telemetry writes;
- external Matomo/geocoding/network calls.

A fix should be source-justified. Do not introduce arbitrary caching before identifying the repeated/expensive work.

## Query behavior

- list/tree rendering must not introduce row-scaled query counts where data can be eager-loaded/aggregated;
- relationship labels, thumbnails, usage counts and ordering affordances should use bounded projections;
- presentation helpers should not hide DB I/O where callers expect pure projection;
- repeated expensive aggregates on one render path should be consolidated or cached when freshness permits;
- performance tests protect fanout/query invariants rather than fragile wall-clock numbers.

## Home

Opening a small settings dialog should not rebuild or requery the complete Home workspace unnecessarily.

The current Home workspace hydrates the settings identity/template/scalar configuration during workspace reload so opening Settings can fill from existing Livewire state rather than performing another settings query merely to open the modal.

Media selection stays lazy through central `MediaAssetSelect`; do not replace this with a full-library preload.

## Site navigation and Pages

The typed Site Node tree is projected once from required SiteSection data/eager presentation relations.

- Sidebar and Pages should not independently rebuild incompatible tree rules;
- `SiteNodePresentation` remains query-free;
- ordering capabilities should reuse bounded projection rather than issue one query per row/action affordance.

## Filesystem and storage accounting

- normal Dashboard/Storage navigation must not recursively walk the entire media filesystem;
- display capacity may use bounded cache/snapshot;
- explicit refresh and upload admission may perform authoritative measurement;
- upload admission never trusts stale display cache for quota enforcement.

## Media / Files

- list/grid views are bounded/paginated;
- thumbnail/usage/reference rendering must not create N+1 fanout;
- inspector/original preview loads expensive media on demand;
- integrity verification/capacity scans are explicit operations, not ordinary navigation.

### Current known watchpoints

These are source-level areas that warrant measurement when browser review reports slowness; they are not blanket instructions to refactor them without evidence.

1. **MediaReferenceCatalog content-reference index** — reference display can build a request-local global index by scanning Blog/Exhibition/Custom/CV/Home Rich Text/direct content. It is cached per request, but broad scanning may still make normal Files loads expensive.
2. **Gallery primary media option preload** — any editor path that preloads/plucks hundreds of MediaAssets instead of using lazy `MediaAssetSelect` is a real candidate for first-open latency.
3. **Operational metrics middleware** — synchronous daily operational-metric UPSERT work on normal requests/Livewire actions is real request tax; observability must not simply be deleted, but non-critical telemetry should remain bounded/deferred where safe.

Measure before changing architecture; do not normalize multi-second warmed transitions as expected.

## Analytics

Matomo remains canonical human analytics.

- fresh cached Analytics belongs to normal warmed budget;
- a live Reporting API cache miss is an external-dependency operation and measured separately;
- reporting has bounded timeouts/stale-unavailable behavior;
- Matomo failure must not delay unrelated admin pages;
- prefer bounded/bulk retrieval over many sequential external requests.

## Geocoding

Exhibition geocoding is only required when the explicit Map feature needs valid coordinates. Ordinary address editing with Map disabled must not make a geocoding network operation part of every save/open path.

## Operational telemetry and audit

Security/audit correctness are not performance switches.

- auth/authorization/audit remain mandatory;
- normal request telemetry keeps synchronous DB work bounded;
- non-critical telemetry should be aggregated/deferred rather than adding avoidable synchronous writes to every interaction.

## Runtime assumptions

The immutable Production image may optimize deployed read-only code:

- OPcache remains enabled;
- Laravel optimization occurs after runtime configuration/secrets are injected;
- build-time optimization must not freeze environment-specific secrets;
- Production/Validation resource/proxy behavior is measured in deployed topology rather than guessed from local timings.

## Validation measurement protocol

For a performance-sensitive exact candidate:

1. confirm release identity/health;
2. use representative editorial data;
3. warm the admin once;
4. measure repeated Dashboard, Pages, Gallery/Artwork, Files, Analytics, Storage and representative Livewire actions;
5. record browser wall-clock and server/proxy timing separately;
6. separate cached Analytics from intentional live API miss;
7. separate cached Storage from authoritative refresh/admission;
8. include one hard-load/parallel-asset pass;
9. inspect query/filesystem telemetry for fanout/scans;
10. treat visibly slow behavior as a regression even when one backend request looks acceptable.

During local browser-polish cycles, do not launch a performance test suite simply because one exists. First inspect/measure the exact slow interaction and make the narrowest evidenced correction.
