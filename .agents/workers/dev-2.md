# Dev Worker 2 — Account and Profile

**Branch:** `dev`
**Lease:** 24
**State:** General Regional Formats spacing corrected in source; runtime/browser acceptance pending
**Claimed work:** Top-right Account/Settings, per-user Admin formats, Public General regional formats, and directly affected formatter consumers. The shared dialog system and unrelated feature editors retain their owners.

## Current source state
- Top-right menu contains Account, Settings and Logout. Settings groups Language, Dates, Numbers, 12/24-hour Time and named IANA Time zone. General contains the separate Public equivalents; the stored user and public preferences do not influence each other.
- Settings now uses canonical `AdminDialog::edit()` with its existing mounted-action `wire:change` autosave and header X only. All five Settings Selects have no `required()` star; the four Public Regional Formats Selects in General also omit stars. Valid value allowlists remain on Settings, and Public model validation remains unchanged.
- Editing Settings choices saves only on real changes through the existing User and AdminNotifier owners. Other format changes retain the open dialog. Language changes require a full reload of the current Admin rendering, consistent with the existing localization contract.
- The original Account & security dialog remains a committed form for name/email, password and multi-factor security; no automatic credential saves. No new JavaScript hooks, modal overrides or parallel persistence path.
- The prior migration preserved historic per-user number display, added independent User/Public time/zone preferences, and left machine timestamps and future measurement-unit conversion unchanged.
- Human-facing metric counts and Activity timestamps in major Admin surfaces already use the personal formatter. Technical numeric payloads remain invariant.

- General Regional Formats' two-row grid now uses the same metric-aligned half-width gutter as Contact/Legal, with vertical row gaps and responsive single-column stacking. Its section wrapper is back to the shared one-column heading/content structure. This scoped layout change does not modify global controls or autosave behavior.

## Evidence and next
- Source-only commit on `dev`; no local runtime, isolated database migration, browser review or user acceptance was executed in this worker session. The shared Edit Action lifecycle was verified from current source and canonical dialog contract.
- Testing to check per-field autosave, change/no-op notifications, close/reopen, select state and persistence, language change reload, Public independence and migration in an isolated environment. Browser acceptance to confirm no stars, no Save button, X close and stable dialog geometry.
- Out-of-scope findings: specialized Admin native date inputs and some isolated display formatters remain outside this bounded change; do not conflate source edits with visual acceptance.
