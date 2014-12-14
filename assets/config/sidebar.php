<?php

return [
    'sidebar' => [
        'reader' => [
            'name' => _i('Reader'),
            'level' => 'admin',
            'default' => 'manage',
            'content' => [
                'manage' => [
                    'alt_highlight' => ['board'],
                    'level' => 'admin',
                    'name' => _i('Manage'),
                    'icon' => 'icon-th-list'
                ],
                'add_series' => [
                    'level' => 'admin',
                    'name' => _i('Add Series'),
                    'icon' => 'icon-th-list'
                ],
                'preferences' => [
                    'level' => 'admin',
                    'name' => _i('Preferences'),
                    'icon' => 'icon-check'
                ]
            ]
        ],
        'boards' => [
            'name' => _i('Boards'),
            'level' => 'admin',
            'default' => 'manage',
            'content' => [
                'add_series' => [
                    'level' => 'admin',
                    'name' => _i('Add Series'),
                    'icon' => 'icon-th-list'
                ],
                'manage' => [
                    'alt_highlight' => ['board'],
                    'level' => 'admin',
                    'name' => _i('Manage'),
                    'icon' => 'icon-th-list'
                ],
                'search' => [
                    'level' => 'admin',
                    'name' => _i('Search'),
                    'icon' => 'icon-search'
                ],
                'preferences' => [
                    'level' => 'admin',
                    'name' => _i('Preferences'),
                    'icon' => 'icon-check'
                ]
            ]
        ],
        'moderation' => [
            'name' => _i('Moderation'),
            'level' => 'mod',
            'default' => 'reports',
            'content' => [
                'bans' => [
                    'level' => 'mod',
                    'name' => _i('Bans'),
                    'icon' => 'icon-truck'
                ],
                'appeals' => [
                    'level' => 'mod',
                    'name' => _i('Pending Appeals'),
                    'icon' => 'icon-heart-empty'
                ]
            ]
        ]
    ]
];
