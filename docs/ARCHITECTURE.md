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

Public content-heading presentation is owned by `SitePageHeading` and the
shared `x-public.page-heading` component across Home, Gallery, Journal and
Custom Page. It is stored on the canonical `SiteSection`, edited through
the shared Filament `PageHeadingFields`, and written by
`SiteSectionEditorialService`. Content headings are independent of
navigation/title identity and off by default; Journal index titles are no
longer hard-coded in separate public templates.


The persisted site/navigation tree is represented by `SiteSection`; application behavior is defined by typed domain concepts.

`App\Domain\Content\SiteSectionType` defines five runtime node types:

| Type | Public page | Creatable | Children | Parent |
| --- | --- | --- | --- | --- |
| Home | `/` | no | no | none |
| Gallery | yes | yes | Gallery children | Gallery or Navigation Node |
| Journal | yes | yes | no | Navigation Node |
| Custom Page | yes | yes | no | Navigation Node |
| Navigation Node | no | yes | yes | none |

`Home` is the singleton root. `JournalTemplate` supports Blog and Exhibitions.

`SiteSection` stores type/template, title/navigation label, slug, publication state, position, parent and optional Gallery persistence reference. Runtime decisions use `SiteSectionType` / `JournalTemplate`, not raw historical strings.

Journal template switching is non-destructive: changing a Journal from Blog to Exhibitions or back changes the active presentation/editorial projection but does not convert or delete the inactive template's retained entry rows.

`ArtworkCategory` remains the persistence model behind the product concept **Gallery**. Contact is a reusable Custom Page component. CV/Vita content is composed through ordinary Custom Page structured Text/List components rather than a dedicated CV persistence or Site Node type.

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

**Immediate navigation refresh:** Filament 5's Livewire `Sidebar` already listens to `refresh-sidebar`. The Pages editor calls `loadSections(navigationChanged: true)` only after a persisted navigation-affecting mutation, dispatching that one built-in event. The Sidebar re-renders the **editorial** `SiteNavigation` tree using committed SiteSection order and hierarchy, including new/unpublished nodes so that Add page immediately creates a Sidebar entry. This must not implicitly publish drafts. The separate `PublicNavigationService` includes every published node in the public menu; a published child whose editorial parent is unpublished appears as a top-level public menu entry rather than being silently hidden. An unpublished node is excluded from public navigation and its public route. Artist preview retains its deliberate draft exception. The retired `site_sections.show_in_navigation` column is not used by application logic and remains physically present only to preserve schema hashes and older Publication restore snapshots. Published sibling positions remain managed by the canonical SiteSection editorial/ordering services; their public-navigation query indexes are intentionally nonunique so older restorable Publication snapshots with formerly hidden position collisions remain valid. The retired column is neither read nor written by current application paths. Both projections omit Home while the single canonical Home routing configuration has Skip Home enabled. Do not add a JS DOM synchronizer, polling, forced reload, duplicate menu tree or mutation events for mere filter/pagination/no-op requests.


Canonical workspaces:

- Dashboard — site/admin overview;
- Pages — typed site tree;
- Home — Home presentation/editorial state;
- Gallery — visual Artwork workspace;
- Journal — Blog or Exhibitions collection workspace;
- Custom Page — structured component editor including CV/Contact composition;
- Storage — canonical reusable MediaAsset library plus whole-site capacity/recovery workspace;
- General — site identity/contact/social/legal settings;
- Analytics and Activity — specialist insight/history surfaces.

Navigation-only nodes do not get fake editors. Persistence Resource/model names must not become artist-facing IA.

## Admin UI system

The canonical theme entrypoint is `resources/css/admin.css`, importing feature/shared modules from `resources/css/admin/`.

The detailed admin-only UI grammar is maintained in [`../ui-skills.md`](ADMIN-UI.md). Durable shared principles include:

- one visible page heading per normal workspace;
- no decorative kicker/eyebrow layer above that heading;
- shared `x-admin.metrics`/`x-admin.metric` technology for factual metrics;
- compact action/filter rows with labels above controls;
- one visible Selection control per task surface;
- flat stable table/grid geometry rather than card walls;
- native Livewire ordering rather than custom HTML5 DnD;
- consistent dialogs/overlays as a shared primitive;
- task-specific content surfaces while reusing shared geometry/tokens.

A technically running browser candidate is not UI acceptance. Current browser feedback is authoritative for iterative admin polish.

## Rich Text architecture

One visual editor and public renderer serve Journal, Home, Custom Pages and the artist's CV content:

```text
AdminRichText / Filament RichEditor + RichTextEditorPlugin
  -> canonical existing Storage MediaAsset IDs (MediaAssetSelect)
  -> RichTextDocument (legacy Markdown to visual editor on edit)
  -> ArtistImageLayout + ArtistTextSpacing + validated text color
  -> SafeRichTextRenderer (shared server-side validation/sanitization)
  -> public HTML (optionally decorated by PublicImageViewer)
```

The editor has one owning Edit-dialog change/blur boundary, with inline image/link/color/spacing popovers and Tiptap tables. The original MediaAsset and alternate text remain canonical; no separate direct RichEditor upload store, autosave timer, runtime Markdown-only renderer or wholesale historical backfill. `PublicImageViewer` adds content-scoped image viewer attributes **after** sanitization for opted-in Home/Journal uses. Normal Artwork Gallery navigation and video players remain distinct consumer modes. Details and acceptance limits: [Editorial Rich Text](EDITORIAL-RICH-TEXT.md), [Media](MEDIA.md), [Public](PUBLIC-IMPLEMENTATION-CONTRACT.md), [Journal](architecture/journal-content-lifecycle.md).

## Media architecture

`MediaAsset` is the canonical reusable original; `MediaVariant` is a rebuildable derivative.

Media ingestion owns durable Storage files and their database records independently from Artwork editing. New originals/variants must be ingested **before** the Artwork write transaction; creation/editing then changes Artwork fields, its primary media linkage and per-artwork ALT override together in one database transaction through the existing domain services. Failed Artwork validation must not roll back a successfully ingested MediaAsset while leaving physical files untracked; the file remains reusable in Storage. Do not ingest a file inside an outer Artwork database transaction.

Gallery Artwork editing has one canonical form and writer (`ArtworkEditDialog`) shared between the Gallery workspace action and the Artwork deep-link EditRecord. The latter exists to edit unassigned drafts and as the destination of admin audit/Home links; it has no separate mutation rules. Gallery assignment changes are committed only by `ArtworkGalleryAssignmentService`, never by Filament form relationship autosave. Creation is owned by the Gallery workspace; superseded `CreateArtwork` and `ManageGalleryArtworks` resource routes/pages were removed, and callers link to `GalleryWorkspace::getUrl()` directly.

Structured consumers include Artwork media, Journal Cover/Gallery, Custom Page Image/List components, Home Image/List components and site identity. Direct-image references in historical CV rows belonged to the retired `cv_entries` table. Rich Text references use `media:<id>`.

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
- Custom.

**Skip Home is not a fourth presentation mode:** it is an independent routing setting owned by `HomeRoutingSettingsService` and persisted as `HomePresentationSetting.skip_home` plus an optional `skip_target_section_id`. `HomeTemplate::SkipHome` remains for historical migration compatibility, not as an editor-selectable current content mode. The selected content mode and its configuration remain stored while Home is bypassed.

Artwork mode resolves deterministic candidates from eligible public Gallery/Artwork data with explicit tie-break state when needed. Under Construction and Custom use ordered structured components. Heading and Rich Text share canonical storage type `text`; editor-only subtype state controls which editing UI is shown without creating another persisted component type.

Home and Custom Page share one public component renderer and component
validation contract. The Home editor offers Image, Heading/Rich Text, List,
Divider, Contact and Legal Disclaimer, with independently publishable
components and List/Contact children. Public presentation uses canonical
Divider variants, filters unpublished content and resolves List direct media
and embedded Rich Text references through the same MediaAsset system.

## Journals

Blog Posts and Exhibitions remain separate content models owned by a Journal SiteSection.

Journal settings own collection title/introduction and active template. Switching template does not transform/delete the inactive content model.

### Exhibition presentation

Exhibition editorial state includes structured venue/address fields plus optional presentation features:

- `gallery_enabled`;
- `map_enabled`.

`location_text` is the street-address field; city and country are separate structured fields. Geocoding is an implementation detail of an enabled Map feature, not a standalone editorial “Find location” task.

`ExhibitionMapPresentation` owns canonical coordinates, embed and external URL sources. The editor retains its OSM iframe preview; public Exhibitions renders one responsive, on-demand native Leaflet dialog using those coordinates (pinned local vendor files), with visible OpenStreetMap attribution and no inline iframe. Map aspect-ratio selection has been retired from the active model, editor and public UI; the historical `map_shape` column remains physically present solely to preserve current Publication snapshot schema compatibility pending an explicitly coordinated schema cutover.

Archived Exhibitions remember their prior state where available. Historical archived rows introduced before that field existed are forward-reconciled deterministically from publication evidence and restore safely through current readiness rules.

## Ordering

Ordered editorial records persist explicit position. Native Livewire sorting (`wire:sort`, `wire:sort:item`, `wire:sort:handle`) is the admin interaction mechanism; domain ordering services remain persistence authority.

Ordering Activity uses `AdminAuditService::recordOrdering()` with a stable domain scope and exact canonical before/after identity sequences. `AdminActivityOrderingProjector` groups a state-continuous sequence into one visible forensic Activity so routine sorting does not create hundreds of rows. The group remains visible regardless of whether its final order equals its starting order; identity only means there is no meaningful inverse to offer as Undo. Composition is publication-bounded and never timer-, debounce- or burst-based.

Filtered/search projections do not silently become canonical reorder sequences. Ranked tables may expose a 1-based Position column as described in `docs/ADMIN-UI.md`.

## Custom Pages and Contact

A Custom Page owns an ordered structured component list. Supported persisted types are `image`, `text`, `list`, `divider`, `contact` and `legal_disclaimer`. CV/Vita uses ordinary `list` and `text`; there is no separate persisted `CV List` type. See [structured components](architecture/structured-components.md).

Parent and child mutations use canonical workspace/domain state; UI hierarchy does not imply a second persistence model. Contact child types remain structured and bounded rather than free-form duplicated components.

## General settings

`PublicContentSetting::general()` is the canonical site-wide settings record for favicon/site identity, public email + visibility, private Contact recipient, social links and truly global legal/public text.

Text settings use event-driven persistence: local while typing, persist on normal change/blur only when normalized value differs. Toggles/selects/media choices may persist on discrete changes. SMTP/DKIM/TLS secrets remain platform/runtime state.

## Analytics

The [Analytics contract](ANALYTICS.md#failure-isolation) alone specifies Matomo tracking/reporting, privacy minimization and network-failure-versus-invalid-configuration. This overview does not create another Analytics availability rule.

## Admin Notifications, Activity and Publication

Three responsibilities are distinct: [Notification](ADMIN-NOTIFICATION-CONTRACT.md) owns actor-scoped mailbox/header messaging, the `AdminNotifier` API, Activity-derived message identity and delivery/retention; [Activity/Publication](ADMIN-ACTIVITY-PUBLICATION-CONTRACT.md) owns audit, Undo, Working/LIVE state and Commit history; [Release](RELEASE.md#release-notes-and-dashboard-announcements) owns static code-backed Changelog/Announcement text. Domain mutation/audit are not automatic Notification delivery.

Filament uses native Livewire navigation. Its concrete `BODY_END`, `@persist` and disposal behavior is [source-traced](architecture/admin-notification-transport.md), not considered browser-verified by this overview.

## Audit and logical Commit

`audit_events` is append-only forensic evidence for successful administrative mutations. It is the persistence source behind the visible Activity/Commit history, not a second hidden journal. Uncommitted audit events are projected as current Activity; a successful Commit links every still-uncommitted event to that Commit, after which it is inspectable through Commit details and no longer individually Undo-capable.

A shell-level Commit promotes the current Working state to LIVE and closes the current Activity generation. Publication truth comes from the actual `public.*` versus `committed.*` state, not from per-event pending flags. Commit/restore/reset/revert behavior continues through canonical Publication services rather than through Notifications.

Publication restore rows remain logically full snapshots, but their immutable JSON payloads are content-addressed in the shared `history_payloads` store. Publication manifests and Undo receipts may reference the same payload identity, so shared history data is stored once and garbage-collected only after all recovery roots release it. Audit evidence remains append-only in normal runtime; current Activity is the uncommitted projection of that evidence and committed Activity is viewed through its Commit. Undo receipts and older Publication restore payloads are bounded/reclaimable recovery data.

The Storage allowance is a whole-site logical contract: canonical originals, generated variants and logical persistent database/application data count toward site usage. Raw PostgreSQL physical-file size is an operational metric rather than billable site usage. Storage's explicit reclaim action may clear Undo and older unprotected restore roots, but must protect LIVE/current Working restore context and must never delete Activity history.

## Security and trust boundaries

- public visitors read only published content/explicitly public media;
- `/admin` requires authenticated/authorized access;
- protected preview is authenticated and does not publish draft media/content;
- uploads and migrated source files are untrusted input;
- persistent admin notifications are scoped to their intended authenticated recipient;
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

Do not freeze temporary CSS class names or current markup through brittle snapshots when browser acceptance is the real requirement. During reconciliation, use the narrowest evidence appropriate to the changed behavior. A later release candidate needs explicit user authorization for promotion to protected `main` and a separately authorized release-verification gate; neither CI nor a PR workflow is automatically permitted in the current branches.

## End-to-end ownership map

For practical change planning, start with the [three cross-feature compositions](architecture/cross-feature-compositions.md): artist Edit→Working→Publication→Public, Exhibition/Journal visibility/media/viewer, and Livewire Public page/SPA ownership. These diagrams link existing contractual owners and historical detailed traces; they are not more individual class-import diagrams. The [Worker Protocol](agents/README.md) uses this architecture to enumerate all shared consumers before an implementation change, including previously accepted behaviors and isolation/verification boundaries.

## Current public and media architecture (source delta dev@434975e1bd09)

The Public site now uses a **static shared paper frame**, configurable artist/header/menu divider, Gallery presentation modes, optional video and global Background/Menu/Page paint surfaces. `PublicAppearance` and `GalleryPresentation` own normalized persisted settings, while `PublicSiteContext` separates committed public reads from preview working reads. Image thumbnails use bounded libvips; canonical video posters use FFmpeg; `MediaPreviewVariant` selects approved public/Admin previews. Optional immutable rights files and selections live under `MediaRightsArtifactService`/`MediaRightsPublicationSelections`. Durable file-deletion requests are owned by the `PublicationMediaCleanupService` outbox. These responsibilities are described normatively by [Public](PUBLIC-IMPLEMENTATION-CONTRACT.md), [Media](MEDIA.md), [Rights](PUBLIC-RIGHTS-AND-PRIVACY.md) and [Publication](ADMIN-ACTIVITY-PUBLICATION-CONTRACT.md), not duplicated as new requirements here.

This is a **selected source reconciliation**, not import of the complete `dev` tree into `docs`. The native documentation generator results of 19 October and old artifacts still refer to the previously checked-out `docs`-code SHA. No artist browser acceptance or protected Validation database operation was performed.

