# Analytics contract

This is the operational and design contract for the privacy-conscious,
self-hosted analytics system. It is application integration guidance against
the authoritative `Wiiii90/server-platform` Matomo contract.

## Ownership and boundary

Matomo On-Premise Community/Core is the source of truth for human visitor
analytics. Matomo Cloud and mandatory premium/commercial plugins are excluded.
The Laravel editorial database must not duplicate raw human visitor, pageview,
or event analytics. `daily_metrics` is limited to lightweight operational
aggregates and optional disposable Matomo dashboard cache.

The platform provides Matomo as a logically isolated service and supplies the
application's site ID and tracking base URL. Physical deployment, database
runtime, private networking, persistence, ingress and secrets are owned by
`server-platform` and are intentionally not prescribed here. A separate
read-only Reporting API identity/token may be used for dashboard integration
and remains outside Git. Matomo failure must never break public rendering,
contact handling, login, or normal admin editing.

## Human collection and event taxonomy

Use first-party browser tracking. Initial reports cover page views/visits,
traffic sources/referrers, country-level geography, device class, artwork and
content popularity, and the following events:

- `artwork_open`
- `artwork_zoom_used`
- `artwork_next`
- `artwork_previous`
- `exhibition_view`
- `exhibition_external_click`
- `blog_view`
- `instagram_click`
- `contact_submit_success`

`artwork_zoom_used` fires at most once per viewer session/open instance, not for
each zoom tick. Events contain no names, emails, admin IDs, message content,
tokens, sensitive query-bearing URLs, or other unnecessary personal data.

Do not initially require city-level geography, fingerprinting, cross-site
tracking, session recording, heatmaps, advertising profiles, or a user-level
visitor browsing UI.

## Referrers, queries, device and geography

Strip or avoid sensitive query strings before analytics storage. Do not send
form values or personal identifiers. Referrer reporting is limited to useful
traffic-source information. Device class/browser/OS aggregation and
country-level geography may use Matomo capabilities; city-level location is
not required. Do not add fingerprinting to improve accuracy. Privacy-sensitive
URL/query handling must be verified during implementation.

## Operational and bot metrics

Do not import full server logs into the human analytics site merely to count
bots. If enabled by server-platform #20, platform ingress/access logs may be
imported into a separate Matomo site ID for log-derived bots, errors, and
request-pattern analytics. `moeller-lars` does not own or import platform
access logs.
Use separate lightweight local aggregation for request counts, status/error
counts (including 404s), response-time/p95-style metrics, admin request health,
upload failures, storage/deployment health, bot-family/request aggregates, and
suspicious request rates. Raw logs remain an operational/security layer.

`daily_metrics` must not contain full IP addresses or raw user-agent strings,
and human analytics must remain visually and conceptually separate from the
bot/error/performance/security panel.

## Privacy and identifiers

The application/admin UI must not expose a clear-IP visitor list. Do not put
names, emails, admin IDs, or other unnecessary identifiers in analytics values.
Raw IPs, if genuinely required for restricted server/security operations, stay
in short-retention infrastructure logs and are not copied into application
analytics tables. Matomo privacy settings must be reviewed before production.

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

## Matomo operations and dashboard

The platform owns Matomo production runtime, dedicated MariaDB, Caddy,
persistence, secrets, archiving, health, resource limits, upgrade lifecycle,
and backup integration. Use a restricted read-only API token for dashboard
integration, stored outside Git and configuration management secrets. Dashboard reads are cached for approximately 5–15 minutes, with useful
ranges of today, 7 days, 30 days, and 12 months. If Matomo is temporarily
unavailable, stale cached analytics may be shown. Matomo, API, and log-parser
failures are isolated and must not affect ordinary application behaviour.

The application owns tracking integration, consent/privacy decisions, event
taxonomy, site ID/base URL configuration, reporting client/dashboard, and
application-level operational aggregates. The future `/admin` dashboard answers artist-useful questions: visits and trend,
most-viewed artworks, viewer interactions, exhibition interest,
traffic/referrer summary, country/device summary, and contact conversion
signal. A separate panel shows operational health and bot/error metrics.

## Cost and hosting

Matomo Community/Core introduces no mandatory commercial software, plugin, or
SaaS dependency. It does introduce real CPU, RAM, storage, backup, and
maintenance requirements on the selected infrastructure. The hosting and
platform decision is recorded in [ADR-0002](adr/ADR-0002-HOSTING-COST-BASELINE.md);
Matomo runtime placement follows server-platform and resource pressure remains
a platform review trigger.

## Official implementation references

- [Matomo privacy configuration](https://matomo.org/faq/general/configure-privacy-settings-in-matomo/)
- [Matomo IP anonymisation](https://matomo.org/faq/general/how-does-ip-address-anonymisation-work-in-matomo/)
- [Matomo auto-archiving with cron](https://matomo.org/faq/on-premise/how-to-set-up-auto-archiving-of-your-reports/)
- [Matomo custom dimensions and tracking](https://matomo.org/faq/reporting-tools/create-track-and-manage-custom-dimensions/)
- [Matomo API token guidance](https://matomo.org/faq/general/faq_114/)
