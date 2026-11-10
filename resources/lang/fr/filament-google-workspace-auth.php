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
                'account' => 'Compte',
                'access' => 'Roles et permissions',
            ],
            'fields' => [
                'name' => 'Nom',
                'email' => 'Email',
                'google_sub' => 'Google Subject',
                'avatar_url' => 'Avatar URL',
                'roles' => 'Roles',
                'permissions' => 'Permissions',
                'direct_permissions' => 'Permissions directes',
                'permissions_via_roles' => 'Permissions via les roles',
                'banned_at' => 'Banni le',
                'last_login_at' => 'Derniere connexion',
            ],
            'empty' => [
                'permissions_via_roles' => 'Aucune',
            ],
            'helpers' => [
                'self' => 'Vous ne pouvez pas retirer votre propre role super-admin, ni vous desactiver ou vous bannir.',
                'last_super_admin' => 'C\'est le dernier super-admin actif : il doit garder ce role et ne peut etre ni desactive ni banni.',
                'direct_permissions' => 'Accordees en plus de celles des roles.',
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
    'protection' => [
        'self_super_admin' => 'Vous ne pouvez pas retirer votre propre role super-admin.',
        'self_deactivate' => 'Vous ne pouvez pas desactiver ou bannir votre propre compte.',
        'last_super_admin' => 'C\'est le dernier super-admin actif : il doit garder ce role et rester actif.',
        'last_super_admin_delete' => 'Le dernier super-admin actif ne peut pas etre supprime.',
    ],
];
