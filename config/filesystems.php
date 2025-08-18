<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Default Filesystem Disk
    |--------------------------------------------------------------------------
    |
    | Here you may specify the default filesystem disk that should be used
    | by the framework. The "local" disk, as well as a variety of cloud
    | based disks are available to your application for file storage.
    |
    */

    'default' => env('FILESYSTEM_DISK', 'local'),

    /*
    |--------------------------------------------------------------------------
    | Filesystem Disks
    |--------------------------------------------------------------------------
    |
    | Below you may configure as many filesystem disks as necessary, and you
    | may even configure multiple disks for the same driver. Examples for
    | most supported storage drivers are configured here for reference.
    |
    | Supported drivers: "local", "ftp", "sftp", "s3"
    |
    */

    'disks' => [

        'local' => [
            'driver' => 'local',
            'root' => storage_path('app/private'),
            'serve' => true,
            'throw' => false,
            'report' => false,
        ],

        'public' => [
            'driver' => 'local',
            'root' => storage_path('app/public'),
            'url' => env('APP_URL').'/storage',
            'visibility' => 'public',
            'throw' => false,
            'report' => false,
        ],

        's3' => [
            'driver' => 's3',
            'key' => env('AWS_ACCESS_KEY_ID'),
            'secret' => env('AWS_SECRET_ACCESS_KEY'),
            'region' => env('AWS_DEFAULT_REGION'),
            'bucket' => env('AWS_BUCKET'),
            'url' => env('AWS_URL'),
            'endpoint' => env('AWS_ENDPOINT'),
            'use_path_style_endpoint' => env('AWS_USE_PATH_STYLE_ENDPOINT', false),
            'throw' => false,
            'report' => false,
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

        'documents_public' => [
            'driver' => 'local',
            'root' => storage_path('app/public/documents'),
            'url' => env('APP_URL').'/storage/documents',
            'visibility' => 'public',
            'throw' => false,
        ],

        'qrcodes' => [
            'driver' => 'local',
            'root' => storage_path('app/public/qrcodes'),
            'url' => env('APP_URL').'/storage/qrcodes',
            'visibility' => 'public',
            'throw' => false,
        ],

        'temp' => [
            'driver' => 'local',
            'root' => storage_path('app/temp'),
            'visibility' => 'private',
            'throw' => false,
        ],

        /*
        |--------------------------------------------------------------------------
        | Backup and Archive Storage
        |--------------------------------------------------------------------------
        */

        'backups' => [
            'driver' => 'local',
            'root' => storage_path('app/backups'),
            'visibility' => 'private',
            'throw' => false,
        ],

        'archives' => [
            'driver' => 'local',
            'root' => storage_path('app/archives'),
            'visibility' => 'private',
            'throw' => false,
        ],

        /*
        |--------------------------------------------------------------------------
        | Cloud Storage (Optional)
        |--------------------------------------------------------------------------
        |
        | Configure these if you want to use cloud storage for documents
        |
        */

        // 's3_documents' => [
        //     'driver' => 's3',
        //     'key' => env('AWS_ACCESS_KEY_ID'),
        //     'secret' => env('AWS_SECRET_ACCESS_KEY'),
        //     'region' => env('AWS_DEFAULT_REGION'),
        //     'bucket' => env('AWS_BUCKET') . '/documents',
        //     'url' => env('AWS_URL'),
        //     'endpoint' => env('AWS_ENDPOINT'),
        //     'use_path_style_endpoint' => env('AWS_USE_PATH_STYLE_ENDPOINT', false),
        //     'throw' => false,
        // ],

        // 'azure_documents' => [
        //     'driver' => 'azure',
        //     'account' => env('AZURE_STORAGE_ACCOUNT'),
        //     'key' => env('AZURE_STORAGE_KEY'),
        //     'container' => 'documents',
        //     'url' => env('AZURE_STORAGE_URL'),
        //     'throw' => false,
        // ],

        // 'google_documents' => [
        //     'driver' => 'gcs',
        //     'project_id' => env('GOOGLE_CLOUD_PROJECT_ID'),
        //     'key_file' => env('GOOGLE_CLOUD_KEY_FILE'),
        //     'bucket' => env('GOOGLE_CLOUD_STORAGE_BUCKET'),
        //     'path_prefix' => 'documents',
        //     'storage_api_uri' => env('GOOGLE_CLOUD_STORAGE_API_URI'),
        //     'throw' => false,
        // ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Symbolic Links
    |--------------------------------------------------------------------------
    |
    | Here you may configure the symbolic links that will be created when the
    | `storage:link` Artisan command is executed. The array keys should be
    | the locations of the links and the values should be their targets.
    |
    */

    'links' => [
        public_path('storage') => storage_path('app/public'),
        public_path('documents') => storage_path('app/public/documents'),
        public_path('qrcodes') => storage_path('app/public/qrcodes'),

    ],

    /*
    |--------------------------------------------------------------------------
    | Symbolic Links
    |--------------------------------------------------------------------------
    |
    | Here you may configure the symbolic links that will be created when the
    | `storage:link` Artisan command is executed. The array keys should be
    | the locations of the links and the values should be their targets.
    |
    */

    'links' => [
        public_path('storage') => storage_path('app/public'),
    ],

];
