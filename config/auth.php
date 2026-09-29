<?php

return [

    'defaults' => [
        'guard'     => 'agent',
        'passwords' => 'agents',
    ],

    'guards' => [
        'web' => [
            'driver'   => 'session',
            'provider' => 'users',
        ],
        'agent' => [
            'driver'   => 'session',
            'provider' => 'agents',
        ],
        // NEW 8 Aug 2026 (Task #92) — separate guard for the new Vendor
        // Portal. Completely isolated from 'agent' — a vendor session can
        // never see agent screens and vice versa.
        'vendor' => [
            'driver'   => 'session',
            'provider' => 'vendors',
        ],
    ],

    'providers' => [
        'users' => [
            'driver' => 'eloquent',
            'model'  => App\Models\User::class,
        ],
        'agents' => [
            'driver' => 'eloquent',
            'model'  => App\Models\Agent::class,
        ],
        'vendors' => [
            'driver' => 'eloquent',
            'model'  => App\Models\Vendor::class,
        ],
    ],

    'passwords' => [
        'users' => [
            'provider' => 'users',
            'table'    => 'password_reset_tokens',
            'expire'   => 60,
            'throttle' => 60,
        ],
        'agents' => [
            'provider' => 'agents',
            'table'    => 'password_reset_tokens',
            'expire'   => 60,
            'throttle' => 60,
        ],
    ],

    'password_timeout' => 10800,

];
