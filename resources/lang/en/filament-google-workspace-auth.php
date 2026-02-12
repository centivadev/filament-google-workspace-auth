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
                'banned_at' => 'Banned at',
                'last_login_at' => 'Last login',
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
];
