# Migration invariants

These invariants define what must remain true when legacy content is moved into the fresh target database. They are data and rendering requirements, not a requirement to preserve the legacy schema or code.

## Legacy artwork model

- The legacy artwork tables/categories are `paintings`, `drawings`, and `prints`.
- Each artwork record contains the factual fields `id`, `filename`, `title`, `date`, `material`, `dimension`, and optional `comment`.
- The target may normalize categories and fields differently, but every source value must map to a documented target field or an explicit, reviewed exception.
- The displayed year is derived from `date`; material, dimensions, title, and comment are rendered as content when present.

## Media and originals

- A source artwork references its original filename in its category media directory and a thumbnail with the same logical filename in the category thumbnail directory.
- Original media is retained; generated derivatives never replace or become the only copy of an original.
- Migration records the source path, target asset identity, byte size, media type, and cryptographic checksum for every original and required derivative.
- Duplicate content is detected by checksum and handled by an explicit deduplication rule that preserves every artwork-to-asset relationship.
- Unsupported, corrupt, or missing files are quarantined and reported; they are never silently discarded.

## Date and ordering semantics

- Category ordering is date descending.
- The landing/latest-work query combines all three categories and selects the newest date descending.
- The stored date controls chronology and displayed year; the exact visitor-visible day is not assumed to be part of the public display.
- Equal-date ordering is undefined by the legacy queries. Migration reconciliation must establish an explicit target `position` from the approved and reconciled legacy display or export ordering wherever authoritative ordering can be established, while retaining the original date unchanged. The process must never silently substitute source ID, target ID, insertion order, or database order. If authoritative same-date ordering cannot be established, record an explicit migration/editorial exception for review. Runtime/public ordering uses the resulting explicit position semantics.
- Ordering reconciliation must compare the approved legacy result set with the target result set, including equal-date cases and category boundaries.

## CV/Vita source

- The legacy Vita/CV source is the text file `txt/vita.txt`, rendered with the legacy page's formatting interpretation and alongside the artist portrait.
- The target may transform this into structured CV entries and safe rich text, but the source text, section meaning, links, and intended order must be preserved losslessly and reviewed by Lars.
- Exhibitions are not inferred from the CV source unless a separate, explicit editorial mapping is approved.

## Fresh target database and losslessness

- The target database is newly designed and fresh; the legacy schema is not preserved and is not required at runtime.
- Imports are repeatable and idempotent against a clean target database. A failed import can be resumed or rolled back without partial silent loss.
- Every required source artwork/content record has a target record or a documented exception with reason and owner.
- Reconciliation compares source/target counts by category and content type, original-media checksums and byte sizes, stable ordering, required-field coverage, and representative rendered text/HTML.
- A migration report is retained with import version, source snapshot identity, counts, checksum results, warnings, exceptions, and sign-off.

## Explicit exclusions

- Legacy authentication, admin users, sessions, password material, and authorization rules are not migrated.
- Legacy database credentials, mail credentials, API tokens, signing secrets, and server configuration are not migrated.
- Legacy SQL/table structure, unsafe formatting/parser code, debug settings, and deployment hooks are not target dependencies.
- Secrets must never appear in the migration report, fixtures, screenshots, commits, or this documentation.
