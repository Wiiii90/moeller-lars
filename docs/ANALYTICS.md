# Analytics contract

This document defines the application boundary for privacy-conscious analytics. Matomo runtime/topology is owned by [`Wiiii90/server-platform`](https://github.com/Wiiii90/server-platform); this repository owns tracking integration, reporting behavior and application-local operational aggregates.

## Source of truth

Self-hosted Matomo Community/Core is the canonical source for **human visitor analytics**.

The Laravel/PostgreSQL application does not duplicate raw Matomo visits, pageviews or visitor-event history. Application-local `daily_metrics`/operational aggregates are reserved for lightweight operational/error/bot/performance signals and bounded disposable reporting cache where appropriate.

Matomo Cloud or mandatory premium/commercial plugins are not required by the product.

## Tracking and reporting are separate

Runtime capabilities are configured independently:

- `MATOMO_TRACKING_ENABLED`
- `MATOMO_REPORTING_ENABLED`

Production may enable both.

Validation may deliberately use:

```text
MATOMO_TRACKING_ENABLED=false
MATOMO_REPORTING_ENABLED=true
```

with a restricted read-only Reporting API identity. This allows dashboard review without recording Validation browser traffic as Production human traffic.

Reporting credentials/tokens remain server-side secrets and are never exposed to browser code or committed to Git.

## Failure isolation

Matomo availability must not be required for:

- public page rendering;
- Contact submission correctness;
- admin login/authentication;
- normal editorial work;
- Media/Storage/Page operations.

Reporting uses bounded timeouts and bounded caching. Optional report failures may produce explicit unavailable/stale states without taking down the full admin dashboard.

## Human event taxonomy

The public tracker may record normal page views/link/download behavior plus deliberately named artist-site interactions such as:

- `artwork_open`
- `artwork_zoom_used`
- `artwork_next`
- `artwork_previous`
- `exhibition_view`
- `exhibition_external_click`
- `exhibition_directions_click`
- `blog_view`
- `email_click`
- `instagram_click`
- `contact_submit_success`

Event values may identify already-public editorial content where useful, but must never include form message contents, visitor names/email addresses, admin IDs, credentials/tokens or other unnecessary personal/private values.

High-frequency interactions such as zoom should be de-duplicated/bounded rather than emitted for every wheel/pinch increment.

## Reporting dashboard

`/admin/analytics` is an application-owned artist dashboard backed by Matomo's Reporting API rather than a second analytics database.

Its current artist-facing workspace composition is:

1. heading row with `Analytics` and a right-aligned Reporting status;
2. six shared traffic metrics using the normal admin metric strip;
3. one large Geography/world-map visualization;
4. shared controls in the order `Search | Filter | Analytics range`;
5. the remaining report sections, using shared tables wherever the report output is tabular.

The world map is Analytics-specific visualization; the heading, metrics, controls and table treatment remain part of the shared admin workspace grammar. The six-metric choice is specific to this Reporting workspace and is not a global rule for every admin page.

Supported aggregate reporting includes, where Matomo provides it:

- visits and unique visitors;
- actions/pageviews and actions per visit;
- average visit duration and bounce rate;
- current-range versus previous-range context;
- trends for Today, 7d, 30d and 12m;
- acquisition/referrer/channel information;
- country/continent geography;
- device class, browser and operating-system aggregates;
- landing/exit/content paths;
- downloads/outbound links/site search where available;
- artist interaction events and content attention.

Human analytics and local operational health remain visually/conceptually distinct.

Matomo may not provide every metric for every arbitrary range. In particular, range-level unique visitor counts may be unavailable depending on Matomo processing/configuration. The application shows such a metric as unavailable rather than manufacturing it from incompatible daily values.

## Data minimization

- No clear-IP visitor list in the artist admin.
- Query strings are removed/reduced before public-path labels are displayed where they could contain unnecessary data.
- Do not add fingerprinting to improve device/user accuracy.
- Raw IP/user-agent/security logs, when operationally required, remain in bounded infrastructure logging rather than being copied into editorial analytics tables.
- Contact form data is not analytics payload.

Consent/cookie behavior depends on the actual deployed Matomo/privacy configuration and applicable legal requirements and must be reviewed before Production. This repository does not make a blanket legal claim that consent is always required or always exempt.

## Operational metrics

Local application/platform aggregates may cover:

- request/status/error counts;
- response-time summaries;
- admin request health;
- upload/storage failures;
- bot/suspicious-request families;
- deployment/storage health signals.

They do not become a shadow human visitor analytics database.

Full access-log import, if used for operational/bot analysis, is a platform responsibility and must remain separated from the human Matomo Website/reporting identity.

## Retention principles

Retention values are operational/privacy targets, not immutable legal mandates. Prefer the shortest retention that preserves useful product/operations value.

Typical targets may include:

- detailed Matomo visit data: short/medium retention such as ~90 days;
- longer-lived aggregate trends when useful for year-over-year reporting;
- local operational aggregates: bounded historical retention;
- raw application/ingress logs: short retention appropriate to operations/security.

The authoritative deployed retention configuration belongs to the relevant platform/Matomo configuration, not this prose document.

## Ownership

`moeller-lars` owns:

- browser tracking integration and semantic event taxonomy;
- tracking/reporting feature flags;
- Matomo Reporting API client and artist dashboard;
- safe labeling/data minimization in the application UI;
- application-local operational aggregates;
- failure-isolation behavior.

`server-platform` owns:

- Matomo service/database runtime;
- persistence/backups;
- networking/ingress;
- runtime secrets/reporting token injection;
- resource limits;
- archiving/retention jobs;
- upgrades/health/monitoring;
- platform access/log pipelines.

## Performance

Fresh cached Analytics navigation belongs to the normal admin performance budget. A deliberate live Reporting API cache miss is an external operation and is measured separately.

Prefer bounded/bulk API retrieval over many sequential requests when supported. See [ADMIN-PERFORMANCE.md](ADMIN-PERFORMANCE.md).

## Official references

- [Matomo Reporting API](https://developer.matomo.org/guides/reporting-api)
- [Matomo JavaScript tracker](https://developer.matomo.org/guides/tracking-javascript-guide)
- [Matomo user permissions](https://developer.matomo.org/guides/permissions)
- [Matomo API token guidance](https://matomo.org/faq/general/faq_114/)
- [Matomo privacy configuration](https://matomo.org/faq/general/configure-privacy-settings-in-matomo/)
- [Matomo IP anonymisation](https://matomo.org/faq/general/how-does-ip-address-anonymisation-work-in-matomo/)
