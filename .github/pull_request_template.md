## Scope

<!-- What durable product/repository change does this PR make? Keep unrelated cleanup out. -->

## Target

- [ ] Worker/slice PR targeting an integration/reconciliation branch
- [ ] Final/release PR targeting `main`

## Verification

For worker/slice PRs, list the **risk-appropriate targeted checks actually run**. Do not claim the full release gate unless it ran.

For the final PR targeting `main`, the canonical workflow must cover:

- [ ] Composer install / security audit
- [ ] Frontend build
- [ ] Pest
- [ ] PHPStan
- [ ] Pint
- [ ] JavaScript tests

Targeted/manual/Validation notes:

<!-- State what was actually exercised. Visual/interaction changes require browser acceptance at the appropriate combined-candidate stage. Do not claim browser/Production validation that was not performed. -->

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
