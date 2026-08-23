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
| Navigation Node | none | Structural navigation only. |

Protected preview equivalents live below `/preview` and do not make draft/private content publicly discoverable.

Migrated slugs such as `cv`, `blog` or `exhibitions` may remain editorial data, but they are not fixed route types. Contact content does not require a standalone Contact Site Node/route; it is a reusable Custom Page component.

Legacy dispatcher/query URLs are evidence, not a blanket compatibility requirement.

## Publication and discoverability

Only publicly eligible content appears in public rendering, navigation, sitemap and viewer sequences.

- Home is always public.
- Gallery/Journal/Custom Page availability follows Site Node publication state.
- Navigation Nodes may group visible children but have no content page.
- Blog/Exhibition entries belong to the correct public Journal and satisfy their lifecycle.
- draft/private content is available only through authenticated preview/admin boundaries.
- unknown/malformed routes fail safely without debug/database/filesystem disclosure.

## Site navigation

Navigation is generated from the canonical Site Node tree and persisted order/visibility. It is not assembled from hard-coded page arrays or legacy category assumptions.

The public visual composition remains artist-specific rather than adopting a generic CMS theme.

## Home selection

Home selects from published Artworks in Galleries eligible for homepage presentation.

Selection uses canonical Artwork date/feature semantics and must be deterministic. Ambiguous invalid state is surfaced rather than silently resolved by database ID/insertion order.

## Gallery and Artwork ordering

Gallery presentation uses persisted Artwork `position` as the authoritative order for assigned Artworks.

- deliberate reorder persists;
- IDs/timestamps/database order are not runtime fallbacks;
- moving Artwork preserves canonical media relationships;
- an Artwork detached/unassigned from a Gallery is not part of a public Gallery sequence;
- published Artwork cannot remain public through an invalid/missing/unpublished Gallery relationship.

Viewer previous/next follows the same public Gallery order.

## Artwork presentation and media

A public Artwork exposes its authored title and applicable year/date, Material, dimensions, description and other approved metadata.

Canonical originals remain authoritative. Required derivatives resolve from them and never replace them as authority. Missing/corrupt/unavailable required media is a readiness/integrity failure, not permission to choose an unrelated fallback.

Informative public images require canonical ALT data; an explicit usage override takes precedence where supported.

Gallery Artwork primary visual media may be image or supported video. Public video behavior is deliberate:
- manual/native controls;
- no autoplay;
- normal close and previous/next navigation remain available;
- image zoom/pan controls do not apply to video.

Audio being supported by the Files library does not automatically make audio a Gallery primary visual. Optional Artwork-specific audio requires its own explicit consumer contract.

## Artwork viewer

The viewer is a first-class public interaction.

For images it supports:
- visible close + `Escape`;
- visible zoom controls and wheel/trackpad zoom;
- touch pinch zoom/pan where supported;
- pointer/mouse pan while enlarged;
- previous/next within the current public ordered sequence;
- clear first/last boundaries;
- recalculation after viewport/orientation changes;
- explicit loading/error state.

Accessibility:
- named modal/dialog or equivalent accessible state;
- focus enters the viewer and returns to the invoking control on close;
- controls have accessible names/focus treatment;
- background interaction/scroll is controlled while open;
- current Artwork title/ALT remains available;
- direct Artwork content remains usable without enhanced viewer JavaScript.

Video uses the same immersive sequence/close context without image-specific zoom/pan behavior.

## Custom Pages, CV and Contact

Custom Pages are ordered structured components validated by `CustomPageSetting`. Current component types include ordinary content and the reusable Contact component.

CV/Vita is represented through a Custom Page composition rather than a special runtime node type.

Contact is **not** a standalone runtime page type. A Contact component may be composed into CV or another Custom Page according to editorial structure.

The public Contact workflow must:
- validate required input server-side;
- enforce honeypot/rate-limit abuse controls;
- deliver to the configured private recipient;
- use visitor email as Reply-To rather than forging From;
- expose safe success/failure without mail/runtime internals;
- fail in a controlled way when delivery is unavailable.

General owns site-wide public email/visibility, social links, favicon, private Contact recipient and truly global legal/public text. SMTP/DKIM/TLS credentials remain runtime/platform secrets.

## Journals

### Blog

A Blog Journal lists only public Posts belonging to it. Draft/unpublished/archived/not-yet-due entries are not public. Scheduled visibility is derived from persisted timestamps rather than requiring a precise promotion job.

### Exhibitions

An Exhibitions Journal lists public Exhibitions belonging to it. Date/location/opening/rich-text/link fields use explicit stored semantics. Upcoming/current/past context is derived from normalized dates where available.

Blog and Exhibitions remain separate content products even though they share Journal placement/settings mechanics.

## Public media delivery

Only media valid for the requesting public consumer is exposed.

- private/quarantined/deleted originals are not public substitutes;
- required variants must exist where the consumer contract requires them;
- public routes do not expose arbitrary storage paths;
- usage/reference rules remain authoritative when content moves/detaches/deletes;
- being accepted into Files does not imply universal public-consumer support.

See [MEDIA.md](MEDIA.md).

## SEO and metadata

- indexable pages have canonical HTTPS URLs;
- sitemap follows the current public model and excludes admin, preview, drafts and navigation-only nodes;
- `robots.txt` does not advertise private/admin/preview content;
- stable Artwork/content slugs support sharing; deliberate path changes may create redirect records;
- titles, favicon and meaningful ALT metadata remain part of public acceptance.

## Analytics boundary

Public Matomo tracking is optional runtime configuration. Analytics failure must not block rendering, navigation, viewer operation or Contact behavior.

No form contents, visitor email/name, admin identifiers or secret values are emitted as analytics data. See [ANALYTICS.md](ANALYTICS.md).

## Responsive/artistic presentation

The public site preserves Lars Möller's established visual identity: artwork-first presentation, typography, spacing and restrained header/navigation/content treatment.

Reliability/accessibility/responsive improvements are allowed but must not silently replace the artistic composition with generic portfolio/card templates.

Browser comparison on representative desktop/mobile/touch sizes remains authoritative before cutover.

## Deliberately not preserved

The target does not preserve insecure HTTP behavior, legacy query dispatch by default, legacy admin/auth/session/SQL/upload implementation, warnings/debug output, broken legacy handlers, accidental DB ordering, public workshop/development surfaces, unsafe rich text or missing-media fallbacks that hide integrity problems.

## Acceptance

Public acceptance requires durable publication/routing/data tests, media integrity, viewer interaction checks, isolated Validation, representative browser comparison and explicit editorial/artist review before Production cutover.