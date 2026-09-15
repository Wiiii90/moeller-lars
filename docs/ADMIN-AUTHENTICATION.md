# Admin authentication and account recovery

This document is the canonical application contract for authentication, authorization, multi-factor authentication and password recovery for the artist administration.

Infrastructure-level mail delivery, ingress and secret placement remain owned by [`Wiiii90/server-platform`](https://github.com/Wiiii90/server-platform). This document describes only the application behavior and the runtime interface it expects.

## Admin route and access boundary

The canonical administration route is `/admin`.

The route is intentionally conventional. Security does not depend on hiding or renaming it. Access is enforced by Laravel/Filament authentication and the application authorization boundary.

Current access contract:

- Filament panel id: `admin`;
- route prefix: `/admin`;
- Laravel session guard: `web`;
- password broker: `users`;
- public self-registration is not enabled;
- `User::canAccessPanel()` requires the `admin` panel and `is_admin=true`;
- passwords remain normal one-way Laravel password hashes and are never recoverable as plaintext.

A valid Laravel user account is therefore not sufficient to enter the administration unless it is explicitly an administrator account.

## Login and session behavior

The login flow uses Filament's authentication pages on top of Laravel's normal session stack. The panel retains the framework middleware for encrypted cookies, session startup, authenticated-session checks, CSRF protection and route bindings.

The project owns the restrained authentication presentation through the custom Filament auth page classes and `resources/css/admin/auth.css`. Login, password recovery and required-MFA screens should remain visually consistent with the artist administration without becoming a separate marketing/SaaS surface.

Password fields are not configured as revealable in the panel.

## Required multi-factor authentication

Administrator accounts require Filament app authentication (TOTP) with recovery codes.

The panel contract is:

- TOTP/app authentication is enabled and required;
- recovery codes are enabled;
- the Filament profile surface is available for MFA management;
- an administrator who has not configured MFA is routed through the required MFA setup before ordinary admin work;
- email is not used as the second authentication factor.

`User` implements Filament's app-authentication and recovery contracts. The database stores nullable `app_authentication_secret` and `app_authentication_recovery_codes` fields required by that framework integration. Filament's own concerns remain responsible for the sensitive-value storage semantics; application code must not invent a second TOTP/recovery-code implementation.

Recovery codes are credentials. They must not be logged, copied into Activity/notifications or exposed through application diagnostics.

## Password recovery

The administration supports email-based password reset. It never sends the existing password by email.

The request flow is deliberately enumeration-resistant:

- the request form accepts the account email address;
- broker lookup is constrained to `is_admin=true`;
- panel authorization is checked before a reset message is emitted;
- the browser receives the same neutral success message whether an administrator account exists or not;
- a non-admin Laravel user does not receive an admin password-reset message.

Current reset controls:

- reset token lifetime: 30 minutes;
- Laravel password-broker throttle: 60 seconds;
- the Filament request action also applies its own rate limit;
- reset tokens use Laravel's existing `password_reset_tokens` storage;
- the reset link leads back into the Filament admin password-reset flow so the user chooses a new password.

Successful recovery replaces the password through the framework reset flow. Passwords are never emailed, logged or made reversible.

## Transactional reset mail

`AdminPasswordResetNotification` is transactional authentication mail. It is separate from the persistent `AdminNotification`/Dashboard inbox contract documented in [ADMIN-NOTIFICATION-CONTRACT.md](ADMIN-NOTIFICATION-CONTRACT.md).

The reset request's neutral Filament feedback is likewise a pre-authentication response, not a persistent admin notification and not an Activity event.

Reset mail is currently sent synchronously. The application therefore does not require a queue worker for account recovery.

Local development defaults to the log mailer. Production/Validation must inject the real mail transport through runtime secrets/configuration. `.env.example` is the variable-name reference and currently documents the intended sender identity as `website@moeller-lars.de`; it does not prove that a deployed SMTP relay is configured or deliverable.

The platform owns the concrete SMTP host, port, TLS mode, credentials, relay policy and operational deliverability. No mail credentials belong in this repository.

## Administrator provisioning

Legacy administrator credentials are not imported or seeded.

Initial/explicit administrator provisioning uses:

```sh
php artisan admin:provision
```

The command keeps password entry interactive/hidden rather than accepting a password as a command-line argument. A newly provisioned administrator without existing app-authentication state must complete required MFA setup before ordinary panel use.

## Deployment expectations

A deployment that is expected to support password recovery must have working outbound transactional mail before it is considered operationally complete.

At minimum, deployment/release review should establish:

- the required forward migration for MFA columns has been applied;
- the intended administrator account can reach `/admin` and is subject to MFA;
- SMTP/runtime mail configuration is injected outside Git;
- the configured sender is accepted by the mail platform;
- a password-reset message can be delivered in the target environment without exposing whether arbitrary addresses are administrator accounts.

Do not use a real password-reset email test as an excuse to place credentials or reset URLs in CI logs.

## Verification ownership

Durable application tests should protect the security contract rather than Filament markup. Current coverage includes private `/admin` access, rejection of authenticated non-admin users, required MFA setup, the app-authentication/recovery contract and admin-only reset mail behavior.

The canonical release verification remains defined in [RELEASE.md](RELEASE.md). Mail-server/runtime verification belongs to the platform/deployment layer, not to unit tests in this repository.
