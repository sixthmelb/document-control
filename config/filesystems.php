<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Default Filesystem Disk
    |--------------------------------------------------------------------------
    */
    'default' => env('FILESYSTEM_DISK', 'local'),

    /*
    |--------------------------------------------------------------------------
    | Filesystem Disks
    |--------------------------------------------------------------------------
    */
    'disks' => [
        'local' => [
            'driver' => 'local',
            'root' => storage_path('app'),
            'throw' => false,
        ],

        'public' => [
            'driver' => 'local',
            'root' => storage_path('app/public'),
            'url' => env('APP_URL').'/storage',
            'visibility' => 'public',
            'throw' => false,
        ],

        /*
        |--------------------------------------------------------------------------
        | Document Storage Disks
        |--------------------------------------------------------------------------
        */
        'documents' => [
            'driver' => 'local',
            'root' => storage_path('app/documents'),
            'visibility' => 'private',
            'throw' => false,
        ],

        'qrcodes' => [
            'driver' => 'local',
            'root' => storage_path('app/public/qrcodes'),
            'url' => env('APP_URL').'/storage/qrcodes',
            'visibility' => 'public',
            'throw' => false,
        ],

        /*
        |--------------------------------------------------------------------------
        | Temporary Storage
        |--------------------------------------------------------------------------
        */
        'temp' => [
                    'driver' => 'local',
                    'root' => storage_path('app/temp'),
                    'visibility' => 'private',
                    'throw' => false,
                ],

        /*
        |--------------------------------------------------------------------------
        | Backup Storage
        |--------------------------------------------------------------------------
        */
        'backups' => [
            'driver' => 'local',
            'root' => storage_path('app/backups'),
            'visibility' => 'private',
            'throw' => false,
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Symbolic Links
    |--------------------------------------------------------------------------
    */
    'links' => [
        public_path('storage') => storage_path('app/public'),
        public_path('qrcodes') => storage_path('app/public/qrcodes'),
    ],
];