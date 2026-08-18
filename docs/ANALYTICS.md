# Analytics contract

This is the operational and design contract for the privacy-conscious,
self-hosted analytics system. It is application integration guidance against
the authoritative `Wiiii90/server-platform` Matomo contract.

## Ownership and boundary

Matomo On-Premise Community/Core is the source of truth for human visitor
analytics. Matomo Cloud and mandatory premium/commercial plugins are excluded.
The Laravel editorial database must not duplicate raw human visitor, pageview,
or event analytics. `daily_metrics` is limited to lightweight operational
aggregates and disposable Matomo dashboard cache.

The platform provides Matomo as a logically isolated service and supplies the
application's site ID and tracking base URL. Physical deployment, database
runtime, private networking, persistence, ingress and secrets are owned by
`server-platform`. A separate Matomo user with `view` access to the intended
website supplies the reporting token. The token remains outside Git and is sent
only in the HTTPS POST body of Reporting API requests.

Tracking and reporting are separate runtime capabilities. Production normally
enables both. Validation may enable reporting while keeping tracking disabled,
so browser review can inspect production aggregate reports without recording
validation traffic as production traffic.

Matomo failure must never break public rendering, contact handling, login, or
normal admin editing.

## Human collection and event taxonomy

Use first-party browser tracking. The public tracker records normal page views,
link/download tracking and heartbeat pings for more useful visit-duration
measurement. Artist-specific events add semantic interaction signals:

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

Event category/action/name are used deliberately: category identifies the
public surface, action identifies the interaction, and event name may identify
public editorial content such as an artwork, exhibition or blog title. Form
contents, names, visitor email addresses, admin IDs and other private values
must never be event values.

`artwork_zoom_used` is de-duplicated while a work is being viewed rather than
being emitted for every wheel/pinch increment. Exhibition-view events use a
meaningful viewport threshold rather than firing merely because an exhibition
record exists in the HTML.

## Reporting surface

`/admin/analytics` is an application-owned artist dashboard backed by Matomo's
Reporting API rather than a duplicate analytics database. One authenticated
`API.getBulkRequest` currently collects the required Core reports for the
selected range.

The dashboard includes:

- visits, unique visitors, tracked actions, actions per visit, average visit
  duration and bounce rate with equal previous-period comparison;
- visit/action trend over today, 7 days, 30 days or 12 months;
- acquisition channel mix, referring websites, social networks, search engines,
  campaigns and AI-assistant referrals;
- country and continent aggregates, new/returning context, weekday and local
  visit-hour distributions;
- most-viewed public paths, entry pages, exit pages, downloads, outbound links,
  site-search keywords and searches with no results;
- event actions, categories and public content names plus high-level artist
  interaction counters;
- visit-duration and pages/actions-per-visit distributions;
- device class, browser and operating-system aggregates;
- a visually separate local operational-health section for errors, bots and
  application response performance.

Individual optional Matomo reports may fail or be unavailable without taking
down the dashboard. The visit summary remains the required baseline. Matomo
On-Premise may omit `nb_uniq_visitors` for custom rolling `period=range`
reports unless range-level unique-visitor processing is enabled. That optional,
potentially expensive metric must not make the dashboard unavailable: the
affected KPI is shown as unavailable while the remaining aggregate reports,
charts and geography continue to render. The `Today` preset uses a native
`period=day` summary. Fresh results are cached briefly and a bounded stale
aggregate can be displayed when Matomo is temporarily unavailable.

## Referrers, queries, device and geography

Query strings are removed before Reporting API URL labels are rendered in the
admin dashboard. Public content reports display path only; external
referrer/outbound/download labels are reduced to useful host/path information.
Do not send form values or personal identifiers.

Referrer reporting is limited to useful acquisition information. Device
class/browser/OS aggregation and country/continent geography use Matomo Core
capabilities. City-level location is not required. Do not add fingerprinting to
improve accuracy.

## Operational and bot metrics

Do not import full server logs into the human analytics site merely to count
bots. If enabled by server-platform #20, platform ingress/access logs may be
imported into a separate Matomo site ID for log-derived bots, errors, and
request-pattern analytics. `moeller-lars` does not own or import platform
access logs.

Use separate lightweight local aggregation for request counts, status/error
counts (including 404s), response-time metrics, admin request health, upload
failures, storage/deployment health, bot-family/request aggregates, and
suspicious request rates. Raw logs remain an operational/security layer.

`daily_metrics` must not contain full IP addresses or raw user-agent strings,
and human analytics must remain visually and conceptually separate from the
bot/error/performance/security panel.

## Privacy and identifiers

The application/admin UI must not expose a clear-IP visitor list. Do not put
visitor names, email addresses, admin IDs, contact message contents, tokens, or
other unnecessary identifiers in analytics values. Raw IPs, if genuinely
required for restricted server/security operations, stay in short-retention
infrastructure logs and are not copied into application analytics tables.
Matomo privacy settings must be reviewed before production.

Final cookie/consent behaviour depends on the implemented Matomo configuration
and applicable legal requirements; it must be verified before production, with
no blanket claim that consent is universally required or universally exempt.

## Initial retention targets

These are operational targets, not immutable legal mandates. Prefer shorter
retention where equivalent value remains; automated deletion/archive is
implementation work.

- Matomo detailed/raw visit data: approximately 90 days.
- Longer-lived Matomo aggregates: as needed for useful year-over-year/artistic
  reporting, subject to storage review.
- Local `daily_metrics`: approximately 24 months.
- Normal application/server raw logs: 14–30 days.
- Bot/operational aggregate history: approximately 12 months.
- Application/archive audit/security data: approximately 3–6 months where
  appropriate, except where separate requirements require otherwise.

## Matomo operations

The platform owns Matomo production runtime, dedicated MariaDB, Caddy,
persistence, secrets, archiving, health, resource limits, upgrade lifecycle,
and backup integration. Dashboard reads are cached for approximately 5–15
minutes. Matomo, API, and log-parser failures are isolated and must not affect
ordinary application behaviour.

The application owns tracking integration, privacy decisions, event taxonomy,
site ID/base URL configuration, reporting client/dashboard, and
application-level operational aggregates.

## Cost and hosting

Matomo Community/Core introduces no mandatory commercial software, plugin, or
SaaS dependency. It does introduce real CPU, RAM, storage, backup, and
maintenance requirements on the selected infrastructure. The hosting and
platform decision is recorded in [ADR-0002](adr/ADR-0002-HOSTING-COST-BASELINE.md);
Matomo runtime placement follows server-platform and resource pressure remains
a platform review trigger.

## Official implementation references

- [Matomo Reporting API](https://developer.matomo.org/guides/reporting-api)
- [Matomo JavaScript tracker](https://developer.matomo.org/guides/tracking-javascript-guide)
- [Matomo user permissions](https://developer.matomo.org/guides/permissions)
- [Matomo API token guidance](https://matomo.org/faq/general/faq_114/)
- [Matomo privacy configuration](https://matomo.org/faq/general/configure-privacy-settings-in-matomo/)
- [Matomo IP anonymisation](https://matomo.org/faq/general/how-does-ip-address-anonymisation-work-in-matomo/)
- [Matomo auto-archiving with cron](https://matomo.org/faq/on-premise/how-to-set-up-auto-archiving-of-your-reports/)
