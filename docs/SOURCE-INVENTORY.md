# Source inventory

## larsmoeller: current-site reference

- Legacy PHP/MySQL public site with artwork categories, landing artwork, Vita/CV text, contact page, and an admin area.
- Connected to a server-hosted Git remote named `live`; the local clone has no active hook, so any automated deployment logic is expected to live on the server.
- The Apache rules in the captured legacy source direct traffic to HTTP; this is historical source behaviour, not a target requirement.
- The source contains security-sensitive legacy patterns. Its credentials and history must stay out of public GitHub until they have been rotated and rewritten.

### Reviewed Vita source contract

- Source: legacy `txt/vita.txt` plus the public Vita portrait.
- Reviewed textual inventory: exactly **31** source rows.
- Approved canonical partition: exactly **2 Biography** rows in `cv_entries` and **29 Exhibitions** in `exhibitions`.
- The 29 Exhibition rows are moved out of the CV target; they must not remain duplicated in both canonical tables.
- The first Biography row carries the portrait relationship. The migrated portrait retains source name/path, byte-size and SHA-256 provenance and is reconciled by `legacy:validate`.
- The repository stores the reviewed row mapping/import logic and validators, not the private production/Validation database or original source-media corpus.

## glassygallery: useful ideas, not a base

- Separate frontend, admin frontend, API, migrations, media records, user records, and CI/CD documentation.
- Useful inspiration: media metadata, structured pages, role vocabulary, database migrations, Docker/CI awareness.
- Not suitable as the base because the UI is a general website customizer rather than a Lars Möller editorial tool; its analytics screen is a placeholder and several API mutations lack consistent authorization safeguards.

## moeller-lars: target

- Clean Laravel 13/PHP 8.3/PostgreSQL target implementation, tests, migration/reconciliation tooling, and target documentation live here.
- Production and the platform-owned Validation runtime remain separate in platform topology and persistence. The application repository intentionally does not encode host `/srv` paths or deployment topology.
- Human analytics are sourced from self-hosted Matomo Community/Core. The application may consume aggregate Reporting API data but does not persist duplicate raw-human analytics or raw visitor identifiers.
- No legacy source, data dump, production image archive, server-side Matomo token, mail credential, or production configuration is copied here.
