# Dev Worker 1 — current state

**Branch:** `dev` · **Lease:** 9 · **Status:** Active; explicitly replacing the archived Dev Worker 1 chat on 2026-11-03. Replacement retains the existing claimed area but does not itself authorize new feature work.

**Scope:** Exhibition Add/Edit, canonical partial dates and conversion, address/map controls, autosave and the accepted Public Exhibitions presentation. Media integrity belongs to Dev Worker 2; Gallery/Artwork belongs to Dev Worker 4.

## Accepted behavior and current source

- Dates: `starts_on` and `ends_on` independently preserve year, month or exact-day precision; an exact start with no end is one day. `ExhibitionDate` is the canonical parser/range/temporal owner. Shared Add/Edit uses `AdminPartialDateControl`, with the Starts help icon explaining one-day entry. The active `date_text` field is retired. Immutable historical checkpoints are not rewritten; the existing publication/restore owner must project historical dates when restoring.
- Migration: `database/migrations/2026_11_03_180000_canonicalize_exhibition_partial_dates.php` guards conversion, inventories Working/Committed/checkpoints, rejects ambiguous or invalid historical values, locks affected data in one transaction and replaces the old published-content constraint in both live schemas. Do not restore parallel runtime fields or synthesize dates from imprecise values.
- Exhibition Add/Edit: Organizer/Venue share a row. Street address, City, Postal code and Country use one four-column row at all breakpoints (`JournalEntryEditorSchema::venueFields()`, `Grid::columns(4)`), with Map enabled directly beneath. The former Admin map preview and orphaned styles were removed. This is **source-confirmed only**; actual full-width and no-overlap geometry still needs browser checks at wide and narrow dialog widths.
- Public Exhibitions: light unframed catalog on centrally configurable public content background; no feature-dependent decorative spacing. Without a feature image, the entire expanded body starts at its left edge; with one, retain the responsive cover float and natural text flow. Closed entries have no separators; opened adjacent entries share one line with no exterior first/last divider. Keep the video teaser/details, native feature-video controls, gallery, viewer and publication behavior. Do not reintroduce the rejected image stage, numbered rows, red decorations, cover-frame shadows or unrequested visual redesign.
- Public maps: when the optional map fails, it must not display an error banner or leave a broken dialog; remove the unusable trigger for that visit while leaving other Exhibition content intact. No automatic new provider. Admin geocoding failure preserves saved address/coordinates and existing optimistic concurrency safeguards and feedback. The intermittent editor conflict message during map/address autosave remains a specific browser verification concern.
- A previously added Admin dialog save/close interceptor was reverted after a user-confirmed regression. The accepted narrow Filament close patch remains; do not reintroduce a second listener or lock without a new decision and evidence. Shared Journal mosaic column tops are aligned.

## Verified evidence

- **Local schema migration, 2026-11-03 (user-provided command output):** migration reported `Nothing to migrate`; independent read-only database inspection confirmed `public.exhibitions.starts_on`, `public.exhibitions.ends_on`, `committed.exhibitions.starts_on`, `committed.exhibitions.ends_on` as `character varying(10)`, with no `date_text` in either live schema. `PublicationSchemaGuard::assertParity(['exhibitions'])` returned `PARITY_OK`. Local Working/Committed **schema parity is verified**, not the accuracy of each converted date, checkpoint restoration or browser behavior. No repeat local migration is needed.
- The previous local preflight covered 29 Working, 29 Committed and 116 historical checkpoint records (174 total) with no recognized conversion ambiguity, including year ranges, month-year and the two consecutive dates `28.09.2018 & 29.09.2018`. This is preflight evidence, not post-migration data verification, and says nothing about Validation's distinct artist data.
- Media-thumbnail cutover was separately confirmed on Validation on 2026-10-30 using user-reported platform output. Do not run it again. It is not part of this worker's current work.
- Source changes are not browser acceptance. No browser review, restore qualification, Continuous Integration run, new Validation migration or production deployment is claimed in this takeover.

## Outstanding gates and handoffs

1. In a current local preview, inspect date values before/after save/reopen, exact/month/year precision and single-day semantics, Starts help, address row at narrow and wide widths, map toggle and failed-geocoder concurrency behavior.
2. Check actual converted rows, Public date rendering and a safe, isolated historical checkpoint restore/projection without overwriting current Working artist data. Check Public no-cover left alignment, with-cover flow, video/map failure, galleries and opened-only separators. Do not infer these results from code inspection.
3. Any future Validation date migration requires **its own read-only inventory**, a reviewed short-lived consistent backup/recovery plan, and explicit user approval before deployment. Local conversion success alone is insufficient.
4. Testing owns date, publication/restore, map, dialog and responsive regression cases on `testing`. Docs owns updating `docs/content/exhibitions.md` for canonical partial dates and `docs/public/site.md` for no-cover left alignment and mosaic alignment; do not update general docs as routine Dev work.
5. Local operational policy is already durable in `AGENTS.md` and the Dev role method: `scripts/local-preview.ps1` synchronizes its branch, so no preceding standalone Git pull; use only a verified running current-code app container for database-only commands; no implicit Docker image build or daemon-wide prune; no unapproved workflow, server or production action.

## Next

Await the user's next explicitly scoped Dev Worker 1 instruction. Before each write, read the fresh `dev` head and confirm this branch-local lease. The replacement itself changes no application code.
