## Scope

<!-- What durable product/repository change does this PR make? Keep unrelated cleanup out. -->

## Target

<!-- Branch authority: AGENTS.md -->

- [ ] Scoped feature/fix/chore/docs PR targeting `dev` (exact dev base SHA recorded)
- [ ] Acceptance/release PR from `dev` targeting `main`

## Verification

<!-- List only checks actually run. Canonical CI/release gates and disposable-database rules are owned by docs/RELEASE.md. Local Feature/Pest runs against the persistent preview database are forbidden. -->

Targeted/manual/Validation notes:

<!-- State what was actually exercised. Visual/interaction changes require browser acceptance at the appropriate integrated-candidate stage. Do not claim browser/Production validation that was not performed. -->

## Admin UI / browser acceptance

<!-- For admin UI changes, review against ui-skills.md and current browser feedback. A page booting successfully is not browser/product acceptance. -->

- [ ] Not an admin UI change
- [ ] Shared admin primitives/geometry were reused where applicable
- [ ] No parallel Rich Text/media/DnD/modal/table technology introduced
- [ ] Current browser findings still requiring acceptance are stated

## Data / migration impact

- [ ] No schema/data migration
- [ ] Migration included and forward/rollback implications documented

## Media / reference impact

- [ ] No MediaAsset/reference/publication-policy change
- [ ] Reference versus public-delivery implications reviewed
- [ ] Protected preview behavior remains intentional

## Release / operations

- [ ] No secrets/private production data committed
- [ ] No Production deployment or mutation performed as part of this PR
- [ ] If a local/Validation preview was built, it is identified as preview-only and not release qualification
- [ ] If a release candidate is required, the exact SHA/image and remaining Validation/browser step are stated
