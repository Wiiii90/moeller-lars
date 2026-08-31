# Migration invariants

These invariants define the lossless, reviewable boundary between the frozen legacy source and the current Laravel/PostgreSQL application. They are migration/reconciliation rules, not a requirement to preserve legacy schema, routes or runtime behavior.

## Artwork source accounting

For every in-scope source Artwork:

- source identity/factual editorial fields are accounted for;
- target Gallery assignment/order is explicit;
- no unexplained source/target count difference is accepted;
- provenance remains evidence, never runtime fallback;
- public ordering is persisted rather than inferred from IDs/insertion order.

The current application also supports a later editorial unassigned Artwork state; that does not change imported-source reconciliation.

## Media and original-file integrity

Canonical originals are retained. Generated derivatives never replace authority.

Migration/reconciliation records enough evidence for source path/name provenance, target MediaAsset identity, byte size, content MIME, SHA-256 and required references.

Missing/corrupt/unsupported/ambiguous files are explicit findings. Deduplication may share one canonical original only when every intended usage remains represented.

## ALT semantics

Meaningful legacy ALT semantics are preserved where valid, but current runtime authority follows the consumer contract.

- `MediaAsset.alt_text` is canonical asset-level ALT;
- Artwork may use an explicitly supported usage override;
- structured Journal Cover/Gallery runtime uses MediaAsset ALT exclusively;
- historical Journal override columns/values may remain as compatibility evidence but do not control rendering/readiness;
- required public ALT is never manufactured from filenames/IDs.

## Rich Text media canonicalization

Current canonical Rich Text embedded-image syntax is Markdown `media:<id>` through `RichTextMediaReference`.

Legacy Journal inline-media runtime tokens/roles are migration evidence only.

Forward Journal canonicalization must:

- recognize only the exact reviewed legacy token form;
- resolve each token unambiguously to its canonical MediaAsset;
- abort on missing/duplicate/unresolved/orphan evidence;
- replace occurrences with central Markdown references;
- remove legacy inline Journal media rows after successful resolution;
- verify no runtime inline role/token remains;
- preserve structured Cover/Gallery usages independently.

Do not reintroduce legacy token parsing as a normal runtime fallback after migration.

## Date and ordering semantics

Legacy Gallery display order is reconciled into explicit target positions. Equal-date rows are not silently ordered by source/target ID or incidental DB order.

When authoritative ordering cannot be established, migration records an explicit reviewed/editorial exception.

The Home candidate/winner for the reviewed snapshot reconciles with approved source behavior while current runtime uses explicit Gallery eligibility and Artwork date/tie-break semantics.

## Vita/CV and Exhibition source accounting

Reviewed legacy Vita textual inventory contains exactly **31 source rows**:

- **2 Biography/CV source rows**;
- **29 Exhibition rows**.

Each source row is accounted once. Exhibition rows are not duplicated as CV content. Portrait provenance reconciles by source identity/bytes/SHA-256/MediaAsset attachment.

Public CV/Vita placement is a Custom Page composition, not a dedicated runtime Site Node type.

## Exhibition address invariant

Canonical Exhibition address fields are structured:

- `location_text` = street address only;
- `city` = city;
- `country` = country.

Forward cleanup may remove only conservative obvious duplicates where `location_text` equals city, country or the already-structured city/country combination. It must not invent a street address when none is known.

Public/admin composition deduplicates Street, City and Country rather than storing combined location prose in the street field.

## Exhibition archived-state invariant

Historical archived Exhibitions may predate `archived_from_state`.

Forward reconciliation for `state=archived` with missing previous state uses deterministic evidence:

- `published_at` present → previous state `published`;
- otherwise → `draft`.

Restoring a historical candidate toward Published still passes current public-readiness validation. If readiness fails, the record restores safely to Draft rather than becoming un-restorable or incorrectly public.

New archive operations record the exact previous Draft/Published state.

## Exhibition optional presentation features

Gallery and Map are explicit per-Exhibition presentation features.

- `gallery_enabled=false` does not delete/detach stored Journal Gallery media;
- disabled Gallery rows remain canonical reference evidence;
- ordinary public media delivery excludes disabled Gallery media;
- Cover publication remains independent of Gallery enabled state;
- `map_enabled` controls public map presentation;
- enabled Map requires valid geodata under the canonical geocoding workflow;
- `map_shape` is currently `wide` or `square`;
- Map presentation is not inferred merely from Exhibition timing.

## Journal template retention invariant

A Journal may switch active template Blog ↔ Exhibitions without destructive conversion.

- BlogPost rows remain owned by the Journal while Exhibitions is active;
- Exhibition rows remain owned while Blog is active;
- switching back restores access to retained rows;
- no template change deletes or converts entries;
- media reference/deletion accounting includes both retained worlds regardless of active template;
- public rendering follows only the current supported active Journal template/lifecycle.

## Contact migration/content invariant

Contact is not a dedicated runtime Site Node/page type.

Migration preserves reviewed contact/profile data while normalizing it into:

- reusable structured Contact component in Custom Page composition;
- General-owned public email/social/global identity;
- General/runtime private delivery recipient;
- no SMTP/server secrets as migration content.

## Canonical Site Node projection

Runtime types are Home, Gallery, Journal, Custom Page and Navigation Node.

Reconciliation requires exactly one Home; one matching Gallery Site Node per migrated Gallery persistence row; valid Gallery hierarchy; Custom Page placement for migrated CV/Vita; Journal Blog/Exhibitions placement/settings/ownership; structural-only Navigation Nodes where required; no standalone Contact node requirement.

## Custom Page and Journal integrity

Every Custom Page requires exactly one `CustomPageSetting` and valid ordered components.

Every Journal requires a supported active `JournalTemplate` and one `JournalSetting`. Retained inactive Blog/Exhibition rows remain owned and non-orphaned; current active projection/public behavior must not require destructive data conversion.

Missing required settings are not repaired at read time through silent fallback records.

## Fresh import versus protected canonical data

A clean-database source import remains repeatable for rehearsal/reconstruction.

Once reviewed data exists in protected Validation/Production:

- it is canonical application data;
- target evolution uses forward Laravel migrations;
- source importer is not rerun destructively into non-empty canonical tables;
- reconciliation is read-only except for explicitly approved forward migrations/editorial corrections.

A failed forward migration is a release blocker, not permission to erase protected evidence.

## Redirect and public-route reconciliation

Legacy PHP/query URLs are evidence, not blanket compatibility surface.

Current typed routes include Home `/`, Gallery/Journal/Custom `/{section-slug}`, Journal entry `/{section-slug}/{entry-slug}`, Artwork `/artworks/{slug}`; Navigation Nodes have no public URL.

No standalone Contact route is required merely because legacy content had Contact semantics.

## Validation output

`php artisan legacy:validate <manifest>` is read-only reconciliation for the reviewed source snapshot. It makes counts, checksum/provenance, ordering, Site Node projection, Vita/CV/Exhibition accounting, route checks, warnings and reviewed exceptions visible.

No unexplained discrepancy is silently normalized.

## Acceptance boundary

Migration reconciliation is necessary but not sufficient for Production acceptance. Separate gates remain application verification, exact release identity, protected Validation, public/admin browser acceptance, editorial approval and platform backup/restore/rollback readiness.

## Explicit exclusions

Never migrated as runtime authorities:

- legacy authentication/users/sessions/passwords;
- DB/mail/API credentials or server secrets;
- legacy SQL/table/admin/upload/parser/debug implementation;
- workshop/development tooling outside approved artist-site target.

Secrets/private dumps/authoritative private media must not appear in Git, migration reports or screenshots.
