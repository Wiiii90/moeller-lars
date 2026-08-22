# Application architecture

## Scope

`moeller-lars` is a Laravel modular monolith for the public Lars Möller website and the authenticated artist administration. Production infrastructure is deliberately outside this repository and is owned by [`Wiiii90/server-platform`](https://github.com/Wiiii90/server-platform).

The accepted stack remains [ADR-0001](adr/ADR-0001-APPLICATION-STACK.md): PHP 8.3+, Laravel 13, Blade public rendering, Filament 5 for `/admin`, PostgreSQL, Vite and Pest. The public site is not a SPA.

## Architectural principles

- One application and one deployable OCI image.
- Domain behavior is not defined by Filament, HTTP requests or persistence constants.
- Public routing, admin presentation and persistence have explicit boundaries.
- Editorial data has one canonical write path per product concept.
- Fail clearly on invalid required state instead of selecting silent fallbacks.
- Legacy data is migration input, not a runtime authority.
- Production topology and secrets remain outside the application repository.

## Site structure

The editable public site is projected through `SiteSection`, while application behavior is owned by typed domain definitions.

### Domain types

`App\Domain\Content\SiteNodeType` defines exactly five runtime node types:

| Type | Public page | Creatable | Can contain children | Can have parent |
| --- | --- | --- | --- | --- |
| Home | `/` | no | no | no |
| Gallery | yes | yes | yes | yes |
| Journal | yes | yes | no | yes, below Navigation Node |
| Custom Page | yes | yes | no | yes, below Navigation Node |
| Navigation Node | no | yes | yes | no |

`Home` is the singleton root. It has no slug, cannot be deleted, is always published and is always represented in navigation. Forward migrations normalize existing installations back to this invariant when necessary.

`App\Domain\Content\JournalTemplate` defines the supported Journal products:

- `Blog`
- `Exhibitions`

A Journal requires a valid template. Other node types must not carry one.

### Persistence boundary

`App\Models\SiteSection` stores the tree projection: type/template, title, navigation label, slug, publication state, position, navigation visibility, parent and optional Gallery persistence reference.

The model enforces structural invariants, but raw string constants on the model are persistence/migration values only. Runtime behavior should use `SiteNodeType` and `JournalTemplate`.

`ArtworkCategory` remains the intentionally isolated persistence model behind the application concept **Gallery**. Application/service/UI naming uses Gallery; a database/model rename is not required to achieve a clean domain boundary.

Parent-node and Journal deletion use explicit application restrictions: parents with descendants and Journals with owned entries are not silently cascaded away.

### Public routing

`App\Routing\SiteNodeRoute` owns public path, URL and current-route interpretation for site nodes. Domain enums and persistence models do not call `route()` or inspect requests.

Canonical routing is path based:

- Home: `/`
- Gallery, Journal and Custom Page: `/{section-slug}`
- Journal entries: `/{section-slug}/{entry-slug}`
- Artwork detail: `/artworks/{slug}`
- Preview equivalents live below `/preview` and remain protected.
- Navigation Nodes have no public URL.

### Admin presentation and navigation

`App\Filament\Support\SiteNodePresentation` maps typed site nodes to Filament icons and their one canonical admin workspace. It is query-free; required relations must be loaded before presentation.

`App\Filament\Support\SiteNavigation` owns the admin navigation projection and active-state calculation. `AdminPanelProvider` assembles the panel but does not independently reconstruct the site tree or reinterpret node types.

Canonical workspaces include:

- dedicated Home presentation workspace;
- Gallery artwork workspace with native settings/add-artwork actions;
- Blog or Exhibitions Journal workspace with native creation and Journal-settings actions;
- Custom Page editor;
- navigation-only nodes with no fake content editor.

Route-backed fake editor overlays and parallel Gallery/Journal resources are not part of the architecture.

## Admin UI system

There is one admin theme entrypoint:

```text
resources/css/admin.css
```

It imports internal modules under `resources/css/admin/`. Admin views do not load page-local stylesheets.

Shared structural primitives cover workspace/header, sections, toolbars/actions, forms, lists, tables, empty states and metrics. Feature-specific classes such as `media-*` may exist when they express real domain layout, but shared design tokens use the canonical `--admin-*` namespace.

The theme is rendered lazily through the Filament panel using `@vite('resources/css/admin.css')`; application/bootstrap commands must not require an already-built Vite manifest.

## Editorial domains

### Gallery and artwork

`GalleryEditorialService` is the application service for Gallery persistence and its matching Site Node. `ArtworkDraftService`, `ArtworkEditorialService` and `ArtworkGalleryAssignmentService` own artwork creation, editorial transitions, media attachment, ordering, Gallery movement and safe draft deletion.

A Gallery has one matching Gallery Site Node. Artwork ordering is explicit and persisted. Moving artwork must preserve media references and obey publication constraints. Normal Gallery renaming keeps the matching navigation identity synchronized unless the navigation label was explicitly customized.

Only unpublished Artwork drafts are directly deletable through the normal editor. Their usage relations are removed, but reusable `MediaAsset` records remain intact.

### Journals

Blog posts and Exhibitions belong to a Journal Site Node through `site_section_id`. Each Journal has one `JournalSetting` record for listing title/intro.

Blog and Exhibitions remain separate editorial/content models even though they share the Journal placement abstraction.

Blog deletion is lifecycle-aware: published or scheduled posts must first leave that public/scheduled state. Exhibition deletion similarly requires the record not to be published. Deletion preserves reusable MediaAssets.

Exhibition ordering is scoped to the owning Exhibitions Journal rather than globally across all Exhibition records. Separate Journals may therefore legitimately contain equal position values.

### Custom pages and site-wide settings

A Custom Page has one `CustomPageSetting` record containing an ordered list of supported structured components. Published page media must resolve to valid available media and satisfy public ALT requirements.

`PublicContentSetting` owns typed site-wide settings scopes such as General, Contact and Vita-derived content. SMTP credentials and other server secrets are not editorial settings.

### Media

`MediaAsset` is the authoritative reusable original. `MediaVariant` contains rebuildable derivatives. Usage records connect media to artwork, Exhibitions and other consumers without copying the canonical original.

Ingest validates type/size/content, generates controlled storage identities and performs storage-capacity admission. Destructive deletion is blocked while references exist. See [MEDIA.md](MEDIA.md).

### Analytics

Self-hosted Matomo Community/Core is the canonical source for human visitor analytics. The application owns tracking integration and the admin Reporting API dashboard; local aggregates cover operational/error/bot/performance signals only. Matomo failure must not break public rendering or normal admin work. See [ANALYTICS.md](ANALYTICS.md).

## Security and trust boundaries

- Public visitors can read only published content and use explicitly public form endpoints.
- `/admin` requires authenticated/authorized access; every mutation is enforced server-side.
- Uploads and migrated source files are untrusted input.
- Secrets live in runtime/platform configuration, never Git.
- Production and Validation must not share writable application database/media state.
- Legacy authentication, SQL helpers, sessions, uploads and credentials are never runtime dependencies.

## Persistence and migration

PostgreSQL is authoritative for editorial state. Canonical uploaded originals are authoritative binary data. Migrations are forward application changes and are not assumed to be data-reversible.

The legacy importer/validator is a controlled migration boundary. Once reviewed data exists in protected Validation or Production state, schema evolution uses normal forward migrations rather than destructive re-import.

Migration-specific guarantees are documented separately in [MIGRATION-INVARIANTS.md](MIGRATION-INVARIANTS.md).

## Release and platform boundary

The application repository owns:

- source and tests;
- database migrations;
- application configuration templates;
- Dockerfile/runtime interface;
- health/readiness behavior;
- immutable GHCR image build;
- application-level migration/media/release checks.

`server-platform` owns:

- Production and Validation placement;
- Caddy/ingress and host networking;
- runtime secrets;
- persistent volume placement;
- resource limits;
- backups/restores;
- monitoring;
- deployment, activation and rollback.

The application image is identified by an exact Git SHA and OCI digest. A green CI run does not authorize Production deployment. See [RELEASE.md](RELEASE.md).

## Test philosophy

Tests protect durable product/security/data contracts rather than framework behavior or visual implementation details. Important examples are authentication/authorization, database invariants, site-node rules, migration reconciliation, publication rules, media lifecycle, contact delivery and pure artwork-viewer behavior.

The final functional-acceptance suite additionally protects stable Home invariants, Gallery identity persistence, parent/Journal deletion restrictions, root/nested reorder persistence, Artwork draft allocation/move/delete behavior, Blog lifecycle deletion, Journal-scoped Exhibition ordering and reusable-media retention.

CSS class names, source strings and temporary regression mechanics are not permanent test architecture.
