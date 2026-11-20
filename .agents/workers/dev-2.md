# Dev Worker 2 — Account and Profile

**Branch:** `dev`
**Lease:** 24
**State:** Source-implemented: independent General/Public and per-user Settings/Admin regional formats; runtime migration and browser acceptance pending.
**Claimed work:** Top-right Account and Settings actions, their persisted user preferences, shared presentation formatters and Public General regional controls. Shared general Stage, navigation/header/dialog infrastructure and feature-editor ownership stay with their active workers.

## Current scope and decisions
- The menu's old Language action was replaced by Settings. Language is inside Settings with separate Dates, Numbers, 12/24-hour Time and named Time zone selectors; Account retains name, email, password and the existing multi-factor-authentication management.
- General's existing public dates and numbers are now joined by public time and named time zone. Public settings remain in separate editorial/committed records; user settings remain on `users`. Named IANA zones and their seasonal abbreviations disambiguate CST/CEST and apply daylight saving automatically to timestamp presentation. Date-only values do not shift.
- Existing per-user region preserves its date preference. The forward migration copies historical numeric separators into a dedicated user number field; time defaults retain UTC/24-hour legacy output. Publication and data timestamps stay canonical. Future unit/measurement preferences are **out of scope**.
- Shared Admin date-time picker display and its timezone follow the user; human-facing Dashboard, Analytics, Activity, Gallery, Custom Page and Storage metric counters and Activity timestamp labels now use the user display preferences. Technical numeric values (e.g. SVG opacity and machine storage values) retain invariant formatting.

## Evidence and next
- Source changes committed on `dev`; no local application checkout/runtime, isolated schema migration execution, full testing run, browser screenshot/acceptance, staging deployment, or production change has been performed here. A standalone PHP check confirmed that named time zones supply both CET and CEST, **not** that project tests pass.
- Review focused final diff and latest `dev` head. Testing owns isolated migration, format, user-isolation, 12/24-hour, daylight-saving and account-security regression coverage. User browser review should verify top-right menu, Account vs Settings, general public vs admin display and persistence/reload.
- Native date/time inputs remain browser/operating-system formatted. Filament's custom `displayFormat()` takes effect for JavaScript-mode pickers; globally switching every native picker would remove direct date typing and alter accepted editor interactions. Do not claim this input-control parity until explicit cross-editor browser acceptance or a deliberate user decision.
- Remaining direct formats in unrelated specialized UI consumers are unverified; coordinate with owning workers rather than rewriting their controllers without review.
