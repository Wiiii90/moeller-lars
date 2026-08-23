# Project charter

## Product goal

`moeller-lars` is the secure, maintainable replacement application for the Lars Möller artist website and its artist-facing administration.

The public site preserves the artist's established visual language and content while replacing the legacy application's security model, administration, persistence, analytics integration, migration tooling and release process.

## Public contract

Non-negotiable principles:

- preserve approved public content, artwork presentation and meaningful information architecture;
- preserve the site's recognisable artistic visual language rather than redesigning it into a generic portfolio/CMS theme;
- use clean path-based canonical URLs; legacy PHP/query syntax is not itself a compatibility requirement;
- keep HTTPS canonical and do not expose debug/admin/development surfaces publicly;
- preserve meaningful artwork ordering and media/ALT semantics through explicit canonical data;
- keep the Artwork viewer reliable across desktop/mobile/touch/keyboard interaction;
- treat broken or unsafe legacy behavior as a defect, not a compatibility requirement;
- require browser/editorial acceptance in addition to automated route/data checks before Production cutover.

## Site structure

The editable public site uses five runtime node concepts:

- **Home**
- **Gallery**
- **Journal** — Blog or Exhibitions template
- **Custom Page**
- **Navigation Node**

CV/Vita is represented through structured Custom Page content rather than a special runtime node type. Contact is a reusable structured component that may be composed into a Custom Page; it is **not** a standalone Contact node/admin destination. Blog and Exhibitions are Journal templates.

Historical persistence names may remain where renaming them would add migration risk, but they do not define the artist-facing domain language.

## Artist administration

`/admin` is a purpose-built authenticated editorial application, not a general-purpose site builder.

Canonical responsibilities:

1. **Dashboard** — concise site/admin overview based on real Analytics, Activity and actionable health; it does not reproduce the Pages tree or Gallery contact sheet.
2. **Pages** — typed site structure, hierarchy, ordering, navigation and publication.
3. **Gallery / Artworks** — visual artwork workspace, metadata, primary media, publication, ordering and Gallery assignment/removal.
4. **Journals** — Blog and Exhibitions collection workspaces.
5. **Custom Pages** — safe structured component composition, including CV/Vita and reusable Contact content.
6. **Files** — canonical reusable MediaAsset library for upload, search, preview, metadata, reference inspection and guarded deletion.
7. **General** — site identity, public/private contact settings, social links and truly global legal/public text; no infrastructure secrets.
8. **Analytics** — privacy-conscious Matomo reporting plus clearly separate operational aggregates.
9. **Activity** — durable admin/editorial history.
10. **Storage** — artist-facing site allowance/usage, not host-wide infrastructure capacity.

Persistent global Preview / Commit / Settings utilities may be added at the shell level, but ordinary form persistence remains independent from the logical Commit/checkpoint concept.

## Admin interaction principles

- one deliberate visible page heading per normal workspace;
- useful desktop width and one shared workspace axis/gutter system;
- task-specific layouts instead of one generic card template;
- no repeated SaaS/LLM card wall or unnecessary explanatory prose;
- shared dialogs/overlays must behave as real accessible dialogs, not navigation disguised as modal UI;
- text settings persist on normal change/blur only when changed, with no debounce/timer-based persistence; toggles/selects/media may persist on discrete change;
- destructive/publication operations remain explicit, authorized and audited.

## Security

- `/admin` requires authenticated/authorized access.
- Authorization is enforced server-side for mutations.
- CSRF/session/rate-limit protections use the application security boundary rather than UI visibility.
- Uploads are untrusted until validated.
- Unsafe rich text/links are rejected or sanitized through canonical policies.
- Secrets, private dumps and authoritative Production media stay outside Git.
- Legacy authentication, credentials, SQL helpers, sessions and upload code are never reused.

## Media

`MediaAsset` is the reusable canonical original. Current ingest supports explicitly allowlisted images, video and audio under `MediaTypePolicy`; each consumer still decides which media kinds it can use.

References are explicit. Detaching a usage does not delete the canonical asset. A referenced asset cannot be destructively deleted. Generated variants are rebuildable and never replace the original as authoritative data.

An Artwork may temporarily be unassigned from a Gallery when its lifecycle permits; Gallery removal must preserve its Artwork and MediaAsset relationships.

## Analytics

Self-hosted Matomo Community/Core is the canonical source for human visitor analytics. Application-local aggregates cover operational/error/bot/performance signals only.

No mandatory paid analytics plugin or analytics SaaS is required. Analytics availability must not become a dependency for public rendering or ordinary admin editing.

## Cost and operational boundary

Avoid mandatory commercial runtime/SaaS dependencies where practical. Hosting/operations cost is allowed but should remain minimized and justified against reliability, backup, security and maintenance requirements.

This repository owns the application/release contract. [`Wiiii90/server-platform`](https://github.com/Wiiii90/server-platform) owns mutable Production/Validation infrastructure, secrets, ingress, backups, monitoring, deployment and rollback.

## Out of scope

- general-purpose free-form website builder;
- public user registration/customer accounts;
- marketplace/social-network functionality;
- migration of legacy credentials/users/sessions;
- mandatory commercial analytics/runtime services;
- infrastructure topology/control surfaces inside the application admin;
- preserving legacy bugs or unsafe implementation details.

## Release acceptance

A release is not accepted merely because CI is green.

Before Production cutover, the approved exact SHA/image must pass the applicable gates:

- durable automated application/security/data tests;
- migration/media reconciliation;
- isolated Validation deployment and release identity verification;
- representative admin functional/browser acceptance;
- representative public/browser/viewer comparison;
- artist/editorial approval;
- platform backup/restore/rollback/readiness checks.

Production deployment remains an explicit authorized platform action.

## Documentation ownership

Current application contracts live under `docs/` and are indexed by [docs/README.md](README.md). GitHub Issues track unfinished work and acceptance status; the contract documents should describe the current architecture rather than duplicate issue-history diaries.

Legacy-source evidence is kept separate and may be retired only after explicit legacy retirement.