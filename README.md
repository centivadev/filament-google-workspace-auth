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

## Requirements

- PHP 8.2+
- Filament v4 or v5
- Laravel 11/12+

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

Add the required traits and fields:

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
        'banned_at',
        'is_active',
    ];

    protected $casts = [
        'last_login_at' => 'datetime',
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

## Notes

- This package does not use Socialite.
- All auth is OIDC with PKCE.
- If you want to disable auto-provisioning, set `FILAMENT_GOOGLE_AUTO_PROVISION=false`.

## Testing

```bash
composer test
```
