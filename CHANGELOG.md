# Changelog

All notable changes to `filament-google-workspace-auth` will be documented in this file.

## Unreleased

### Security

- Reject callbacks with an absent `state`, `nonce` or `code_verifier`. `hash_equals('', '')` returns `true`, so a session that carried none of these values passed the CSRF and replay checks — a callback could be accepted without the flow ever having gone through `/auth/google`.
- Enforce the `is_active` gate. It was guarded by `property_exists()`, which is always `false` for Eloquent column values, so deactivated users could still sign in and the account was silently re-activated on every login. Only `banned_at` was actually enforced.
- Refuse banned and deactivated accounts **before** the record is written, so a rejected login no longer refreshes `last_login_at`, `name` or `avatar_url`.
- Reject ID tokens with no `sub` claim instead of storing an empty `google_sub`, which could match an unrelated account on a later lookup.
- Rate limit the redirect and callback routes (`routes.throttle`, default `30,1`).
- Restore `JWT::$leeway` after verification instead of leaking the 30s tolerance to every other JWT consumer in the host application.

### Changed

- **Breaking:** the user model must use `HasFilamentGoogleWorkspaceUser` and `HasRoles`; a misconfigured model now fails loudly instead of skipping the account-status checks. Both traits were already documented as required.
- `spatie/laravel-permission` now allows v7 and v8 (was v6 only), unblocking installs on projects already on those majors.
- `avatar_url` stores `null` rather than `''` when Google sends no picture.
- Replaced the deprecated `Table::actions()` with `recordActions()`.

### Internal

- Dependencies updated: 47 known advisories in the lock file down to zero. Filament 5.2.1 → 5.7.6.
- Replaced the abandoned `nunomaduro/larastan` with `larastan/larastan` v3 and PHPStan v2. This was what pinned the toolchain to Laravel 11, where the remaining advisories had no patched release.
- Tooling modernised to Pest 5 and Testbench 11, which requires PHP 8.4 and Laravel 13 to run the test suite.
- PHPStan raised from level 3 to level 6 over `src` and `tests`, with no suppressions in `src`.
- Added a test workflow (there was none), PHPStan on pull requests, a `composer audit` job, and Dependabot coverage for Composer. Dependabot auto-merge narrowed to patch updates.

## 1.0.5 - 2026-03-25

- Fix intermittent `BeforeValidException` with Google OIDC tokens by adding 30s JWT leeway for clock skew tolerance

## 1.0.0 - 202X-XX-XX

- initial release
