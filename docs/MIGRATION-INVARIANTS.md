# Migration invariants

These invariants define what must remain true when legacy content is moved into the fresh target database. They are data and rendering requirements, not a requirement to preserve the legacy schema or code.

## Legacy artwork model

- The legacy artwork tables/categories are `paintings`, `drawings`, and `prints`.
- Each artwork record contains the factual fields `id`, `filename`, `title`, `date`, `material`, `dimension`, and optional `comment`.
- The target may normalize categories and fields differently, but every source value must map to a documented target field or an explicit, reviewed exception.
- The displayed year is derived from `date`; material, dimensions, title, and comment are rendered as content when present.
- Every source record and every public source-category mapping is explicit and
  reviewed; this task does not invent mappings for dispatcher-only categories.
- Reconciliation compares total artwork count, each source-table/category
  count, and each target-category count. Any unexplained difference is a
  blocking migration exception, not an accepted normalization.

## Media and originals

- A source artwork references its original filename in its category media directory and a thumbnail with the same logical filename in the category thumbnail directory.
- Original media is retained; generated derivatives never replace or become the only copy of an original.
- Migration records the source path, target asset identity, byte size, media type, and cryptographic checksum for every original and required derivative.
- Original source filename and path provenance are retained even when target
  storage keys are generated. Originals remain authoritative.
- Thumbnail/derivative reconciliation detects both missing and unexpected
  files, in addition to checksum/byte-size mismatches.
- Duplicate content is detected by checksum and handled by an explicit deduplication rule that preserves every artwork-to-asset relationship.
- Unsupported, corrupt, or missing files are quarantined and reported; they are never silently discarded.

## ALT text semantics

- Capture legacy image ALT/title semantics wherever present; the reviewed
  artwork templates use the artwork title as image ALT.
- Target ALT preserves meaningful legacy artwork-title semantics while allowing
  a safe accessibility correction when the source value is empty, misleading,
  or unsafe. Any unexplained loss or change is a reconciliation finding.

## Date and ordering semantics

- Category ordering is date descending.
- The landing/latest-work query combines all three categories and selects the newest date descending.
- The stored date controls chronology and displayed year; the exact visitor-visible day is not assumed to be part of the public display.
- Equal-date ordering is undefined by the legacy queries. Migration reconciliation must establish an explicit target `position` from the approved and reconciled legacy display or export ordering wherever authoritative ordering can be established, while retaining the original date unchanged. The process must never silently substitute source ID, target ID, insertion order, or database order. If authoritative same-date ordering cannot be established, record an explicit migration/editorial exception for review. Runtime/public ordering uses the resulting explicit position semantics.
- Ordering reconciliation must compare the approved legacy result set with the target result set, including equal-date cases and category boundaries.

## CV/Vita source

- The legacy Vita/CV source is the text file `txt/vita.txt`, rendered with the legacy page's formatting interpretation and alongside the artist portrait.
- The reviewed source inventory contains exactly **31** Vita source rows. The approved canonical mapping is exactly **2 Biography rows** in `cv_entries` and **29 Exhibition rows** in `exhibitions`; this is a partition of the 31 source rows, not two independent imports.
- Every canonical Biography/Exhibition row retains `legacy_source=legacy-public-vita`, the reviewed migration batch identity, source identity and intended order so the validator can account for every source row exactly once.
- The artist portrait is part of the same reviewed Vita source: the first Biography row references the migrated portrait asset and that asset retains legacy source path/name, byte-size and SHA-256 provenance.
- Existing protected Validation data is transformed forward by the application migration that splits legacy exhibition rows out of `cv_entries`. Re-running the destructive source import against non-empty Validation/Production tables is not an accepted reconciliation strategy.
- Reconciliation must fail if Biography/Exhibition counts differ from 2/29, if a Vita row exists in both canonical targets, if portrait provenance/integrity differs, or if any of the 31 source rows is unaccounted for.
- The source text, section meaning, links, and intended order must remain lossless and are still subject to artist/browser acceptance even after database reconciliation is green.

## Canonical SiteSection projection

- `SiteSection` is the canonical public-navigation/availability projection introduced during the rebuild; its migration must be reconciled independently from visual browser acceptance.
- Exactly one singleton section must exist for each of `home`, `vita`, `blog`, and `exhibitions`.
- Every `artwork_categories` row maps to exactly one `gallery` SiteSection and no Gallery SiteSection may reference a missing category.
- Gallery section parentage must match the artwork-category hierarchy exactly. A child category must point at the SiteSection belonging to its category parent; a root category must have no SiteSection parent.
- `php artisan legacy:validate <manifest>` performs this read-only structural check together with the legacy content/media reconciliation. A failure is a migration blocker and must not be repaired by deleting Validation evidence.

## Fresh target database and losslessness

- The target database is newly designed and fresh; the legacy schema is not preserved and is not required at runtime.
- Imports are repeatable and idempotent against a clean target database. A failed import can be resumed or rolled back without partial silent loss.
- Every required source artwork/content record has a target record or a documented exception with reason and owner.
- Reconciliation compares source/target counts by category and content type, original-media checksums and byte sizes, stable ordering, required-field coverage, and representative rendered text/HTML.
- Cutover reconciliation verifies every approved legacy-to-canonical redirect
  listed in `PUBLIC-IMPLEMENTATION-CONTRACT.md`, including status code and
  target behaviour. Broken or non-public legacy routes (for example the
  missing `links` and Contact handler paths) remain explicit exclusions rather
  than being silently redirected.
- A migration report is retained with import version, source snapshot identity, counts, checksum results, warnings, exceptions, and sign-off.

## Validation and acceptance

- The migration report makes unexplained count, mapping, media, ALT, text,
  ordering, SiteSection, and redirect differences visible.
- Validation fails migration acceptance until each difference is resolved or
  recorded as an explicit exception with reason, owner, and review decision;
  differences are never silently normalized.
- Database/media reconciliation is necessary but not sufficient for production acceptance: release-candidate browser comparison and explicit artist approval remain separate gates.

## Explicit exclusions

- Legacy authentication, admin users, sessions, password material, and authorization rules are not migrated.
- Legacy database credentials, mail credentials, API tokens, signing secrets, and server configuration are not migrated.
- Legacy SQL/table structure, unsafe formatting/parser code, debug settings, and deployment hooks are not target dependencies.
- Secrets must never appear in the migration report, fixtures, screenshots, commits, or this documentation.
