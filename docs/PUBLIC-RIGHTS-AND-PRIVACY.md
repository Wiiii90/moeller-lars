# Public rights and privacy implementation contract

## Responsibility and UI

Artist-authored legal and copyright text remains editable on the About/Contact
Custom Page, supported by the existing Legal Disclaimer and General/Media
copyright fields. The artist may choose to change/unpublish it. The developer
owns technical security, crawler/TDM signals and analytics consent. No global
footer or additional menu entry is authorized. Full-resolution Artwork Viewer
media must retain its quality: the presented browser bytes can be downloaded,
and neither CSP nor robot instructions provide technical download protection.

## Implemented rights reservation

- `app/Http/Middleware/SecurityHeaders.php`: `tdm-reservation: 1` for
  ordinary public HTML and media responses (not Admin/Pulse/preview).
- `GET /.well-known/tdmrep.json`: site-wide TDMRep rules, JSON
  `[{"location":"/","tdm-reservation":1}]`. Confirm at cutover the deployed
  ingress forwards this dot-prefixed route to Laravel.
- `PublicSeoController::robots()` keeps public search and sitemap available,
  disallowing `GPTBot`, `Google-Extended`, `ClaudeBot`, and `CCBot` for
  the entire public site. OAI-SearchBot/Googlebot/Claude-SearchBot remain
  permitted. A robots opt-out is voluntary, not universal crawler blocking.
- Rights of photographers and other third-party media owners need verification
  before asserting ownership or granting licenses.

Primary protocol and operator sources:
- https://www.w3.org/community/reports/tdmrep/CG-FINAL-tdmrep-20220216/
- https://developers.openai.com/api/docs/bots
- https://developers.google.com/crawling/docs/crawlers-fetchers/google-common-crawlers
- https://support.anthropic.com/en/articles/8896518-does-anthropic-crawl-data-from-the-web-and-how-can-site-owners-block-the-crawler
- https://commoncrawl.org/faq

## Open: Matomo consent — must be completed before enabling production tracking

Current `resources/js/matomo.js` immediately tracks on startup whenever
`MATOMO_TRACKING_ENABLED` is true (page views, events, heartbeat). There is
no prior consent, refusal or withdrawal UI. Cookie-free JS tracking alone is
not necessarily exempt from consent under German law. Do not claim compliance.
Until complete, public browser tracking must be disabled in deployment.

Requirements: no Matomo load/request/event before opt-in; equal Accept and
Reject, persistent revocable choice, discoverable preference control,
accurate privacy notice (responsible party, Matomo host, processing, retention,
rights), no blocking of artwork viewing and no forced footer. Test first visit,
refusal, opt-in, navigation, reload, revocation, narrow layout and accessibility.
Matomo reporting may remain independently enabled; deployment flags not checked.
See https://developer.matomo.org/guides/tracking-consent .

## Open: legal content

The blanket legacy link-liability disclaimer is not a substitute for an
imprint or privacy statement. The artist must supply accurate responsible
person/service address, rights/credits and approved texts for the About page.
Do not invent legal details or publish placeholder legal copy. Do not move
his editorial content into a global footer without approval.

This implementation has source tests but no local PHP/browser runtime or CI.
