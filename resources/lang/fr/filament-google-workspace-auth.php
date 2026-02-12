<?php

return [
    'login' => [
        'button' => 'Se connecter avec Google',
    ],
    'navigation' => [
        'groups' => [
            'users' => 'Utilisateurs',
        ],
    ],
    'filament' => [
        'users' => [
            'sections' => [
                'identity' => 'Identite',
                'access' => 'Acces',
            ],
            'fields' => [
                'name' => 'Nom',
                'email' => 'Email',
                'google_sub' => 'Google Subject',
                'avatar_url' => 'Avatar URL',
                'roles' => 'Roles',
                'permissions' => 'Permissions',
                'banned_at' => 'Banni le',
                'last_login_at' => 'Derniere connexion',
            ],
            'actions' => [
                'ban' => 'Bannir',
                'unban' => 'Debannir',
            ],
        ],
        'roles' => [
            'sections' => [
                'main' => 'Role',
            ],
            'fields' => [
                'name' => 'Nom',
                'permissions' => 'Permissions',
            ],
        ],
        'permissions' => [
            'sections' => [
                'main' => 'Permission',
            ],
            'fields' => [
                'name' => 'Nom',
            ],
        ],
    ],
];
