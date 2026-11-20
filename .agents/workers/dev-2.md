# Dev Worker 2 — Account and Profile

**Branch:** `dev`
**Lease:** 24
**State:** Account/Settings menu and public/Admin regional preferences source-implemented; runtime, migration and browser acceptance pending.
**Claimed work:** Account security, per-user Admin Settings (language, date, number, time and time zone), matching independent Public formats in General. Shared General Stage, Admin header/dialog infrastructure and feature editors remain owned elsewhere.

## Implemented source scope
- Top-right menu's standalone Language action replaced by Settings; Account retains name, email, password and app multi-factor authentication management.
- Settings has separate Language, Dates, Numbers, Time (12/24-hour) and Time zone (named IANA zones). User preferences are stored separately from public General; changing a preference reloads the Admin presentation.
- Public General now has Date, Number, Time and Time zone controls stored on both editorial/public and committed/public settings. Public date-time conversion is separated from authenticated user preferences, and daylight saving is derived from the named zone.
- A forward migration adds preferences with defaults matching historical time output (UTC/24-hour) and backfills per-user number formats from the former combined admin region. Existing date preferences remain unchanged.
- Central Admin date/date-time picker presentation uses the current user preference; downstream number/date consumers are to be reviewed without changing machine-readable values.
- No unit-system conversion or new tests were implemented.

## Verification and next
- Source-only changes committed on `dev`; no isolated migration execution, browser review, full suite or deployment has been performed.
- Review downstream date/time and human-facing number consumers, fix any remaining direct formatting paths in scope, and verify the exact final diff after concurrent `dev` commits.
- Testing owns migration/behavior tests; browser acceptance must confirm the menu position, settings persistence, time-zone transitions and independent Public/Admin rendering.
