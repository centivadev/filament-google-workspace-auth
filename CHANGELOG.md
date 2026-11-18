# Changelog

All notable changes to `filament-google-workspace-auth` will be documented in this file.

## 2.0.0 - 2026-11-18

A major release: the new pending access lock is on by default, including for a config file published
before it existed. After upgrading, an account that holds nothing but `guest` (or the configured
`default_role`), or no role, only reaches the `pending-access` page, and permissions given to the
`guest` role no longer apply. Set `FILAMENT_GOOGLE_PENDING_ACCESS=false` to keep the previous
behaviour. While the lock is enabled, a panel throws a `LogicException` on boot when it uses tenancy,
when its `authGuard` is not the configured `guard`, or when spatie teams are on. An app that already
redirects these accounts to its own page must remove that redirect, or both redirects loop. Rebuild
the Filament component cache (`php artisan filament:optimize`) after upgrading if you cache it: until
then the new page has no route and locked accounts get a 403. No public API was removed.

### Added

- Pending access lock, on by default (`pending_access.enabled`, `FILAMENT_GOOGLE_PENDING_ACCESS`). An account that holds no role on the configured guard other than `pending_access.roles` (default `guest`), and no direct permission on it, is locked. `default_role` always counts as pending, and an account with no role at all, or a signed-in user that is not an Eloquent model, is locked too. The persistent `EnsureAccessIsGranted` panel middleware sends every panel request of a locked account, `livewire/update` included, to the new `PendingAccess` page (slug `pending-access`). That page only offers logging out and shows `pending_access.support_email` when it is set.
- While the lock is enabled, a `Gate::before` registered ahead of spatie's denies every ability to a locked instance of the configured `user_model`, so resources without a policy, `can:` middleware and `authorize()` calls are refused even where the app grants them.
- `pending_access.*` translations (en, fr).

### Security

- `CheckGoogleSessionLifetime` is registered as persistent, so the absolute session lifetime and the Google account check also run on `livewire/update`. Before, an expired session or a suspended Google account kept running Livewire actions until the next full page load.

### Changed

- The sign-in button spans the card through Filament's full-width actions container (`fi-ac fi-width-full`). The `w-full justify-center` utilities it used are not in Filament's compiled theme and had no effect.

## 1.2.1 - 2026-11-10

Upgrading is drop-in: no configuration changes are required and no public API was removed.

### Changed

- The users edit form is split into three sections: `Identity` (name, email, avatar URL) and `Account` (`banned_at`, Google subject, last login) side by side, then a full width `Roles and permissions` section with roles and direct permissions side by side and the permissions inherited through roles below. New translation keys `filament.users.sections.account` and `filament.users.helpers.direct_permissions`, and the `filament.users.sections.access` label becomes `Roles and permissions`.

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
