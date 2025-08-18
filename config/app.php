<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Application Name
    |--------------------------------------------------------------------------
    |
    | This value is the name of your application, which will be used when the
    | framework needs to place the application's name in a notification or
    | other UI elements where an application name needs to be displayed.
    |
    */

    'name' => env('APP_NAME', 'Laravel'),

    /*
    |--------------------------------------------------------------------------
    | Application Environment
    |--------------------------------------------------------------------------
    |
    | This value determines the "environment" your application is currently
    | running in. This may determine how you prefer to configure various
    | services the application utilizes. Set this in your ".env" file.
    |
    */

    'env' => env('APP_ENV', 'production'),

    /*
    |--------------------------------------------------------------------------
    | Application Debug Mode
    |--------------------------------------------------------------------------
    |
    | When your application is in debug mode, detailed error messages with
    | stack traces will be shown on every error that occurs within your
    | application. If disabled, a simple generic error page is shown.
    |
    */

    'debug' => (bool) env('APP_DEBUG', false),

    /*
    |--------------------------------------------------------------------------
    | Application URL
    |--------------------------------------------------------------------------
    |
    | This URL is used by the console to properly generate URLs when using
    | the Artisan command line tool. You should set this to the root of
    | the application so that it's available within Artisan commands.
    |
    */

    'url' => env('APP_URL', 'http://localhost'),

    /*
    |--------------------------------------------------------------------------
    | Application Timezone
    |--------------------------------------------------------------------------
    |
    | Here you may specify the default timezone for your application, which
    | will be used by the PHP date and date-time functions. The timezone
    | is set to "UTC" by default as it is suitable for most use cases.
    |
    */

    'timezone' => 'UTC',

    /*
    |--------------------------------------------------------------------------
    | Application Locale Configuration
    |--------------------------------------------------------------------------
    |
    | The application locale determines the default locale that will be used
    | by Laravel's translation / localization methods. This option can be
    | set to any locale for which you plan to have translation strings.
    |
    */

    'locale' => env('APP_LOCALE', 'en'),

    'fallback_locale' => env('APP_FALLBACK_LOCALE', 'en'),

    'faker_locale' => env('APP_FAKER_LOCALE', 'en_US'),

    /*
    |--------------------------------------------------------------------------
    | Encryption Key
    |--------------------------------------------------------------------------
    |
    | This key is utilized by Laravel's encryption services and should be set
    | to a random, 32 character string to ensure that all encrypted values
    | are secure. You should do this prior to deploying the application.
    |
    */

    'cipher' => 'AES-256-CBC',

    'key' => env('APP_KEY'),

    'previous_keys' => [
        ...array_filter(
            explode(',', env('APP_PREVIOUS_KEYS', ''))
        ),
    ],

    /*
    |--------------------------------------------------------------------------
    | Maintenance Mode Driver
    |--------------------------------------------------------------------------
    |
    | These configuration options determine the driver used to determine and
    | manage Laravel's "maintenance mode" status. The "cache" driver will
    | allow maintenance mode to be controlled across multiple machines.
    |
    | Supported drivers: "file", "cache"
    |
    */

    'maintenance' => [
        'driver' => env('APP_MAINTENANCE_DRIVER', 'file'),
        'store' => env('APP_MAINTENANCE_STORE', 'database'),
    ],
    /*
    |--------------------------------------------------------------------------
    | Document Control System Settings
    |--------------------------------------------------------------------------
    */
    
    'company_code' => env('COMPANY_CODE', 'AKM'),
    'company_name' => env('COMPANY_NAME', 'PT. Aneka Karya Mandiri'),
    'admin_email' => env('ADMIN_EMAIL', 'admin@akm.com'),

    /*
    |--------------------------------------------------------------------------
    | Document Settings
    |--------------------------------------------------------------------------
    */
    
    'max_document_size' => env('MAX_DOCUMENT_SIZE', 10240), // KB
    'allowed_document_types' => [
        'pdf' => 'application/pdf',
        'doc' => 'application/msword',
        'docx' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
    ],
    
    /*
    |--------------------------------------------------------------------------
    | Workflow Settings
    |--------------------------------------------------------------------------
    */
    
    'review_deadline_days' => env('REVIEW_DEADLINE_DAYS', 3),
    'approval_deadline_days' => env('APPROVAL_DEADLINE_DAYS', 7),
    'auto_archive_expired' => env('AUTO_ARCHIVE_EXPIRED', true),
    
    /*
    |--------------------------------------------------------------------------
    | Retention Settings
    |--------------------------------------------------------------------------
    */
    
    'download_retention_days' => env('DOWNLOAD_RETENTION_DAYS', 365),
    'notification_retention_days' => env('NOTIFICATION_RETENTION_DAYS', 90),
    'audit_retention_days' => env('AUDIT_RETENTION_DAYS', 2555), // 7 years
    
    /*
    |--------------------------------------------------------------------------
    | QR Code Settings
    |--------------------------------------------------------------------------
    */
    
    'qr_code_size' => env('QR_CODE_SIZE', 200),
    'qr_code_margin' => env('QR_CODE_MARGIN', 2),
    'qr_code_error_correction' => env('QR_CODE_ERROR_CORRECTION', 'M'),
    
    /*
    |--------------------------------------------------------------------------
    | Email Settings
    |--------------------------------------------------------------------------
    */
    
    'notification_email_enabled' => env('NOTIFICATION_EMAIL_ENABLED', true),
    'notification_email_queue' => env('NOTIFICATION_EMAIL_QUEUE', 'emails'),
    
    /*
    |--------------------------------------------------------------------------
    | Security Settings
    |--------------------------------------------------------------------------
    */
    
    'session_timeout_minutes' => env('SESSION_TIMEOUT_MINUTES', 120),
    'max_login_attempts' => env('MAX_LOGIN_ATTEMPTS', 5),
    'lockout_duration_minutes' => env('LOCKOUT_DURATION_MINUTES', 15),
    
    /*
    |--------------------------------------------------------------------------
    | Public Portal Settings
    |--------------------------------------------------------------------------
    */
    
    'public_portal_enabled' => env('PUBLIC_PORTAL_ENABLED', true),
    'public_search_enabled' => env('PUBLIC_SEARCH_ENABLED', true),
    'public_download_enabled' => env('PUBLIC_DOWNLOAD_ENABLED', true),
    'documents_per_page' => env('DOCUMENTS_PER_PAGE', 12),
    
    /*
    |--------------------------------------------------------------------------
    | Cache Settings
    |--------------------------------------------------------------------------
    */
    
    'cache_public_data_minutes' => env('CACHE_PUBLIC_DATA_MINUTES', 60),
    'cache_statistics_minutes' => env('CACHE_STATISTICS_MINUTES', 30),
    'cache_search_results_minutes' => env('CACHE_SEARCH_RESULTS_MINUTES', 15),

];
