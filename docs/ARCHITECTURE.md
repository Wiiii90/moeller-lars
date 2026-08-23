# Application architecture

## Scope

`moeller-lars` is a Laravel modular monolith for the public Lars Möller website and the authenticated artist administration. Production/Validation infrastructure is deliberately outside this repository and is owned by [`Wiiii90/server-platform`](https://github.com/Wiiii90/server-platform).

Accepted stack: PHP 8.3+, Laravel 13, Blade public rendering, Filament 5 for `/admin`, PostgreSQL, Vite and Pest. The public site is not a SPA.

## Architectural principles

- one application and one deployable OCI image;
- domain behavior is not defined by Filament, HTTP requests or persistence constants;
- public routing, admin presentation and persistence have explicit boundaries;
- one canonical write path per product concept;
- invalid required state fails clearly instead of silently selecting fallbacks;
- legacy data is migration input, not runtime authority;
- Production topology and secrets remain outside this repository.

## Site structure

The persisted site/navigation tree is represented by `SiteSection`; application behavior is defined by typed domain concepts.

`App\Domain\Content\SiteNodeType` defines five runtime node types:

| Type | Public page | Creatable | Children | Parent |
| --- | --- | --- | --- | --- |
| Home | `/` | no | no | none |
| Gallery | yes | yes | Gallery children | Gallery or Navigation Node |
| Journal | yes | yes | no | Navigation Node |
| Custom Page | yes | yes | no | Navigation Node |
| Navigation Node | no | yes | yes | none |

`Home` is the singleton root. `JournalTemplate` currently supports Blog and Exhibitions.

`SiteSection` stores type/template, title/navigation label, slug, publication/navigation state, position, parent and optional Gallery persistence reference. Runtime decisions use `SiteNodeType` / `JournalTemplate`, not raw historical strings.

`ArtworkCategory` remains the persistence model behind the product concept **Gallery**. A physical model/table rename is not required for clean runtime/admin language.

Contact is not a runtime Site Node type. It is a reusable structured component for Custom Page composition. CV/Vita is likewise represented through Custom Page content rather than a dedicated runtime node type.

## Public routing

`App\Routing\SiteNodeRoute` owns canonical path/URL interpretation.

- Home: `/`
- Gallery, Journal, Custom Page: `/{section-slug}`
- Journal entries: `/{section-slug}/{entry-slug}`
- Artwork detail: `/artworks/{slug}`
- protected preview equivalents: `/preview/...`
- Navigation Nodes have no public URL.

## Admin presentation and navigation

`SiteNodePresentation` maps typed nodes to their canonical admin workspace. `SiteNavigation` owns the admin tree projection and active-state calculation.

Canonical workspaces:

- Dashboard — site/admin overview, not a Pages/Gallery duplicate;
- Pages — actual typed site tree;
- Home — dedicated Home presentation/editorial state;
- Gallery — visual Artwork contact sheet and Gallery-specific workflows;
- Journal — Blog or Exhibitions collection workspace;
- Custom Page — structured component editor;
- Files — reusable MediaAsset library;
- General — site-wide identity/contact/social/legal settings;
- Analytics, Activity and Storage — specialist insight/operations surfaces.

Navigation-only nodes do not get fake editors. Internal Resource/model names must not become the artist-facing IA.

## Admin UI system

The canonical admin theme entrypoint is:

```text
resources/css/admin.css
```

It imports modules under `resources/css/admin/`. Shared tokens/primitives own workspace width/axis, control heights, section rhythm, forms, lists/tables, metrics, actions and empty states. Feature-specific layout classes are allowed only where the task genuinely differs.

Current visual contract:

- one deliberate visible page heading per normal workspace;
- usable full desktop width with shared gutters/axis;
- task-specific presentation rather than generic repeated cards;
- Pages hierarchy remains a tree, Gallery a contact sheet, Files a dense reusable-asset workspace, Analytics an analytical composition, Storage a capacity surface and Activity a history surface.

Dialogs/overlays are a shared system concern. Modal workflows must provide real overlay containment, close/Escape behavior, focus trap/restoration, responsive sizing and correct nested popover/select layering. Page-local faux-dialog workarounds are not architecture.

## Editorial domains

### Gallery and Artwork

`GalleryEditorialService` owns Gallery persistence + matching Site Node. Artwork creation/update/publication, Gallery assignment and media behavior are owned by the Artwork domain services rather than direct Filament writes.

An Artwork may be **unassigned** from a Gallery (`artwork_category_id = null`) when its lifecycle permits. Detaching from a Gallery is not deletion: the Artwork and its MediaAsset usages remain. Published Artwork must leave the public state before a detach that would violate publication invariants.

Gallery ordering is explicit. Moving/reassigning preserves media references. Shared MediaAssets survive detach, move and primary-media replacement unless an independent reference-safe deletion is explicitly performed.

`ArtworkMaterialPreset` is a convenience library for reusable Material suggestions. Removing a preset does not rewrite historical Artwork text.

Structured dimensions support Height × Width with optional Depth and unit, while retaining a safe freeform path for unusual/legacy values.

### Journals

Blog Posts and Exhibitions belong to Journal Site Nodes through their owning relationship. Journal settings own collection title/introduction independently from entries.

Blog and Exhibitions remain separate content models even though placement is shared through Journal.

Deletion/publication transitions are lifecycle-aware and preserve reusable MediaAssets.

### Custom Pages and Contact

A Custom Page owns an ordered structured component list. Supported components include normal content and the reusable Contact component. Publication validation applies safe-link/rich-text/media rules.

The Contact component owns visitor-facing form presentation and canonical Contact submission behavior. It does not own SMTP/runtime secrets or duplicate General site settings.

### General settings

`PublicContentSetting::general()` is the canonical site-wide settings record for favicon/site identity, public email + visibility, private Contact recipient, social links and truly global legal/public text.

Text settings use event-driven persistence: local while typing, persist on normal change/blur only when the normalized value differs from the persisted value. No per-keystroke request and no debounce/timer-based persistence. Toggles/selects/media choices may persist on discrete actual changes.

SMTP credentials, DKIM/TLS secrets and server topology are runtime/platform state, not artist-editable settings.

### Media / Files

`MediaAsset` is the authoritative reusable original. `MediaVariant` contains rebuildable derivatives. Consumers reference MediaAssets; detaching a usage never means deleting the canonical file.

`MediaTypePolicy` owns the explicit allowlist and upload limits. Current canonical ingest supports:

- JPEG, PNG, WebP;
- MP4/H.264 and WebM VP8/VP9/AV1 video under content verification;
- MP3, M4A/AAC, Ogg and WAV audio.

Consumer support remains narrower where appropriate. In particular, Gallery primary Artwork media is visual image/video; audio library support does not automatically make audio a Gallery primary medium.

Destructive MediaAsset deletion is blocked while supported live references exist. See [MEDIA.md](MEDIA.md).

### Analytics

Self-hosted Matomo Community/Core is the canonical human-analytics source. The application owns semantic tracking + Reporting API presentation. Local aggregates are only for operational/error/bot/performance signals.

Validation may read aggregate reporting through a restricted server-side identity while tracking remains disabled. Matomo failure must not break public rendering or ordinary admin editing. See [ANALYTICS.md](ANALYTICS.md).

### Audit and logical Commit

`audit_events` is the durable append-only administrative history. Normal domain persistence happens independently of any future logical Commit/checkpoint feature.

A shell-level Commit may group already-persisted audited changes into an editorial checkpoint; it is not a Save button and not Git integration.

## Security and trust boundaries

- public visitors read only published content and explicitly public endpoints;
- `/admin` requires authenticated/authorized access; mutations are enforced server-side;
- uploads and migrated source files are untrusted input;
- secrets live in runtime/platform configuration, never Git;
- Production and Validation do not share writable application database/media state;
- legacy authentication, SQL helpers, sessions, uploads and credentials are never runtime dependencies.

## Persistence and migration

PostgreSQL is authoritative for editorial state. Canonical uploaded originals are authoritative binary data. Generated variants are rebuildable.

The legacy importer/validator is a controlled migration boundary. Existing protected Validation/Production state evolves through forward Laravel migrations rather than destructive re-import.

Migration-specific guarantees are documented in [MIGRATION-INVARIANTS.md](MIGRATION-INVARIANTS.md).

## Release and platform boundary

This repository owns source/tests, application migrations, configuration templates, Docker/runtime interface, health behavior, immutable GHCR image build and application-level verification.

`server-platform` owns Production/Validation placement, Caddy/ingress, runtime secrets, persistent volumes, resource limits, backups/restores, monitoring, deployment/activation and rollback.

A release is identified by exact Git SHA + immutable OCI digest. CI success does not authorize Production deployment. See [RELEASE.md](RELEASE.md).

## Test philosophy

Tests protect durable product/security/data contracts: authorization, database invariants, typed Site Node behavior, migration reconciliation, publication rules, media lifecycle, Contact delivery, audit immutability and viewer behavior.

Do not freeze temporary CSS class names or visual markup through brittle snapshot-style tests when browser/product acceptance is the real requirement.