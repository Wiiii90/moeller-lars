# Project charter

## Product goal

`moeller-lars` is the secure, maintainable replacement application for the Lars Möller artist website and its artist-facing administration.

The public site preserves the artist's established visual language/content while replacing the legacy application's security model, administration, persistence, analytics integration, migration tooling and release process.

## Public contract

Non-negotiable principles:

- preserve approved public content, artwork presentation and meaningful information architecture;
- preserve the site's recognisable artistic visual language rather than redesigning it into a generic portfolio/CMS theme;
- use clean path-based canonical URLs;
- keep HTTPS canonical and do not expose debug/admin/development surfaces publicly;
- preserve meaningful Artwork ordering and media/ALT semantics through explicit canonical data;
- keep the Artwork viewer reliable across desktop/mobile/touch/keyboard interaction;
- treat broken/unsafe legacy behavior as a defect, not compatibility requirement;
- require browser/editorial acceptance in addition to automated route/data checks before Production cutover.

## Site structure

The editable site uses five runtime node concepts:

- **Home**
- **Gallery**
- **Journal** — Blog or Exhibitions active template
- **Custom Page**
- **Navigation Node**

CV/Vita is structured Custom Page/CV content. Contact is a reusable structured component, not a standalone Contact node/admin destination.

Journal template switching is non-destructive: inactive Blog/Exhibition rows remain retained and become available again when the template is switched back.

Historical persistence names may remain where renaming adds migration risk, but do not define artist-facing domain language.

## Artist administration

`/admin` is a purpose-built authenticated editorial application, not a general-purpose builder or generic SaaS dashboard.

Canonical responsibilities:

1. **Dashboard** — concise real overview, not Pages/Gallery duplication.
2. **Pages** — typed site structure, hierarchy, ordering, navigation and publication.
3. **Home** — singleton presentation configuration and relevant Home-specific editorial data.
4. **Gallery / Artworks** — visual artwork workspace, metadata/media/publication/order/assignment.
5. **Journals** — Blog/Exhibitions workspaces and settings.
6. **Custom Pages** — safe structured composition including CV/Vita and reusable Contact.
7. **Files** — canonical reusable MediaAsset library.
8. **General** — site identity, contact/social/global legal/public settings; no infrastructure secrets.
9. **Analytics** — privacy-conscious Matomo reporting plus clearly separate operational aggregates.
10. **Activity** — durable admin/editorial history.
11. **Storage** — artist-facing site allowance/usage, not host-wide infrastructure capacity.

Persistent Preview / future logical Commit / Settings utilities may exist at shell level, but normal form persistence is independent of logical Commit/checkpoint concepts.

## Admin interaction principles

The detailed admin UI grammar is maintained in [`../ui-skills.md`](../ui-skills.md).

Durable principles:

- one visible page heading per normal workspace;
- no decorative kicker/eyebrow layer;
- factual shared metric strips where meaningful, without invented filler;
- compact control labels above actions/inputs;
- one visible Selection control per task surface;
- stable table/grid columns and action slots;
- task-specific layouts instead of one generic card template;
- native Livewire ordering and explicit persisted position;
- shared accessible dialogs/overlays;
- central Rich Text/media-selection technologies rather than page-local forks;
- text settings persist on normal change/blur only when changed;
- destructive/publication operations are explicit, authorized and audited.

Browser acceptance may reject a technically functioning implementation for poor/inconsistent UI. A healthy container or green CI is not product acceptance.

## Central Rich Text contract

Current Rich Text surfaces share:

```text
AdminRichText / Filament MarkdownEditor
  -> Markdown
  -> RichTextMediaReference
  -> SafeRichTextRenderer
  -> public HTML
```

Canonical embedded Media Files image references use `media:<id>`. Do not create editor-specific parallel upload/image syntax or resurrect legacy Journal RichEditor/TipTap runtime behavior.

## Media

`MediaAsset` is the reusable canonical original. References are explicit and separate from public-delivery eligibility.

- `MediaReferenceQuery` protects/reports canonical references, including retained inactive Journal content;
- `PublicMedia` determines current ordinary public eligibility;
- protected preview does not create another asset type or publish draft content;
- structured Journal Cover/Gallery uses Media Files ALT at runtime;
- disabled Exhibition Gallery remains stored/referenced but is not publicly deliverable as Gallery media;
- generated variants are rebuildable and never replace the original as authority.

An Artwork may temporarily be unassigned from a Gallery when lifecycle permits; detaching preserves Artwork/MediaAsset relationships.

## Journals and Exhibitions

Blog and Exhibitions remain separate content products under Journal placement.

Exhibition Gallery and Map are explicit optional presentation features. Street/City/Country are structured independently. Map geocoding is triggered by the enabled Map workflow rather than exposed as a standalone editorial “Find location” concept.

Archived Exhibition restore must preserve known prior state and safely reconcile historical rows that predate explicit prior-state storage.

## Security

- `/admin` requires authenticated/authorized access;
- authorization is server-side for mutations;
- CSRF/session/rate-limit protections use application security boundaries;
- uploads are untrusted until validated;
- unsafe rich text/links are rejected/sanitized through canonical policies;
- secrets/private dumps/authoritative Production media stay outside Git;
- legacy authentication/credentials/SQL/session/upload code are never reused.

## Analytics

Self-hosted Matomo Community/Core is canonical human analytics. Local aggregates cover operational/error/bot/performance signals only. Analytics availability does not become a dependency for public rendering or normal admin editing.

## Cost and operational boundary

Avoid mandatory commercial runtime/SaaS dependencies where practical. Hosting cost should remain minimized and justified against reliability, backup, security and maintenance.

This repository owns application/release contract. `server-platform` owns mutable Production/Validation infrastructure, secrets, ingress, backups, monitoring, deployment and rollback.

## Out of scope

- general-purpose free-form site builder;
- public customer accounts/marketplace/social-network functionality;
- migration of legacy credentials/users/sessions;
- mandatory commercial analytics/runtime services;
- infrastructure topology/control surfaces inside app admin;
- preserving legacy bugs/unsafe implementation details;
- maintaining multiple equivalent Rich Text/media/admin UI technologies.

## Release acceptance

A release is not accepted merely because CI is green, migrations run or a local container is healthy.

Before Production cutover, the approved exact SHA/image must pass applicable gates:

- durable automated application/security/data verification;
- migration/media reconciliation;
- isolated Validation/release identity;
- representative admin browser acceptance;
- representative public/browser/viewer comparison;
- artist/editorial approval;
- platform backup/restore/rollback/readiness.

Production deployment remains an explicit authorized platform action.

## Documentation ownership

Current contracts live under `docs/` and are indexed by `docs/README.md`. Workflow rules live in root `AGENTS.md`; reusable admin UI rules in `ui-skills.md`; continuation-handoff rules in `followup-skill.md`.

GitHub Issues/current browser review track unfinished work/acceptance. Legacy evidence is kept separate and retired only after explicit legacy retirement.
