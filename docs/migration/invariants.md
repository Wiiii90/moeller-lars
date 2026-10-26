# Migration invariants

These invariants define the lossless, reviewable boundary between the frozen legacy source and the current Laravel/PostgreSQL application. They are migration/reconciliation rules, not a requirement to preserve legacy schema, routes or runtime behavior.

## Artwork source accounting

For every in-scope source Artwork:

- source identity/factual editorial fields are accounted for;
- target Gallery assignment/order is explicit;
- no unexplained source/target count difference is accepted;
- provenance remains evidence, never runtime fallback;
- public ordering is persisted rather than inferred from IDs/insertion order.

The current application also supports a later editorial unassigned Artwork state; that does not change imported-source reconciliation. Artist-approved Validation reassignment/addition/removal/reordering may intentionally differ from Legacy; preserve source provenance and the reviewed final outcome.

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

When authoritative ordering cannot be established, migration records an explicit reviewed/editorial exception. After import, **artist-approved persisted Validation order** is the final target even when intentionally different from historical Legacy order.

The Home candidate/winner for the reviewed snapshot reconciles with approved source behavior while current runtime uses explicit Gallery eligibility and Artwork date/tie-break semantics.

## Vita/CV and Exhibition source accounting

Reviewed legacy Vita textual inventory contains exactly **31 source rows**:

- **2 Biography/CV source rows**;
- **29 Exhibition rows**.

Each source row is accounted once. Exhibition rows are not duplicated as CV content. Portrait provenance reconciles by source identity/bytes/SHA-256/MediaAsset attachment. The 2/29 counts describe the **original source**, not a required final count after approved artist additions, removals or changes.

Public CV/Vita placement is a Custom Page composition, not a dedicated runtime Site Node type.

## Exhibition address invariant

Canonical Exhibition address fields are structured:

- `location_text` = street address only;
- `postal_code` = independently editable postcode (optional);
- `city` = city;
- `country` = country (never inferred solely from a five-digit postcode).

Forward cleanup may remove only conservative obvious duplicates where `location_text` equals city, country or the already-structured city/country combination. It must not invent a street address when none is known.

Public/admin composition deduplicates Street, Postal code, City and Country rather than storing combined location prose in the street field. Postcode schema is mirrored in `public` and `committed` to maintain working/LIVE parity. Historic publication snapshots retain their original schema hashes; this forward schema change does not forge compatibility with older retained payloads.

The Blog's per-post `gallery_presentation` is likewise a forward-only,
publication-tracked field mirrored into `public.blog_posts` and
`committed.blog_posts` with default `sequence`. Older Article entries retain
an uncropped sequential gallery until the artist explicitly chooses another
presentation. No retained snapshot fingerprint is silently rewritten.

The local-only `exhibitions:normalize-addresses` command provides a read-only preview by default and an explicit `--apply` conversion for confidently parsed legacy entries. It does not invent countries, geocode automatically, change coordinates, overwrite already structured entries or silently guess ambiguous compound locations. The original legacy importer record remains the provenance source; read-only legacy validation allows either its original raw address or the explicit canonical normalized form.

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
- legacy `map_shape` values remain in the physical schema for Publication snapshot compatibility only; map aspect ratio is no longer editable or used in active presentation;
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

A clean-database Legacy import remains repeatable for source/provenance rehearsal; it is **not** the required Production-content source once the artist has curated Validation.

Once reviewed data exists in protected Validation/Production:

- it is canonical application data; protected Validation is the evolving editorial cutover candidate;
- target evolution uses forward Laravel migrations while preserving original media and editorial state;
- source importer is not rerun destructively into non-empty canonical tables;
- Legacy reconciliation is read-only except for reviewed forward migrations or explicit artist/editorial corrections;
- intentional approved Legacy-to-Validation differences remain traceable rather than classified as unexplained data loss.

At final cutover, freeze the **approved, internally consistent Validation PostgreSQL and original-media corpus**; derive an independent Production target using an explicit content/runtime sanitation matrix for sessions, secrets, users, mail/analytics configuration and test-only artifacts. Check Publication Working/LIVE, original checksums, recovery roots and exact application release compatibility, with isolated restore and rollback verification. A failed forward migration or rehearsal is a blocker, not permission to erase protected data. See [#209](https://github.com/Wiiii90/moeller-lars/issues/209).

## Historical migration self-containment

Historical migrations must preserve the schema/domain contract that existed when they were introduced.

- do not import mutable runtime constants such as current publication tracked-table/entity lists into a historical migration;
- freeze the migration's own table/entity set when that set determines `migrate:fresh` behavior;
- evolve the current schema/domain contract through a later forward migration instead of changing history indirectly through runtime code;
- comments may document deliberately asymmetric rollback behavior where restoring a superseded constraint would recreate an invalid dependency.

This rule keeps clean-database reconstruction deterministic without treating old migrations as runtime authority.

**Known current violation (not an exception to the rule):** the historical
[`2026_09_15_000001_add_restorable_publication_versions.php`](../../database/migrations/2026_09_15_000001_add_restorable_publication_versions.php)
imports `App\Domain\Publication\PublicationSnapshot` and uses the
mutable current `PublicationSnapshot::TABLES` in `up()`-time snapshot
backfill, `schemaHash()` and `snapshotHash()`. The original Publication
migration had **16** tracked tables (including retired `cv_entries`),
whereas the current runtime constant has **15**. Thus this historical
step does **not** currently satisfy the self-containment invariant.
This is a source-confirmed **migration-evolution risk**, not evidence
that a particular `migrate:fresh` run failed. Do not patch historical
tables on the `docs` workstream or change snapshot/hash values by
guessing; a separately authorized and isolated migrated PostgreSQL
reconstruction must establish the correct historical behavior first.
See [schema evolution](../architecture/schema-evolution.md#konkreter-quellenkonflikt-noch-nicht-bereinigt).


## Legacy evidence and internal schema retirement

True legacy evidence comes from the frozen pre-Laravel source and remains subject to the cutover/legacy-retirement boundary where that evidence still protects reconciliation or recovery.

`blog_settings` is explicitly **not** legacy-source evidence. It was created by this Laravel application for the original dedicated Blog settings model. Its canonical values were later normalized into `site_sections` / `journal_settings`; runtime and Publication stopped using it as authority or fallback. A later forward migration therefore retires the obsolete table independently of legacy-site retirement.

Historical migrations continue to mention `blog_settings` because they must reconstruct the application's actual schema evolution. Do not rewrite those historical steps merely to hide a superseded internal design. The old `blog_settings -> site_sections` RESTRICT foreign key remains intentionally non-restored because recreating that obsolete coupling would invalidate canonical replacement behavior.

## Redirect and public-route reconciliation

Legacy PHP/query URLs are evidence, not blanket compatibility surface.

Current typed routes include Home `/`, Gallery/Journal/Custom `/{section-slug}`, Journal entry `/{section-slug}/{entry-slug}`, Artwork `/artworks/{slug}`; Navigation Nodes have no public URL.

No standalone Contact route is required merely because legacy content had Contact semantics.

## Validation output

`php artisan legacy:validate <manifest>` is read-only reconciliation for the reviewed source snapshot. It makes counts, checksum/provenance, ordering, Site Node projection, Vita/CV/Exhibition accounting, route checks, warnings and reviewed exceptions visible.

No unexplained discrepancy is silently normalized; a deliberately approved Validation edit is a reviewed source-to-final difference rather than automatically a failed source import.

## Acceptance boundary

Migration provenance **and** approved Validation-to-Production reconciliation are necessary but not sufficient. Independent gates remain exact application identity, v0.4/v0.5-specific browser/product acceptance, editorial approval and platform backup/restore/rollback readiness.

## Explicit exclusions

Never migrated as runtime authorities:

- legacy authentication/users/sessions/passwords;
- DB/mail/API credentials or server secrets;
- legacy SQL/table/admin/upload/parser/debug implementation;
- workshop/development tooling outside approved artist-site target.

Secrets/private dumps/authoritative private media must not appear in Git, migration reports or screenshots.

## Artist-curated Validation protection (current source boundary)

The current forward migration sources for rights artifacts, Gallery settings and Global Public Appearance were reviewed at `dev@434975e1bd09`; **the migrations were not run** in this documentation update. Local synthetic data are not a substitute for the artist's curated Validation content. Validation may hold cutover-relevant originals, editorial fields and Publication history: no reset, recreation, test seed, destructive backfill or migration execution without a separately approved deployment/cutover plan and verified backups. A physical `pg_catalog` audit is to run only against a distinct freshly migrated disposable PostgreSQL database. This clarifies the existing cutover invariant; it is not a new permission to operate on Validation.

