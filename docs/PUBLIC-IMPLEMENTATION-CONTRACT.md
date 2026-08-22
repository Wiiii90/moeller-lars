# Public implementation contract

This document defines durable public behavior for the current application. Detailed legacy observations used for cutover comparison live separately in [LEGACY-PUBLIC-CONTRACT.md](LEGACY-PUBLIC-CONTRACT.md).

## Canonical route model

Public routing is driven by persisted typed Site Nodes rather than hard-coded legacy page/category names.

| Surface | Canonical path | Rule |
| --- | --- | --- |
| Home | `/` | Singleton Home presentation. |
| Gallery | `/{section-slug}` | Published Gallery Site Node backed by Gallery persistence. |
| Journal | `/{section-slug}` | Published Blog or Exhibitions Journal. |
| Journal entry | `/{section-slug}/{entry-slug}` | Public entry belonging to the matching Journal. |
| Custom Page | `/{section-slug}` | Published structured Custom Page. |
| Artwork detail | `/artworks/{slug}` | Stable shareable Artwork view. |
| Navigation Node | none | Structural navigation only; never a public content route. |

Protected preview equivalents live below `/preview` and do not make draft/private content publicly discoverable.

Migrated slugs such as `cv`, `contact`, `blog` or `exhibitions` may remain editorial data, but they are not fixed application route types. Creating a supported new Gallery, Journal or Custom Page does not require a route-code change.

Legacy dispatcher/query URLs are evidence, not a blanket compatibility requirement. Redirects are created only for deliberate new-application path changes or a separately evidenced SEO/external-link need.

## Publication and discoverability

Only publicly eligible content appears in public rendering, navigation, sitemap and viewer sequences.

- Home is always public.
- Gallery/Journal/Custom Page availability follows the Site Node publication contract.
- A Navigation Node may group visible child destinations but has no content page itself.
- Blog/Exhibition entries must belong to the correct published Journal and satisfy their own publication lifecycle.
- Draft/private content is available only through the authenticated preview/admin boundary.
- Unknown or malformed routes return a safe not-found response without debug/database/filesystem disclosure.

## Site navigation

Navigation is generated from the canonical Site Node projection and persisted order/visibility.

- Home is represented according to its invariant.
- Gallery, Journal and Custom Page nodes use their persisted labels and destinations.
- Navigation Nodes group child destinations without inventing a public URL.
- Hierarchy and ordering come from persisted Site Node placement, not hard-coded menu arrays.

The public visual composition remains artist-specific rather than adopting a generic CMS theme.

## Home selection

Home selects from published Artworks in Galleries explicitly eligible for homepage presentation.

The target behavior uses canonical Artwork date/feature semantics and must be deterministic. If the newest eligible set is ambiguous under the product rules, the application surfaces a clear invalid/attention state rather than silently choosing by database ID, insertion order or another accidental tie-breaker.

## Gallery and Artwork ordering

Gallery presentation uses persisted Artwork `position` as the authoritative editorial order.

- Positions are explicit and non-negative.
- A deliberate reorder persists; reload must preserve it.
- `work_date`, IDs, timestamps or database order are not runtime ordering fallbacks.
- Moving an Artwork to another Gallery preserves its canonical media relationships and appends/positions it according to the destination's ordering contract.
- Published Artwork cannot remain public through an invalid/unpublished Gallery relationship.

The Artwork viewer's previous/next sequence follows the same canonical public Gallery order.

## Artwork presentation

A public Artwork exposes the authored metadata that exists for it, including title and applicable year/date, medium, dimensions and description.

Public artwork media requirements are explicit:

- the canonical original is retained as authoritative media;
- required derivatives resolve from that original and never replace it as authority;
- missing/corrupt/unavailable required media is an integrity/readiness failure rather than permission to choose an unrelated fallback;
- informative public images require canonical ALT data;
- an explicit usage-specific ALT override takes precedence over the asset-level ALT value when present.

## Artwork viewer

The viewer is a first-class public interaction, not incidental gallery JavaScript.

Required behavior:

- open from an Artwork thumbnail/direct context;
- visible close control and `Escape` support;
- visible zoom controls plus wheel/trackpad zoom;
- touch pinch zoom and touch pan where supported;
- pointer/mouse pan while enlarged;
- previous/next navigation within the current public ordered sequence;
- clear boundary behavior at the first/last Artwork;
- containment recalculation after viewport/orientation changes;
- explicit loading/error state for transient delivery failures.

Accessibility requirements:

- named modal/dialog or equivalent accessible state;
- focus moves into the viewer and returns to the invoking control on close;
- controls have accessible names and visible focus treatment;
- background interaction/scroll is controlled while open;
- the current Artwork title/ALT remain available to assistive technology;
- direct/public Artwork content remains usable without the enhanced JavaScript viewer.

## Custom Pages and Contact

Custom Pages are structured, ordered components validated by `CustomPageSetting`. Current component types include text, list and contact components.

The migrated CV/Vita and Contact placements are normal Custom Pages rather than special runtime Site Node types.

The public Contact workflow must:

- validate required input server-side;
- reject honeypot/rate-limit abuse according to the application contract;
- deliver to the configured private recipient;
- use the visitor address as Reply-To when appropriate rather than forging it as the message From identity;
- expose a safe success/failure result without leaking mail/runtime details;
- fail closed when delivery is unavailable.

General/site-wide public email, social, favicon and legal settings come from canonical typed settings rather than page-specific secret configuration. SMTP credentials are never public/editorial data.

## Journals

### Blog Journal

A Blog Journal lists only public Blog Posts belonging to that Journal. Draft, unpublished, archived or not-yet-due entries are not public.

Scheduled visibility uses persisted schedule semantics. Public availability does not depend on a background job changing the row at a precise moment.

### Exhibitions Journal

An Exhibitions Journal lists public Exhibitions belonging to that Journal. Date/location/opening/rich-text/link fields use their explicit stored semantics. Upcoming/current/past context is derived from normalized dates where available.

Blog and Exhibitions are separate content products even though they share Journal placement/settings mechanics.

## Public media delivery

Only media that is valid for its public consumer is exposed publicly.

- Private/quarantined/deleted originals are not public substitutes.
- Required variants must be available for surfaces that require them.
- Public media routes do not expose arbitrary storage paths.
- Media usage/reference rules remain authoritative when content is moved or deleted.

See [MEDIA.md](MEDIA.md).

## SEO and metadata

- Every indexable page has one canonical HTTPS URL.
- Canonical URLs use current path routes, never legacy query forms.
- Sitemap content is generated from the current public model and excludes admin, preview, drafts and navigation-only nodes.
- `robots.txt` must not advertise private/admin/preview content.
- Stable Artwork/content slugs support sharing; deliberate canonical path changes may create permanent redirect records.
- Page titles, description/author intent, favicon and meaningful ALT metadata remain part of public acceptance.

## Analytics boundary

Public Matomo tracking is optional runtime configuration. Analytics failure must not block rendering, navigation, viewer operation or Contact submission behavior.

No form contents, visitor email/name, admin identifiers or secret values are sent as analytics event data. See [ANALYTICS.md](ANALYTICS.md).

## Responsive/artistic presentation

The public site preserves Lars Möller's established visual identity: artwork-first presentation, typography, spacing, header/navigation composition and restrained content treatment.

Reliability, accessibility and responsive improvements are allowed, but they must not silently replace the artistic composition with generic cards, dashboards or portfolio templates.

Browser comparison on representative desktop/mobile sizes remains the authority for final visual acceptance before cutover.

## What is deliberately not preserved

The target does not preserve:

- insecure HTTP-only behavior;
- legacy PHP/query dispatcher URLs by default;
- legacy admin/auth/session/SQL/upload implementations;
- warnings/debug output for invalid requests;
- broken legacy Contact/links handlers;
- accidental database ordering;
- public workshop/development/admin surfaces;
- raw unsafe rich text or unvalidated external links;
- missing-media fallbacks that hide integrity problems.

## Acceptance

Public acceptance requires more than route tests. The release candidate must pass:

- durable publication/routing/data tests;
- media integrity checks;
- viewer interaction checks;
- isolated Validation deployment;
- representative browser comparison;
- explicit editorial/artist review before Production cutover.
