# Migration plan

Status snapshot: **2026-08-18**

This plan describes the controlled move from the legacy Lars Möller site into the clean `moeller-lars` target. The importer/schema foundation already exists; remaining work is final source reconciliation, release validation and cutover. GitHub Issues remain authoritative for acceptance state.

## Ownership boundary

`moeller-lars` owns:

- target PostgreSQL schema/migrations;
- legacy importer and mapping payloads;
- content/media reconciliation and validation commands;
- canonical application media model;
- public/admin application behaviour;
- application persistence/migration/rollback requirements.

`Wiiii90/server-platform` owns:

- production and temporary-validation runtime placement;
- database/container/network topology;
- production secrets;
- persistent host paths;
- recurring backup/restore automation;
- deployment, cutover and rollback orchestration.

The legacy application/data is read-only migration input. Production credentials, database dumps and private media archives never enter this repository.

## Source authority

Development/reconciliation may use protected local snapshots, but the final pre-cutover migration source is the most current authoritative legacy production dataset.

Before final import:

1. freeze/export the current in-scope legacy database/content state;
2. capture the matching authoritative media set;
3. identify the source snapshot/batch without exposing secret values;
4. ensure recovery material exists through the platform backup/cutover process;
5. run the same importer/reconciliation path already exercised against development snapshots.

Do not modernize the running legacy application in place merely to make migration easier.

## In-scope content

- all reviewed in-scope artwork records/categories actually present in the authoritative source snapshot;
- artwork originals and required migration/public derivative evidence;
- biography/Vita source rows and portrait;
- explicitly classified historical Exhibition rows;
- public profile/contact/social/legal values required by the target;
- factual metadata/order/provenance required to reproduce the approved public baseline.

The legacy `/workshop` subtree and `larsMoellerWorkshop` database are outside the rebuilt artist-site migration target. They remain part of the legacy rollback/retirement boundary until `server-platform` explicitly retires the old runtime.

Legacy authentication/users/passwords/sessions, credentials, mail secrets and unsafe application configuration are excluded.

## Target normalization

The target does not preserve the legacy schema/table-per-category architecture.

### Artworks and categories

Legacy category/dispatcher evidence is mapped explicitly into ordinary target `ArtworkCategory` records. The importer is generic: category names/slugs are mapping data rather than application constants.

The three landing-query tables (`paintings`, `drawings`, `prints`) are confirmed source facts, but they are not assumed to be the complete set of content available in a particular database snapshot. Broader dispatcher selectors are reconciled against actual source-table/data availability. Missing or ambiguous source categories become explicit migration exceptions rather than invented empty target categories.

### CV and Exhibitions

The verified normalized Vita source contains **31 accounted source rows**:

- **2 Biography rows** remain target `cv_entries`;
- **29 explicitly classified Exhibition rows** become first-class `exhibitions` records.

This split is an explicit normalization decision based on source classification, not a heuristic that guesses exhibitions from arbitrary prose. Historical Exhibition rows must not remain duplicated in CV after normalization. Portrait/profile provenance is retained.

### Media

Canonical target originals are authoritative immutable assets. Generated variants are rebuildable and never replace originals. Source filename/path, byte size/checksum and target asset identity remain reconcilable without exposing the legacy filesystem as a runtime public interface.

## Import execution

A migration run must be explicit, repeatable and fail closed on unresolved required data.

1. Create/use an isolated fresh target PostgreSQL database.
2. Apply the target Laravel migrations.
3. Supply the reviewed external legacy snapshot/manifest to the importer; do not commit it.
4. Import/map categories and artworks generically.
5. Ingest canonical originals through the application media boundary and materialize required public derivatives/ALT data before content is considered publish-ready.
6. Import/normalize Vita biography and Exhibition data with provenance.
7. Reconcile public profile/settings values.
8. Run the migration validation report.
9. Resolve every blocking exception before accepting the dataset.

Partial failure must not silently produce an accepted half-import. Unsupported/corrupt/missing media and ambiguous required ordering/data remain explicit findings.

## Reconciliation

The validation report covers at minimum:

- source and target record counts by content/category;
- explicit source-to-target category mapping;
- all 31 verified Vita rows accounted for as Biography vs Exhibition targets;
- source/target provenance identifiers;
- canonical original filenames/paths, byte sizes and SHA-256 values;
- required derivative availability/integrity;
- required ALT data;
- factual artwork metadata and normalized dates;
- complete curated category order, including same-date groups;
- approved Home winner for the source snapshot;
- portrait/profile/content preservation;
- missing, unexpected, corrupt or quarantined assets;
- explicit reviewed exceptions.

A difference is never accepted merely because the page still renders.

## Current application phase

The clean target schema, importer/reconciliation tooling, secure media boundary, public/admin domains and release-image contract are already present in the repository. Migration work is therefore no longer a speculative “build vertical slices” sequence.

The remaining migration-related application gates are primarily:

- #31 — final CV/Exhibition normalization/reconciliation acceptance;
- #34 — release-candidate public regression comparison;
- #38 — persistence restore/rollback validation;
- #39/#40 — production readiness and editorial acceptance;
- #41/#42 — cutover/stabilization application validation.

Other open public/admin issues may still block release acceptance even if the imported data itself reconciles.

## Temporary release validation

Before production cutover, `server-platform` provides an isolated temporary HTTPS environment using an exact immutable application image.

Validation includes:

- expected application release identity;
- migration status and database connectivity;
- imported/reconciled target data;
- controlled media integrity/delivery;
- public route/content/visual regression;
- artist admin smoke/acceptance flows;
- Contact delivery behaviour;
- Matomo reporting/tracking configuration as selected for validation;
- graceful analytics failure behaviour;
- persistence restore/rollback expectations.

The temporary environment is not a permanent staging environment.

## Cutover sequence

Final cutover occurs only after both application and platform readiness gates are green:

1. freeze/capture the authoritative final legacy content/media source;
2. ensure a platform recoverable state and rollback boundary;
3. run/validate the final target import or required target migration against the approved release process;
4. run reconciliation until there are no unresolved blocking differences;
5. validate the exact immutable application release in the temporary HTTPS environment;
6. complete public/admin/security/analytics/persistence acceptance;
7. obtain editorial approval;
8. let `server-platform` perform the production traffic switch;
9. run application cutover smoke validation;
10. retain legacy rollback capability through the stabilization window;
11. retire legacy runtime only through the later platform retirement gate.

## Acceptance checklist

- every in-scope source record is accounted for or has an explicit reviewed exception;
- every required canonical original is present and reconciled;
- generated derivatives do not replace originals or silently mask missing target data;
- target category/runtime behaviour remains generic and independent of source category names;
- Biography and Exhibition source rows are normalized without duplication or loss;
- ordering/Home selection is deterministic because required editorial/migration invariants are explicit, not because of hidden fallback sorts;
- the target can run without the legacy schema/filesystem/application;
- migration validation is reproducible and produces actionable pass/fail output;
- production data/secrets are absent from Git and public evidence;
- temporary release validation, backup/restore, rollback, public regression and editorial acceptance are complete before traffic switches.
