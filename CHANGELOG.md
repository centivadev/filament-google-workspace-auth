# Changelog

All notable changes to `filament-google-workspace-auth` will be documented in this file.

## 1.2.0 - 2026-11-10

No configuration changes are required and no public API was removed, but several behaviours change
and are worth reading before upgrading:

- A signed-in account that is banned or deactivated is now logged out on its next request,
  Livewire requests included. Previously the status was only checked at the Google callback, so a
  deactivated user kept access until the session ended. Accounts that are already inactive today
  lose their open sessions on upgrade.
- On the Filament users resource, nobody can remove their own `super-admin` role, deactivate or ban
  themselves, and the last active, non-banned super-admin can no longer lose the role, be
  deactivated, banned or deleted, by anyone. `FilamentUserPolicy::delete()` now denies that account.
- The roles and permissions selects on the users resource only list records of the configured
  `guard`. Links to records of other guards are kept on save.
- The edit page of the users resource now runs in a database transaction.

### Security

- Add the persistent `EnsureAccountIsAllowed` panel middleware, registered by the plugin with `authMiddleware(..., isPersistent: true)` so that it also runs on `livewire/update`. It applies the same rules as sign-in (`isBanned()` / `isActive()` when the model uses `HasFilamentGoogleWorkspaceUser`, the `banned_at` / `is_active` attributes otherwise, no `is_active` column meaning active), then logs out, invalidates the session and redirects to the login page.
- Add `AccountProtection`, a public API enforcing the account protection rules server-side, for projects with their own users screen. Its `ensure*` methods lock the `super-admin` role row, its pivot rows and its holders (`SELECT ... FOR UPDATE`) so that two super-admins demoting each other at the same time are serialized. They must run inside a transaction on the user model connection.
- Enforce those rules in `EditFilamentUser` (`beforeSave`) and in the `ban` row action, not only through the UI.

### Fixed

- The `ban` and `unban` row actions no longer write `is_active` when the table has no such column. They used to fail with an SQL error, leaving the account unbanned.

### Changed

- The users form shows direct permissions and permissions inherited through roles separately.
- The `banned_at` field and the `ban` action are disabled, with an explanation, on your own account and on the last active super-admin.
- Clear the spatie permission cache after saving a user.
- New `AccountStatus` helper shared by the Google callback, the new middleware and `AccountProtection`.

## 1.1.1 - 2026-08-27

Upgrading is drop-in: no configuration changes are required and no public API was removed. The one
behaviour change is that the `ban` and `unban` row actions on the Filament users list now require
`filament.users.update` — previously they ran for anyone who could merely view the list.

### Security

- Require `filament.users.update` before the `ban` and `unban` row actions on the Filament users resource run. Unlike `EditAction`, a plain `Action::make()` does not auto-resolve a policy — with no `->authorize()` call its default authorization is "allowed", so any user holding only `filament.users.view_any` could ban or unban any account, including other admins, without ever holding `filament.users.update`.

## 1.1.0 - 2026-08-14

Upgrading is drop-in: no configuration changes are required and no public API was removed. The
behaviour changes worth knowing about are that accounts with a falsy `is_active` can no longer sign
in — that gate never actually worked before — and that the auth routes are now rate limited.

### Security

- Reject callbacks with an absent `state`, `nonce` or `code_verifier`. `hash_equals('', '')` returns `true`, so a session that carried none of these values passed the CSRF and replay checks — a callback could be accepted without the flow ever having gone through `/auth/google`.
- Enforce the `is_active` gate. It was guarded by `property_exists()`, which is always `false` for Eloquent column values, so deactivated users could still sign in and the account was silently re-activated on every login. Only `banned_at` was actually enforced.
- Refuse banned and deactivated accounts **before** the record is written, so a rejected login no longer refreshes `last_login_at`, `name` or `avatar_url`.
- Reject ID tokens with no `sub` claim instead of storing an empty `google_sub`, which could match an unrelated account on a later lookup.
- Rate limit the redirect and callback routes (`routes.throttle`, default `120,1`). Deliberately generous: Laravel keys the limiter by IP and a whole Workspace office usually shares one NAT address, so this is an abuse ceiling rather than the brute-force defence.
- Restore `JWT::$leeway` after verification instead of leaking the 30s tolerance to every other JWT consumer in the host application.

### Changed

- `spatie/laravel-permission` now allows v7 and v8 (was v6 only), unblocking installs on projects already on those majors.
- A model missing `HasRoles` now aborts with an explanatory 500 instead of a bare `BadMethodCallException`.
- `avatar_url` stores `null` rather than `''` when Google sends no picture.
- Replaced the deprecated `Table::actions()` with `recordActions()`.

### Internal

- Dependencies updated: 47 known advisories in the lock file down to zero. Filament 5.2.1 → 5.7.6.
- Replaced the abandoned `nunomaduro/larastan` with `larastan/larastan` v3 and PHPStan v2. This was what pinned the toolchain to Laravel 11, where the remaining advisories had no patched release.
- Tooling modernised to Pest 5 and Testbench 11, which requires PHP 8.4 and Laravel 13 to run the test suite.
- PHPStan raised from level 3 to level 6 over `src` and `tests`, with no suppressions in `src`.
- Added a test workflow (there was none), PHPStan on pull requests, a `composer audit` job, and Dependabot coverage for Composer. Dependabot auto-merge narrowed to patch updates.

## 1.0.6 - 2026-06-15

- Simplify the login view button markup

## 1.0.5 - 2026-03-25

- Fix intermittent `BeforeValidException` with Google OIDC tokens by adding 30s JWT leeway for clock skew tolerance

## 1.0.0 - 202X-XX-XX

- initial release
