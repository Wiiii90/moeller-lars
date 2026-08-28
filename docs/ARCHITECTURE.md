# Application architecture

## Scope

`moeller-lars` is a Laravel modular monolith for the public Lars Möller website and the authenticated artist administration. Production/Validation infrastructure is deliberately outside this repository and is owned by [`Wiiii90/server-platform`](https://github.com/Wiiii90/server-platform).

Accepted stack: PHP 8.3+, Laravel 13, Blade public rendering, Filament 5 for `/admin`, PostgreSQL, Vite and Pest. The public site is not a SPA.

## Architectural principles

- one application and one deployable OCI image;
- domain behavior is not defined by Filament, HTTP requests or persistence constants;
- public routing, admin presentation and persistence have explicit boundaries;
- one canonical write path per product concept;
- one canonical Rich Text/media-reference technology across editor surfaces;
- referenced media and publicly deliverable media are separate questions;
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

`Home` is the singleton root. `JournalTemplate` supports Blog and Exhibitions.

`SiteSection` stores type/template, title/navigation label, slug, publication/navigation state, position, parent and optional Gallery persistence reference. Runtime decisions use `SiteNodeType` / `JournalTemplate`, not raw historical strings.

Journal template switching is non-destructive: changing a Journal from Blog to Exhibitions or back changes the active presentation/editorial projection but does not convert or delete the inactive template's retained entry rows.

`ArtworkCategory` remains the persistence model behind the product concept **Gallery**. Contact is a reusable Custom Page component. CV/Vita content is composed through the current Custom Page/CV content model rather than a runtime Site Node type.

## Public routing

`App\Routing\SiteNodeRoute` owns canonical path/URL interpretation.

- Home: `/`
- Gallery, Journal, Custom Page: `/{section-slug}`
- Journal entries: `/{section-slug}/{entry-slug}`
- Artwork detail: `/artworks/{slug}`
- protected preview equivalents: `/preview/...`
- Navigation Nodes have no public URL.

Protected preview media routes live below `/preview/media/...`; they permit authenticated preview of otherwise non-public MediaAssets without changing normal public-media policy.

## Admin presentation and navigation

`SiteNodePresentation` maps typed nodes to their canonical admin workspace. `SiteNavigation` owns the admin tree projection and active-state calculation.

Canonical workspaces:

- Dashboard — site/admin overview;
- Pages — typed site tree;
- Home — Home presentation/editorial state;
- Gallery — visual Artwork workspace;
- Journal — Blog or Exhibitions collection workspace;
- Custom Page — structured component editor including CV/Contact composition;
- Files — canonical reusable MediaAsset library;
- General — site identity/contact/social/legal settings;
- Analytics, Activity and Storage — specialist insight/operations surfaces.

Navigation-only nodes do not get fake editors. Persistence Resource/model names must not become artist-facing IA.

## Admin UI system

The canonical theme entrypoint is `resources/css/admin.css`, importing feature/shared modules from `resources/css/admin/`.

The detailed admin-only UI grammar is maintained in [`../ui-skills.md`](../ui-skills.md). Shared presentation ownership is explicit:

- `x-admin.workspace` owns the normal workspace shell and heading row, including an optional right-aligned status;
- `x-admin.metrics` / `x-admin.metric` own factual metric composition and the metric strip's complete border/separator box;
- `x-admin.controls` owns the shared Search/filter/reset/context-action/Selection row;
- `x-admin.table` owns ordinary flat table presentation;
- the existing `admin-hierarchy` primitive extends that table grammar for Pages and Custom Page hierarchy presentation;
- feature CSS owns only genuinely task-specific visualization/layout and must not duplicate the shared workspace, metrics, controls or ordinary table grammar.

Durable shared principles include:

- one visible page heading per normal workspace, with no decorative kicker/eyebrow layer and no heading-owned separator;
- factual metrics only where meaningful rather than a fixed metric count on every page;
- compact controls in the shared semantic order defined by `ui-skills.md`;
- flat stable table geometry rather than card walls, with hierarchy as an extension rather than a separate table system;
- native Livewire ordering rather than custom HTML5 DnD;
- consistent dialogs/overlays as a shared primitive;
- task-specific visualization surfaces while reusing shared geometry/tokens around them.

A technically running browser candidate is not UI acceptance. Current browser feedback is authoritative for iterative admin polish.

## Rich Text architecture

All current rich editorial surfaces share one stack:

```text
AdminRichText / Filament MarkdownEditor
  -> Markdown
  -> RichTextMediaReference
  -> SafeRichTextRenderer
  -> public HTML
```

Canonical embedded Media Files images use:

```markdown
![](media:<id>)
```

`RichTextMediaReference` owns parsing/formatting. `SafeRichTextRenderer` owns safe public rendering. `CanonicalMediaImageRenderer` resolves canonical media images. Arbitrary external image URLs are not a second supported embedded-media system.

This stack is used by Blog body, Exhibition description, Custom Page Text/List rich text, CV body and Home rich text. The legacy Journal TipTap/RichEditor/custom-block runtime is not part of the architecture.

## Media architecture

`MediaAsset` is the canonical reusable original; `MediaVariant` is a rebuildable derivative.

Structured consumers include Artwork media, Journal Cover/Gallery, CV direct images, Custom Image components, Home Image components and site identity. Rich Text references use `media:<id>`.

Two central questions are intentionally separate:

- `MediaReferenceQuery`: is the asset referenced anywhere in canonical editorial data?
- `PublicMedia`: is the asset actually eligible for ordinary public delivery now?

Protected preview changes URLs/context, not asset identity or `isPublicAsset()` semantics.

Journal structured Cover/Gallery runtime ALT is canonical `MediaAsset.alt_text`; legacy Journal usage override values may remain as migration evidence but are ignored by the runtime and rewritten rows store no override.

For published Exhibitions, Cover is public normally; Gallery media is public only when the Exhibition's explicit `gallery_enabled` presentation feature is enabled. Disabled Gallery rows remain stored and referenced.

Media-reference destination resolution for one Journal includes retained Blog and Exhibition entry worlds for that SiteSection even when only one template is currently active.

See [MEDIA.md](MEDIA.md).

## Home presentation

`HomePresentationSetting` owns the singleton Home presentation state. Supported presentation modes are:

- Artwork;
- Under Construction;
- Skip Home;
- Custom.

Artwork mode resolves deterministic candidates from eligible public Gallery/Artwork data with explicit tie-break state when needed. Under Construction and Custom use ordered structured components. Heading and Rich Text share canonical storage type `text`; editor-only subtype state controls which editing UI is shown without creating another persisted component type.

Home direct/Rich Text media participate in the same canonical MediaAsset/reference/publication systems.

## Journals

Blog Posts and Exhibitions remain separate content models owned by a Journal SiteSection.

Journal settings own collection title/introduction and active template. Switching template does not transform/delete the inactive content model.

### Exhibition presentation

Exhibition editorial state includes structured venue/address fields plus optional presentation features:

- `gallery_enabled`;
- `map_enabled`;
- `map_shape` (`wide` or `square`).

`location_text` is the street-address field; city and country are separate structured fields. Geocoding is an implementation detail of an enabled Map feature, not a standalone editorial “Find location” task.

`ExhibitionMapPresentation` owns the canonical map presentation source contract used by editor/public rendering.

Archived Exhibitions remember their prior state where available. Historical archived rows introduced before that field existed are forward-reconciled deterministically from publication evidence and restore safely through current readiness rules.

## Ordering

Ordered editorial records persist explicit position. Native Livewire sorting (`wire:sort`, `wire:sort:item`, `wire:sort:handle`) is the admin interaction mechanism; domain ordering services remain persistence authority.

Filtered/search projections do not silently become canonical reorder sequences. Ranked tables may expose a 1-based Position column as described in `ui-skills.md`.

## Custom Pages and Contact

A Custom Page owns an ordered structured component list. Supported components include normal content, List, CV List and reusable Contact composition.

Parent and child mutations use canonical workspace/domain state; UI hierarchy does not imply a second persistence model. Contact child types remain structured and bounded rather than free-form duplicated components.

## General settings

`PublicContentSetting::general()` is the canonical site-wide settings record for favicon/site identity, public email + visibility, private Contact recipient, social links and truly global legal/public text.

Text settings use event-driven persistence: local while typing, persist on normal change/blur only when normalized value differs. Toggles/selects/media choices may persist on discrete changes. SMTP/DKIM/TLS secrets remain platform/runtime state.

## Analytics

Self-hosted Matomo Community/Core is the canonical human-analytics source. The application owns semantic tracking and Reporting API presentation. Local aggregates cover operational/error/bot/performance signals only. Matomo failure must not break public rendering or ordinary admin work.

See [ANALYTICS.md](ANALYTICS.md).

## Audit and logical Commit

`audit_events` is append-only administrative history. Normal domain persistence happens independently of any future logical Commit/checkpoint feature. A shell-level Commit may group already-persisted audited changes; it is not Save and not Git integration.

## Security and trust boundaries

- public visitors read only published content/explicitly public media;
- `/admin` requires authenticated/authorized access;
- protected preview is authenticated and does not publish draft media/content;
- uploads and migrated source files are untrusted input;
- secrets live in runtime/platform configuration, never Git;
- Production and Validation do not share writable application database/media state;
- legacy authentication/SQL/session/upload implementation is never a runtime dependency.

## Persistence and migration

PostgreSQL is authoritative for editorial state. Canonical uploaded originals are authoritative binary data. Generated variants are rebuildable.

The legacy importer/validator is a controlled migration boundary. Existing protected canonical state evolves through forward Laravel migrations rather than destructive re-import.

See [MIGRATION-INVARIANTS.md](MIGRATION-INVARIANTS.md).

## Release and platform boundary

This repository owns source/tests, migrations, configuration templates, Docker/runtime interface, health behavior, immutable GHCR image build and application-level verification.

`server-platform` owns Production/Validation placement, ingress, runtime secrets, persistent volumes, resource limits, backups/restores, monitoring, activation and rollback.

A release is identified by exact Git SHA + immutable OCI digest. CI success or a local browser candidate does not authorize Production deployment. See [RELEASE.md](RELEASE.md).

## Test philosophy

Tests protect durable security/data/domain contracts. Browser/product acceptance protects visual interaction quality.

Do not freeze temporary CSS class names or current markup through brittle snapshots when browser acceptance is the real requirement. During reconciliation, use the narrowest evidence appropriate to the changed behavior; the final `main` PR receives the canonical full verification gate.
