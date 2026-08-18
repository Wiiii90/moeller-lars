# Migration invariants

These invariants define what must remain true when legacy content is normalized into the clean target application. They constrain data/reconciliation, not the target schema layout or runtime implementation.

## General rules

- The legacy application/database/media set is migration input only.
- The target PostgreSQL schema is authoritative after cutover; legacy schema/table layout is not retained as a runtime dependency.
- Every in-scope source record/value must map to a documented target value or an explicit reviewed exception.
- Missing, duplicate, contradictory or ambiguous required data fails reconciliation. Runtime fallback behaviour must not conceal migration defects.
- Every imported record retains sufficient non-secret provenance to identify its source/batch.
- Final reconciliation uses the authoritative pre-cutover production snapshot if it is newer than earlier development snapshots.

## Artwork source/category semantics

The reviewed legacy source establishes two different facts that must not be conflated:

1. the landing query directly confirms `paintings`, `drawings` and `prints` tables;
2. the public dispatcher also exposes broader category selectors including `cyanotype`, `bichromate`, `litho`, `photo`/`photos`, `ignis` and `other`.

The migration process must inspect the supplied source snapshot and record actual table/data availability. It must not assume that the three landing tables are the complete content corpus, and it must not invent target content for dispatcher selectors whose source data is unavailable or ambiguous.

- source-to-target category mapping is explicit migration data;
- target categories are generic `ArtworkCategory` records and behave like later artist-created categories;
- concrete category names/slugs are never required by production runtime logic;
- source selector/table naming distinctions are retained as provenance where relevant;
- counts are reconciled per actual source table/category and target category;
- unexplained differences are blocking findings.

## Artwork factual data

For every in-scope source artwork, preserve/reconcile the available factual fields represented by the legacy source, including:

- source identity/provenance;
- original filename/path provenance;
- title;
- date/date text and normalized date semantics;
- material/medium;
- dimensions;
- optional comment/description;
- category mapping;
- approved presentation order.

Normalization may change field/table structure but not silently change factual meaning.

## Media and originals

- canonical originals are retained and remain authoritative;
- generated target derivatives never replace originals or become the only source of truth;
- each imported original records source provenance, target asset identity, byte size, content MIME and SHA-256;
- required target derivatives are independently verifiable by recorded identity/size/checksum/profile;
- thumbnail/derivative reconciliation detects missing and unexpected files as well as mismatched bytes/checksums;
- unsupported, corrupt or missing media is quarantined/reported rather than silently discarded;
- duplicate byte content may be deduplicated only through an explicit rule that preserves every editorial usage relationship;
- target public delivery does not depend on the legacy filesystem path layout.

Published target artwork must satisfy the same canonical media/publication rules as later artist-authored content. Import must not mark content publish-ready while required original, derivative or ALT invariants are unresolved.

## ALT semantics

The legacy templates commonly derive artwork image ALT from the artwork title. Migration preserves meaningful source semantics, but target accessibility metadata is explicit canonical data.

- asset-level ALT and any explicit usage override are reconciled as target editorial values;
- informative public images require valid canonical ALT data;
- decorative use must be explicit;
- title, filename, legacy raw metadata or placeholder text is not used at runtime as a rescue fallback for missing required ALT;
- intentional accessibility corrections are recorded/reviewable rather than appearing as unexplained migration loss.

## Date and ordering semantics

Legacy category queries use date-descending presentation and leave same-date order undefined. The target normalizes presentation into explicit editorial positions.

- preserve the canonical source date/raw date evidence;
- normalize date precision without inventing unavailable month/day information;
- category presentation uses target `artwork.position` after reconciliation;
- the imported baseline position sequence reproduces the approved legacy display order where that order is authoritative;
- same-date ambiguity must be resolved explicitly or remain a blocking migration/editorial exception;
- source ID, target ID, insertion order, slug, timestamp or database order may not be used silently as a tie-breaker;
- later artist reorder may intentionally change chronology; that is editorial state, not migration corruption.

The legacy Home query combines the three confirmed landing tables and chooses the newest date. The target Home pipeline is generic and uses persisted category Home eligibility, but the approved imported snapshot must still reconcile to the expected legacy winner. If target-eligible newest records are ambiguous, the ambiguity must be explicit rather than resolved by a hidden secondary sort.

## Vita, Biography and Exhibitions

The verified legacy Vita source is normalized into **31 accounted source rows** with explicit classifications:

- **2 Biography rows** remain target `cv_entries`;
- **29 Exhibition rows** become first-class target `exhibitions` records.

This classification is authoritative migration data for the reviewed snapshot. Do not guess Exhibition identity from arbitrary prose.

The normalization invariant is:

- all 31 source rows remain accounted for;
- exactly the two classified Biography rows remain legacy-derived CV entries;
- all 29 classified Exhibition rows are represented as Exhibitions;
- historical Exhibition rows are not duplicated in CV;
- title/date/venue/location/body/link semantics and legacy provenance are retained where present;
- the portrait remains associated with the verified biography/public profile provenance;
- public profile/social/contact/legal values required by the target are reconciled independently.

Any later authoritative production-source change before cutover must be reconciled explicitly rather than assuming the 31-row snapshot can never change.

## Fresh target database and repeatability

- clean-snapshot imports into a fresh isolated target database are deterministic and repeatable;
- a failed import cannot silently leave a dataset accepted as complete;
- importer logic is generic and independent of concrete artwork category identities;
- every required source entity has a target entity or explicit exception;
- migration validation can be rerun without requiring the legacy application at runtime;
- current target databases that already contain validated imported data may be transformed through reviewed Laravel migrations when a target normalization changes; do not rerun the legacy source import merely to reproduce a transformation already represented by target migration history.

## Reconciliation report

The retained migration report records enough non-secret evidence to reproduce the acceptance decision, including:

- source snapshot/batch identity;
- source/target counts by content type/category;
- category mapping;
- Biography/Exhibition accounting;
- provenance coverage;
- media original/derivative existence, sizes and checksums;
- required ALT coverage;
- normalized date/field coverage;
- complete category order and same-date findings;
- Home winner;
- portrait/profile/text preservation;
- missing/unexpected/quarantined assets;
- explicit exceptions with review outcome.

There is **no blanket legacy-URL redirect reconciliation invariant**. Legacy PHP/query URLs are not target compatibility requirements. Redirect validation applies only where an explicit new-application redirect or separately approved SEO/external-link redirect exists.

## Validation and acceptance

Migration acceptance fails while any unexplained required difference remains.

A difference may be accepted only when it is:

- understood;
- explicitly recorded;
- assigned a reason/decision;
- compatible with the current target contracts;
- reviewed at the appropriate migration/editorial gate.

A page rendering successfully is not evidence that its migration is lossless.

## Explicit exclusions

Never migrate:

- legacy authentication/admin users/passwords/sessions/authorization rules;
- database/mail/API/signing credentials or tokens;
- unsafe SQL/parser/upload/auth implementation details;
- debug settings or host/server configuration as application data;
- legacy workshop content into the artist-site target unless a separate future scope decision explicitly adds it.

Secrets, production dumps and private media archives must never appear in migration reports committed to Git, fixtures, screenshots, issue bodies or repository documentation.
