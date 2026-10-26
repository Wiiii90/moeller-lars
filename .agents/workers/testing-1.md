# Testing Worker 1 — active assignment

- **Branch:** `testing`
- **Lease token:** 1
- **State:** active
- **User assignment:** Integrate current `dev` changes into `testing` and reconcile the canonical test suite; keep `dev` untouched, remove obsolete tests, preserve durable contracts; no CI or final qualification.
- **Starting testing source:** `116f3563884b7a1cff660952ca9108691f123d67`
- **Baseline integrated dev:** `0200407133ce4696817271d2141fceaca82f8aff` (last known common source, to be reconciled against current dev).
- **Current dev at registration:** `d903569fa972fc049a1a52e2e5c43947a9c4c757`.
- **Progress:** Read testing AGENTS, published role method, shared protocol and branch state. Installed branch-local role-method snapshot and isolated registration. Preparation not yet complete.
- **Next:** Review the cumulative changed media/rights and administration paths, build a branch-only reconciled source tree retaining curated tests, then verify exact source parity and run only non-destructive structural checks.
- **Gate:** No test run, CI, Dev promotion, release or protected database action without separate authorization.
