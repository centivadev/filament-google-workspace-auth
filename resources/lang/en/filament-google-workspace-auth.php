<?php

return [
    'login' => [
        'button' => 'Sign in with Google',
    ],
    'navigation' => [
        'groups' => [
            'users' => 'Users',
        ],
    ],
    'filament' => [
        'users' => [
            'sections' => [
                'identity' => 'Identity',
                'access' => 'Access',
            ],
            'fields' => [
                'name' => 'Name',
                'email' => 'Email',
                'google_sub' => 'Google Subject',
                'avatar_url' => 'Avatar URL',
                'roles' => 'Roles',
                'permissions' => 'Permissions',
                'direct_permissions' => 'Direct permissions',
                'permissions_via_roles' => 'Permissions via roles',
                'banned_at' => 'Banned at',
                'last_login_at' => 'Last login',
            ],
            'empty' => [
                'permissions_via_roles' => 'None',
            ],
            'helpers' => [
                'self' => 'You cannot remove your own super-admin role, deactivate or ban yourself.',
                'last_super_admin' => 'This is the last active super-admin: it must keep the role and cannot be deactivated or banned.',
            ],
            'actions' => [
                'ban' => 'Ban',
                'unban' => 'Unban',
            ],
        ],
        'roles' => [
            'sections' => [
                'main' => 'Role',
            ],
            'fields' => [
                'name' => 'Name',
                'permissions' => 'Permissions',
            ],
        ],
        'permissions' => [
            'sections' => [
                'main' => 'Permission',
            ],
            'fields' => [
                'name' => 'Name',
            ],
        ],
    ],
    'protection' => [
        'self_super_admin' => 'You cannot remove your own super-admin role.',
        'self_deactivate' => 'You cannot deactivate or ban your own account.',
        'last_super_admin' => 'This is the last active super-admin: it must keep the role and stay active.',
        'last_super_admin_delete' => 'The last active super-admin cannot be deleted.',
    ],
];
