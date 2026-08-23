# Migration invariants

These invariants define the lossless, reviewable boundary between the frozen legacy source and the current Laravel/PostgreSQL application. They are migration/reconciliation rules, not a requirement to preserve legacy schema, routes or runtime behavior.

## Artwork source accounting

The reviewed legacy artwork source contains the factual categories `paintings`, `drawings` and `prints` used by the legacy public site.

For every in-scope source Artwork:
- source identity and factual editorial fields are accounted for;
- target Gallery assignment/order is explicit where the migrated Artwork is assigned;
- no unexplained source/target count difference is accepted;
- legacy provenance remains evidence and never becomes a runtime fallback;
- target public ordering is persisted rather than inferred from IDs/database order.

The current application additionally supports an unassigned Artwork state for later editorial detach/remove workflows. That runtime capability does not change the migration requirement that imported source Artworks reconcile to their intended mapped Galleries.

## Media and original-file integrity

Canonical originals are retained. Generated derivatives never replace the authoritative original.

Migration/reconciliation records enough evidence to prove each in-scope original:
- source path/name provenance;
- target MediaAsset identity;
- byte size;
- content MIME classification;
- SHA-256 checksum;
- required content references.

Missing/corrupt/unsupported/ambiguous files are explicit findings. They are not silently discarded or substituted.

Deduplication may share one canonical original only when every intended usage relationship is preserved.

## ALT semantics

Meaningful legacy ALT/title semantics are preserved where valid. Accessibility corrections may be made when legacy ALT is empty/misleading/unsafe, but the change must be explicit.

Required public ALT is not manufactured from filenames/IDs at runtime.

## Date and ordering semantics

Legacy Gallery display order is reconciled into explicit target positions. Equal-date rows are not silently ordered by source ID, target ID, insertion order or incidental DB behavior.

When authoritative legacy ordering cannot be established, migration records an explicit reviewed/editorial exception.

The Home candidate/winner for the reviewed snapshot must reconcile with the approved source behavior while using current Gallery eligibility and Artwork date/feature semantics.

## Vita/CV and Exhibition source accounting

The reviewed legacy Vita source is `txt/vita.txt` plus the public portrait.

The reviewed textual inventory contains exactly **31 source rows**. The approved canonical partition is:
- **2 Biography/CV source rows**;
- **29 Exhibition rows** in `exhibitions`.

A source row is accounted for exactly once. Exhibition rows must not remain duplicated as CV content.

Portrait identity/provenance is reconciled by byte size, SHA-256 and canonical MediaAsset attachment.

The runtime Site Structure has no dedicated `vita` node type. Public CV/Vita presentation is a **Custom Page** composition consuming the canonical migrated content/settings.

## Contact migration/content invariant

Contact is not a dedicated runtime Site Node/page type.

Migration must preserve the reviewed Contact/public-profile data required by the target, but normalize it into the current architecture:
- visitor-facing Contact presentation is a reusable structured Contact component that may be composed into a Custom Page such as CV;
- public email/social/global contact identity is owned by canonical General settings;
- private Contact delivery recipient is owned by General/runtime fallback;
- no migration invariant requires a standalone `/contact` Site Node/admin destination;
- SMTP credentials/server mail topology are never migration content.

If historical Contact placement data existed, its content/provenance must be accounted for without recreating the rejected standalone Contact architecture.

## Canonical Site Node projection

Runtime types are defined by `SiteNodeType`: Home, Gallery, Journal, Custom Page and Navigation Node.

Reconciliation requires:
- exactly one **Home** Site Node;
- Home has no slug, is published and navigation-visible;
- every migrated `artwork_categories` row has one matching **Gallery** Site Node;
- no Gallery Site Node references a missing Gallery persistence record;
- Gallery hierarchy obeys current parent rules;
- migrated CV/Vita placement is a **Custom Page** with required `custom_page_settings`;
- migrated Blog placement is a **Journal / Blog** with `journal_settings` and owned Posts;
- migrated Exhibitions placement is a **Journal / Exhibitions** with `journal_settings` and owned Exhibitions;
- Navigation Nodes are structural only and need no legacy counterpart unless explicitly mapped;
- no standalone Contact Site Node is required by the canonical target.

There is no migration invariant requiring singleton runtime types named `vita`, `contact`, `blog` or `exhibitions`.

## Custom Page and Journal integrity

Every Custom Page requires exactly one `CustomPageSetting` record and its ordered structured components must pass current validation.

Every Journal requires a supported `JournalTemplate` and one `JournalSetting`. Blog Posts/Exhibitions must belong to the correct template-specific Journal and not become orphaned.

Missing required settings are not repaired at read time through silent fallback records.

## Fresh import versus protected canonical data

A clean-database source import remains repeatable for migration rehearsal/reconstruction.

Once reviewed data exists in protected Validation or Production state:
- it is canonical application data;
- target schema evolution uses forward Laravel migrations;
- the source importer is not rerun destructively into non-empty canonical tables;
- reconciliation is read-only except for explicitly approved forward migrations/editorial corrections.

A failed forward migration/reconciliation is a release blocker, not permission to erase protected evidence.

## Redirect and public-route reconciliation

Legacy PHP/query URLs are historical evidence, not a blanket compatibility surface.

Migration/cutover checks only redirects with an explicit SEO/external-link/product requirement. New-application slug changes may create redirects under the current redirect policy.

Public route reconciliation verifies the typed route model:
- Home `/`;
- Gallery/Journal/Custom Page `/{section-slug}`;
- Journal entry `/{section-slug}/{entry-slug}`;
- Artwork detail `/artworks/{slug}`;
- Navigation Nodes have no public URL.

No standalone Contact route is required merely because the legacy site had Contact-related content. Unknown/malformed routes fail safely without legacy debug behavior.

## Validation output

`php artisan legacy:validate <manifest>` is a read-only reconciliation tool for the reviewed source snapshot.

A successful report makes visible:
- source/target counts;
- media/checksum integrity;
- provenance coverage;
- ordering differences;
- Site Node projection;
- Vita/CV/Exhibition accounting;
- required route/redirect checks;
- warnings and explicit reviewed exceptions.

No unexplained discrepancy is silently normalized.

## Acceptance boundary

Migration reconciliation is necessary but not sufficient for Production acceptance. Separate gates remain application CI, exact release identity, protected Validation, public/browser comparison, admin acceptance, editorial approval and platform backup/restore/rollback readiness.

## Explicit exclusions

Never migrated as target runtime authorities:
- legacy authentication/users/sessions/password material;
- DB/mail/API credentials or server secrets;
- legacy SQL/table architecture;
- legacy admin/upload/parser/debug implementation;
- workshop/development tooling outside the approved artist-site target.

Secrets, private dumps and authoritative private media must not appear in Git, migration reports or screenshots.