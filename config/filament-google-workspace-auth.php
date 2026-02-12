<?php

return [
    'client_id' => env('FILAMENT_GOOGLE_CLIENT_ID'),
    'client_secret' => env('FILAMENT_GOOGLE_CLIENT_SECRET'),

    // Optional explicit redirect URI. If null, route() will be used.
    'redirect_uri' => env('FILAMENT_GOOGLE_REDIRECT_URI'),

    // Workspace domain restriction.
    'hosted_domain' => env('FILAMENT_GOOGLE_HOSTED_DOMAIN'),

    // Optional allowlist of emails. If set, only these can sign in.
    'allowed_emails' => array_values(array_filter(array_map('trim', explode(',', (string) env('FILAMENT_GOOGLE_ALLOWED_EMAILS', ''))))),

    // Emails that should always be super-admin.
    'super_admin_emails' => array_values(array_filter(array_map('trim', explode(',', (string) env('FILAMENT_GOOGLE_SUPER_ADMIN_EMAILS', ''))))),

    // Default role assigned on first login when not super-admin.
    'default_role' => env('FILAMENT_GOOGLE_DEFAULT_ROLE', 'guest'),

    // Spatie permission guard name.
    'guard' => env('FILAMENT_GOOGLE_GUARD', 'filament'),

    // Filament user model to use.
    'user_model' => env('FILAMENT_GOOGLE_USER_MODEL', App\Models\FilamentUser::class),

    // If true, new users are created automatically on first login.
    'auto_provision' => env('FILAMENT_GOOGLE_AUTO_PROVISION', true),

    // Resource registration (users/roles/permissions) inside Filament.
    'resources' => [
        'users' => true,
        'roles' => true,
        'permissions' => true,
        'navigation_group' => 'System',
    ],

    // Route configuration.
    'routes' => [
        'prefix' => env('FILAMENT_GOOGLE_ROUTE_PREFIX', 'auth/google'),
    ],
];
