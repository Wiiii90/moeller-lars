# Migration invariants

These invariants define the lossless, reviewable boundary between the frozen legacy source and the current Laravel/PostgreSQL application. They are migration/reconciliation rules, not a requirement to preserve legacy schema, routes or runtime behavior.

## Artwork source accounting

The reviewed legacy artwork source contains the factual tables/categories `paintings`, `drawings` and `prints` used by the landing/gallery queries.

For every in-scope source artwork:

- source identity and factual editorial fields are accounted for;
- the target Artwork belongs to the intended Gallery;
- no unexplained source or target record count difference is accepted;
- legacy provenance remains migration evidence and is never used as a runtime fallback;
- target public ordering is explicit and persisted rather than inferred from source/target IDs or database order.

Broader legacy dispatcher labels remain source evidence only. A target Gallery exists only when the reviewed migration mapping supports it.

## Media and original-file integrity

Canonical originals are retained. Generated derivatives never replace the authoritative original.

Migration/reconciliation records enough evidence to prove each in-scope original:

- source path/name provenance;
- target MediaAsset identity;
- byte size;
- content MIME classification;
- SHA-256 checksum;
- required Artwork/content references.

Missing, corrupt, unsupported or ambiguous files are explicit findings. They are not silently discarded or replaced with another file.

Deduplication may share one canonical original only when every intended usage relationship is preserved.

## ALT semantics

Meaningful legacy ALT/title semantics are preserved where valid. The target may make an accessibility correction when source ALT is empty, misleading or unsafe, but that change must be explicit rather than accidental.

For public content, missing required canonical ALT data is an invariant/readiness failure. Filenames, titles or legacy metadata are not runtime substitution rules unless the product contract explicitly defines them as authored values.

## Date and ordering semantics

Legacy category display order is reconciled into explicit target positions. Equal-date source rows must not be silently ordered by source ID, target ID, insertion order or incidental database behavior.

If an authoritative order cannot be established, migration records an explicit editorial exception for review.

The Home candidate/winner for the reviewed snapshot must reconcile with the approved source behavior while using the target application's persisted Gallery eligibility and Artwork date/feature semantics.

## Vita/CV and Exhibition source accounting

The reviewed legacy Vita source is `txt/vita.txt` plus the public portrait.

The reviewed textual inventory contains exactly **31 source rows**. Their approved canonical partition remains:

- **2 Biography/CV source rows** retained as canonical structured migration/editorial data;
- **29 Exhibition rows** in `exhibitions`.

A source row must be accounted for exactly once. Exhibition rows must not remain duplicated as CV content.

The portrait relationship and source provenance are reconciled by asset identity, byte size and SHA-256.

The current Site Structure no longer has a dedicated `vita` node type. The migrated public CV/Vita presentation is a **Custom Page** whose structured blocks consume the canonical migrated content/settings. Historical CV records may remain as migration/editorial data where required; they do not define a separate runtime site-node type.

## Canonical Site Node projection

The current runtime types are defined by `SiteNodeType`: Home, Gallery, Journal, Custom Page and Navigation Node.

Reconciliation requires:

- exactly one **Home** Site Node;
- Home has no slug, is published and is represented in navigation;
- every `artwork_categories` row has exactly one **Gallery** Site Node referencing it;
- no Gallery Site Node references a missing Gallery persistence record;
- Gallery hierarchy matches the approved parent relationship and obeys current SiteNode parent rules;
- the migrated CV/Vita placement is a **Custom Page** with its required `custom_page_settings` row;
- the migrated Contact placement is a **Custom Page** with its required structured contact component/settings;
- the migrated Blog placement is a **Journal** with template `blog`, a `journal_settings` row, and every migrated Blog Post bound to that Journal by `site_section_id`;
- the migrated Exhibitions placement is a **Journal** with template `exhibitions`, a `journal_settings` row, and every migrated Exhibition bound to that Journal by `site_section_id`;
- Navigation Nodes are structural/editorial data only and require no legacy counterpart unless explicitly mapped.

There is no migration invariant requiring singleton runtime types named `vita`, `contact`, `blog` or `exhibitions`; those were superseded by the configurable Site Node model.

## Custom Page and Journal integrity

Every Custom Page requires exactly one `CustomPageSetting` record. Its ordered structured blocks must pass the current validation contract.

Every Journal requires a supported `JournalTemplate` and exactly one `JournalSetting`. Blog Posts and Exhibitions must belong to the correct template-specific Journal and must not be orphaned during migration.

Migration must not repair missing required settings at read time through fallback records or implicit defaults.

## Fresh import versus protected canonical data

A clean-database source import remains repeatable for migration rehearsal and reconstruction.

Once reviewed imported data exists in protected Validation or Production state:

- it is canonical application data;
- target schema evolution uses normal forward Laravel migrations;
- the source importer is not rerun destructively into non-empty canonical tables;
- reconciliation is read-only except for explicitly approved forward migrations/editorial corrections.

A failed forward migration or reconciliation failure is a release blocker, not permission to erase protected evidence.

## Redirect and public-route reconciliation

Legacy PHP/query URLs are historical evidence, not a blanket compatibility surface.

Migration/cutover checks only redirects that have an explicit SEO/external-link/product requirement. New-application slug changes may create durable redirect records under the current redirect policy.

Public route reconciliation verifies the current typed route model:

- Home `/`;
- Gallery/Journal/Custom Page `/{section-slug}`;
- Journal entry `/{section-slug}/{entry-slug}`;
- Artwork detail `/artworks/{slug}`;
- Navigation Nodes have no public URL.

Unknown/malformed routes must fail safely without legacy warning/debug behavior.

## Validation output

`php artisan legacy:validate <manifest>` is a read-only migration reconciliation tool for the reviewed source snapshot. A successful report must make the following visible:

- source/target counts;
- media/checksum integrity;
- provenance coverage;
- ordering differences;
- Site Node projection;
- Vita/CV/Exhibition accounting;
- required redirect/public-route checks;
- warnings and explicit reviewed exceptions.

No unexplained discrepancy is normalized silently.

## Acceptance boundary

Migration reconciliation is necessary but not sufficient for Production acceptance.

Separate gates remain:

- application CI;
- exact release-image identity;
- isolated Validation deployment;
- browser/public comparison;
- admin functional acceptance;
- artist/editorial approval;
- platform backup/restore/rollback readiness.

## Explicit exclusions

The following are never migrated as target runtime authorities:

- legacy authentication/users/sessions/password material;
- database/mail/API credentials or server secrets;
- legacy SQL/table architecture;
- legacy admin/upload/parser/debug implementation;
- workshop/development tooling outside the approved artist-site content scope.

Secrets, private dumps and authoritative private media must not appear in Git, migration reports or screenshots.
