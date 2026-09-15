# Admin password policy

`App\Support\AdminPasswordPolicy` is the single password-rule authority for administrator provisioning, account changes and password reset.

## Environment behavior

- Validation, Production and every environment other than `local`/`testing`: 15 to 128 characters.
- `local` and `testing`: 8 to 128 characters and no external breach lookup.
- Character-class composition is not required. Letters, digits, symbols, spaces and Unicode are allowed.
- Strict environments reject obvious administration-specific guesses and use Laravel's uncompromised-password check.
- Existing stored passwords are not revalidated during normal login; the policy applies when a password is provisioned, changed or reset.

The relaxed local/testing rule exists only for developer convenience. It is not a production recommendation.

## Generator

`AdminPasswordField` exposes Generate and Copy actions in the Account dialog and password-reset form.

Generation runs in the browser with Web Crypto (`crypto.getRandomValues()`), uses rejection sampling to avoid modulo bias, produces 28-character values from a letters/digits/symbols alphabet and fills the confirmation field at the same time. Copying only happens after an explicit user action.

The generator does not use mouse movement, timing entropy or `Math.random()`. Generated values are not logged or sent to analytics. They reach the application only through the normal form submission needed to hash and store the new password.

A password manager is the preferred storage location for generated credentials.

## Provisioning

`php artisan admin:provision` uses Laravel's configured default password rule, so it cannot drift from Account/reset behavior.

Validation and Production administrator accounts are provisioned independently. Legacy credentials are not imported or reused.
