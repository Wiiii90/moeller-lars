## Scope

<!-- What durable product/repository change does this PR make? Keep unrelated cleanup out. -->

## Target

- [ ] Worker/slice PR targeting an integration branch
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

## Data / migration impact

- [ ] No schema/data migration
- [ ] Migration included and forward/rollback implications documented

## Release / operations

- [ ] No secrets/private production data committed
- [ ] No Production deployment or mutation performed as part of this PR
- [ ] If a Validation preview was built, it is identified as preview-only and not release qualification
- [ ] If a release candidate is required, the exact SHA/image and remaining Validation step are stated
