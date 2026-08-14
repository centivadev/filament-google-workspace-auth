# Filament Google Workspace Auth

Google Workspace (OIDC) authentication for Filament v4/v5 using a dedicated `FilamentUser` model and Spatie roles/permissions.

## Features

- 100% Google login (no username/password)
- Workspace domain restriction (`hd` + email domain)
- Automatic user provisioning with avatar + last login timestamp
- Default role assignment on first login (configurable)
- Filament resources to manage users/roles/permissions (with protected roles)
- Policies + permissions-based authorization (Laravel Gate)
- Separate guard and model to avoid conflicts with a future `User` model
- Session validity management: absolute timeout + near-real-time Google account revocation detection
- Ban and deactivation gates enforced before the account record is touched
- Rate-limited auth routes

## Requirements

- PHP 8.2+
- Filament v4 or v5
- Laravel 11/12/13
- `spatie/laravel-permission` v6, v7 or v8

> **Tested against:** PHP 8.4, Laravel 13, Filament v5 and `spatie/laravel-permission` v8.
> The lower bounds above are declared but not exercised in CI — the test toolchain (Pest 5)
> requires PHP 8.4 and Laravel 13. Filament v4 support in particular is unverified.

## Installation

```bash
composer require centivadev/filament-google-workspace-auth
```

Publish config + migrations:

```bash
php artisan vendor:publish --tag="filament-google-workspace-auth-config"
php artisan vendor:publish --tag="filament-google-workspace-auth-migrations"
```

Install Spatie permissions (migrations + config):

```bash
php artisan vendor:publish --provider="Spatie\Permission\PermissionServiceProvider" --tag="permission-migrations"
php artisan vendor:publish --provider="Spatie\Permission\PermissionServiceProvider" --tag="permission-config"
php artisan migrate
```

## Google Cloud Console Setup

1. **Create or select a Google Cloud Project**
2. **Configure OAuth Consent Screen**
   - Type: `Internal` (Workspace only)
   - Add your Workspace domain (`mydomain.com`)
   - Add scopes: `openid`, `email`, `profile`
3. **Create OAuth Client ID**
   - Type: `Web application`
   - Authorized redirect URI:
     - `https://YOUR-FILAMENT-DOMAIN/auth/google/callback`
     - Example: `https://admin.mydomain.com/auth/google/callback`
4. **Copy the Client ID and Client Secret** into your `.env`

```dotenv
FILAMENT_GOOGLE_CLIENT_ID=xxx.apps.googleusercontent.com
FILAMENT_GOOGLE_CLIENT_SECRET=xxxx
FILAMENT_GOOGLE_REDIRECT_URI=https://admin.mydomain.com/auth/google/callback
FILAMENT_GOOGLE_HOSTED_DOMAIN=mydomain.com
FILAMENT_GOOGLE_SUPER_ADMIN_EMAILS=admin@mydomain.com,cto@mydomain.com
FILAMENT_GOOGLE_DEFAULT_ROLE=guest
FILAMENT_GOOGLE_ROUTE_PREFIX=auth/google
```

## Filament Panel Setup

Enable the plugin and remove password-based features from your panel provider:

```php
use CentivaDev\FilamentGoogleWorkspaceAuth\FilamentGoogleWorkspaceAuthPlugin;

return $panel
    ->login()
    ->plugins([
        FilamentGoogleWorkspaceAuthPlugin::make(),
    ]);
```

Remove `->passwordReset()` and `->emailVerification()` from your panel provider to keep the login 100% Google.

## FilamentUser model

- `HasRoles` (Spatie) is required — it provides `assignRole()`, used for the default and
  super-admin roles.
- `HasFilamentGoogleWorkspaceUser` is recommended. It provides `isBanned()` and `isActive()`,
  which gate sign-in. A model without it still works and is still gated: the same rule is read
  straight off `banned_at` and `is_active`. Override either method to customise the rule.

`is_active` is optional: models without that column are always considered active. It is owned by
your application, not by this package's migrations, so give it a database default.

```php
use CentivaDev\FilamentGoogleWorkspaceAuth\Concerns\HasFilamentGoogleWorkspaceUser;
use Spatie\Permission\Traits\HasRoles;

class FilamentUser extends Authenticatable implements FilamentUserContract, HasAvatar, HasName
{
    use HasFilamentGoogleWorkspaceUser;
    use HasRoles;

    protected $fillable = [
        'name',
        'email',
        'google_sub',
        'avatar_url',
        'last_login_at',
        'email_verified_at',
        'banned_at',
        'is_active',
    ];

    protected $casts = [
        'last_login_at' => 'datetime',
        'email_verified_at' => 'datetime',
        'banned_at' => 'datetime',
        'is_active' => 'boolean',
    ];
}
```

Make sure the `filament` guard exists in `config/auth.php` and that `filament-users` provider uses the `FilamentUser` model.

## Configuration

The published config file lives at:

- `config/filament-google-workspace-auth.php`

Key options:

- `hosted_domain` to restrict Workspace domain
- `allowed_emails` to restrict to specific emails
- `super_admin_emails` to auto-assign `super-admin`
- `default_role` to auto-assign `guest`
- `guard` to match your Filament guard (default: `filament`)
- `routes.prefix` to align with your Filament path (example: `auth/google` for a root‑domain panel)
- `routes.throttle` abuse ceiling on the redirect/callback routes, as `"attempts,minutes"` (default: `120,1`, `null` to disable). Laravel keys this by IP and a whole Workspace office usually shares one NAT address, so keep it generous — the brute-force defence is PKCE/state/nonce, not this.

## Access control

### How accounts are matched

On callback, an account is looked up by `google_sub` **or** by `email`. The email match is what
lets you pre-create accounts (or migrate existing ones) and have them adopted by the first Google
login, which then stores `google_sub` as the permanent identity anchor.

This means **anyone who can authenticate with an email already present in `filament_users` takes
over that row**. That is safe because the email must be verified by Google and must pass your
domain/allowlist restrictions — but it does mean those restrictions are what protect the mapping.
Configure at least one of them.

### Domain restriction

`hosted_domain` is only enforced when it is set. When set, both checks below must pass:

- the `hd` claim must match, when present — personal Gmail accounts carry no `hd`
- the email must end with `@<hosted_domain>`

The email suffix check is the one that stops personal Gmail accounts, so `hosted_domain` alone is
sufficient. Leaving it empty means **any** Google account can sign in unless `allowed_emails` is set.

### Account status

For an existing account, sign-in is refused — before the record is written — when `banned_at` is
set or an `is_active` column is falsy. This resolves through `isBanned()` / `isActive()` when the
model uses `HasFilamentGoogleWorkspaceUser`, and off the attributes otherwise.

So banning or deactivating takes effect on the next login attempt, signing in never re-activates a
deactivated account, and a refused attempt leaves `last_login_at` and the profile fields untouched.

Newly provisioned accounts have no prior status to check; `is_active` takes your column default.

## Admin UI

The plugin registers three resources (configurable):

- Filament Users
- Roles
- Permissions

They are grouped under the navigation group configured in `resources.navigation_group`.

Protected roles:
- `super-admin` and `guest` cannot be deleted
- The `name` of those roles is not editable

Base permissions:
- The package ships a migration stub `add_base_permissions.php.stub` that seeds default Filament permissions.
- It also creates the `super-admin` + `guest` roles if missing.
- It assigns **all permissions for the guard** to `super-admin`.
  Publish and run the package migrations to create them.

Authorization:
- Policies are registered for roles, permissions, and Filament users.
- Gate checks rely on Spatie permissions like `filament.users.*`, `filament.roles.*`, `filament.permissions.*`.

## Session Validity

The package provides two independent mechanisms to ensure sessions stay in sync with Google Workspace.

### Remember me

Controls whether a persistent cookie is issued after login. When `false` (default), the session ends when the browser is closed.

```dotenv
FILAMENT_GOOGLE_REMEMBER=false
```

### Absolute session lifetime

Forces the user to re-authenticate with Google after a fixed delay, regardless of activity.

```dotenv
FILAMENT_GOOGLE_SESSION_LIFETIME=480   # 8 hours, null to disable
```

This is independent from Laravel's native `SESSION_LIFETIME` (`config/session.php`). Both apply simultaneously — the one that triggers first wins:

| Setting | Type | Resets on activity? |
|---|---|---|
| Laravel `SESSION_LIFETIME` | Idle timeout | Yes — extends on every request |
| `FILAMENT_GOOGLE_SESSION_LIFETIME` | Absolute timeout | No — fixed since login |

**Example:** `SESSION_LIFETIME=120` (2h idle) + `FILAMENT_GOOGLE_SESSION_LIFETIME=480` (8h absolute).
A user active all day is kicked out after 8 hours. An idle user is kicked out after 2 hours.

> **Important:** When `FILAMENT_GOOGLE_REMEMBER=true`, the remember-me cookie bypasses Laravel's `SESSION_LIFETIME` entirely. In that case, `FILAMENT_GOOGLE_SESSION_LIFETIME` is the only timeout enforced.

### Google account revocation detection

Periodically calls the Google OpenID Connect UserInfo endpoint (`https://openidconnect.googleapis.com/v1/userinfo`) to verify the user's account is still active. If the account has been deleted or suspended in Google Workspace, the user is logged out immediately.

```dotenv
FILAMENT_GOOGLE_USERINFO_CHECK_INTERVAL=5   # every 5 minutes, null to disable
```

The check uses the `access_token` stored in the session (valid for 60 minutes after login). After that window, `session_lifetime` acts as the safety net.

**Timeline:**

```
Login
  │
  ├─ 0–60 min ──── UserInfo check every N minutes ──── detection within N minutes
  │
  └─ 60 min+ ─────────── session_lifetime only ──────── detection at expiry
```

> Network errors when calling the UserInfo endpoint are ignored (fail open) to avoid disrupting legitimate users during transient Google outages.

## Notes

- This package does not use Socialite.
- All auth is OIDC with PKCE (S256), plus `state` and `nonce`. A callback whose session carries
  none of those values is refused — it never went through `/auth/google`.
- The redirect and callback routes are rate limited (`routes.throttle`).
- If you want to disable auto-provisioning, set `FILAMENT_GOOGLE_AUTO_PROVISION=false`.

## Testing

```bash
composer test      # Pest
composer analyse   # PHPStan, level 6 over src and tests
composer format    # Pint
```

Tests are fully offline: Google endpoints are mocked, no real credentials are required.

The dev toolchain (Pest 5, Testbench 11) requires **PHP 8.4 and Laravel 13**, which is narrower
than what the package itself supports. See the note under [Requirements](#requirements).
