# Dev Worker 6 — Home Hero

- **Branch:** `dev`
- **Lease:** 16; this chat is the only active holder.
- **Baseline:** `14012313e0f63111b339a7b73ddeff30bf5f3d88`; feature source is contained in the accompanying commit, exact published HEAD must be checked before any follow-up write.
- **Current ownership:** Pages → Home Hero sources, Stage, Settings, public candidate resolver and focused Dummy Gallery editor. No tests, CI, server, deployment or other worker feature ownership.

## Current source change — review/browser acceptance still pending

- Source modes: Automatic = regular enabled Galleries; Semi-Automatic = regular plus enabled Home-only Dummy Galleries; Manual = Dummy Galleries exclusively. A Dummy Gallery has a stable negative ID, name, enabled state and Artwork/Storage member references, with enabled flags and optional title/year/month/day for direct Storage. This is stored in the canonical Home Artwork configuration JSON; public Gallery entities are not invented. Existing Manual/Semi-Automatic stored members are imported into one initial Dummy Gallery on first canonical save. The old manual member parser remains only as a persisted-data reader.
- Ranking: Existing four oldest/newest Artwork date or primary Media added rules, plus Gallery Order versions with saved source priority. Inside each Gallery the chosen date/media rule applies; otherwise all candidates are ranked globally, no implicit Manual priority or stored manual Artwork sequence. Missing Artwork dates on loose media are not fabricated. Source priority is stored per Source profile; moving sources is not Artwork reordering.
- Candidates: editable Total N or Per Gallery N, including N=1; global 50-candidate limit and Stage warning. Display: Top Only, Random, Sequential unchanged in meaning; chance normalization shared by all modes. Stage has precisely one row of four controls: Source / Ranking / Candidates (scope + amount) / Display. Settings follow the same controls without added persistent helper prose.
- Home source table: real regular/Dummy Gallery rows, expanded candidates, name link and Edit/Enable/Delete actions for Dummy, Gallery priority Move Up/Down, Add Dummy Gallery footer, existing pagination/search. Source selection identity is scoped by Gallery so choosing the same Artwork in multiple sources cannot trigger unrelated bulk actions.
- Add Dummy Gallery Create dialog stages Name, existing Artwork references, eligible Storage media and inline metadata/member operations; one Commit persists. Edit dialog persists name on blur and member actions immediately. The shared Create dialog has circled-check Commit, Clear and native X; Edit never gains those Create actions. Canonical Admin table, media picker, audited Home mutations, notifications and media retention are used. Legacy Edit Group UI and member drag/move code are removed.
- Related source files: `HomeHeroConfigurationService`, `HomeHeroResolver`, `HomeHeroCandidate`, `PublicArtworkQuery`, `MediaReferenceQuery`, `HomePresentation`, `HomeSettingsDialog`, `HomeDummyGalleryDialog`, `AdminDialog`, Home view/partials, `home.css` and canonical `responsive.css`. Obsolete `HomeHeroGroupDialog` and its Blade table deleted.

## Verification and limits

- **Verified:** Connected GitHub source inspection, strict single-owner redesign and guarded Git-object commit construction. Exact HEAD/ref update and changed-file diff must be confirmed in next handoff.
- **Not executed:** PHP syntax checks, isolated database tests, local Preview, browser, migration rehearsal, Continuous Integration, Validation and Production. Do not describe the current layout or behavior as runtime-proven or user-accepted.
- **Required browser/source acceptance:** staged Create add/clear/commit, edit auto-save and invalid/duplicate candidate errors; imported historical Manual members; Storage media retention; real public Home with newly published/dated Artworks; all Ranking × Total/Per-Gallery × Source × Display combinations, duplicate Artwork identities across Dummy groups, Gallery Order moving across pagination, random weights, 50-cap; four Stage controls aligned at wide/narrow widths; source row/table/editor actions and header Commit/Clear/X order. Testing owns later regression suite changes and existing test reconciliation.
- **Out-of-scope findings:** none known; Balanced deliberately not implemented.
- **Next:** Validate on current local Preview and correct concrete in-scope failures. Keep this record concise and current.
