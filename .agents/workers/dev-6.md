# Dev Worker 6 — Home presentation

- **Branch:** `dev`
- **Lease:** 16 (new chat explicitly replacing lease 14; old holder is stale)
- **Takeover baseline:** `a8654ca4c5bc657c3dfc41ca48e8d8695399080b` (read current source again before each code write)
- **Current assigned scope:** Pages → Home Hero Artwork sourcing, candidate management/rotation, Stage and Settings integration, public hero display; previously owned Home Under Construction and Custom editor parity. Other Dev workers own Dashboard, General Appearance, Gallery, Pages and dialog-wide redesign.
- **Immediate task:** Verify and complete the just-committed Global / One per Gallery selection feature described in the user's handoff. Do not implement speculative further selection strategies before a separate user decision.

## Current implemented contract (handoff; source and browser acceptance to be checked)

- `HomeHeroConfigurationService` owns persisted `artwork.source_profiles`. Automatic, Semi-Automatic and Manual keep independent profiles; `selection_mode` belongs to Automatic/Semi-Automatic only. `global` is the backward-compatible default; `one_per_gallery` is the new option. Saved Global `group_size` survives a switch to per-Gallery and back. Manual mode has no Selection control.
- `PublicArtworkQuery` and `ArtworkSort` jointly own eligible public Artwork ranking: one eligible candidate per enabled Gallery using PostgreSQL window ranking, preserving the four existing sort rules, exclusion before ranking and global cross-Gallery order. No new Gallery preference flag or duplicate query owner.
- `HomeHeroResolver` remains the only public candidate composition owner. Semi-Automatic reserves enabled Manual members and excludes their identities from automatic Gallery selection; existing 50-item ceiling and stored manual ordering/weights remain. The Stage warns when eligible Galleries plus effective Manual members exceed capacity instead of silently claiming full per-Gallery coverage.
- Home Stage quick settings and the canonical Settings dialog both expose Selection for Automatic and Semi-Automatic. Per-Gallery Stage size is derived/read-only; Settings hides the Global numeric size control in that mode. Existing Random, Sequential and Top Only strategies and rotation-anchor reset still apply.
- The preceding session reported source edits committed through GitHub; **no runtime, tests, browser review or user acceptance of the newest candidate** is established. Confirm actual commit and code, not just this record.

## Adjacent Home contracts to preserve

- Source profiles are independent across Automatic, Semi-Automatic and Manual. Manual Sourced Artworks is its own candidate group with enabled/disabled membership; Automatic and Semi-Automatic permit Gallery and individual automatic Artwork exclusions. Reordering, enabled flags and percentages are authoritative only through the existing Home service, audit and notification owner.
- The Home Stage has a visual/controls split, canonical compact quick settings, status labels `Preview:` / `Next Rotation:`, a fixed candidate rail and shared icon/percentage controls. The Edit Group modal shows the entire actually sourced mixed Semi-Automatic sequence, and supports immediate Move Up/Down/drag for manual and automatic candidates without a secondary Save action. Automatic candidates cannot be removed from Storage by that modal.
- Home presentation options use the established Gallery/public semantic components but have Home-owned persistence. Under Construction and Custom use canonical Custom Page forms/editors.
- Prior source changes also touched `resources/css/admin/task-surfaces.css` (shared inline percentage), `resources/css/admin/home.css` (Stage tracks), central table/modal insets and actions. Retain accepted shared geometry and do not introduce parallel controls or layout patches.
- There is a previously described forward data migration moving legacy Gallery/Artwork Home exclusion state into Home source profiles. Data-first migration order is required before public runtime refresh. Do not claim migrated or deployed.

## Verification and handoff

- **Source review:** verify final selection commit and direct consumers, migration/serialization boundaries, source rules and list capacity, profile save/restore and Stage/Settings parity.
- **Browser acceptance still required:** Global unchanged; each of four per-Gallery rules, unpublished/empty/excluded Gallery and fallback, disabled automatic candidates, Manual overlap in Semi-Automatic, 50-cap warning, saved Global Size restoration, Random/Sequential/Top Only preview, Settings/Stage persistence and public Home, wide/narrow layouts.
- **Adjacent pending visual review:** Random Chance 0.00/20.00/100.00 not clipped; current Edit Group single-line rows, symmetric modal inset and labeled-vs-icon actions; Home Stage status and responsive layout; gallery table selection/bulk actions; video/month rotation; Custom/Under Construction parity.
- **Testing owner:** Dev must not edit tests or general documentation. No Continuous Integration, Validation/Production deployment, main promotion or browser acceptance may be claimed without actual authorized execution.
- **Next:** finish source-level review of the new Selection option, correct only genuine in-scope defects if found, and provide the exact checked commit, changed files and unverified browser checks.
