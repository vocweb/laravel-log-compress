<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Compression Password
    |--------------------------------------------------------------------------
    |
    | When set, the tar.gz archive will be encrypted using AES-256-CBC via
    | OpenSSL. Set to null to create unencrypted archives.
    |
    */
    'password' => env('LOG_COMPRESS_PASSWORD', null),

    /*
    |--------------------------------------------------------------------------
    | Delete After Compress
    |--------------------------------------------------------------------------
    |
    | Whether to delete the original log file after successful compression.
    |
    */
    'delete_after_compress' => true,

    /*
    |--------------------------------------------------------------------------
    | Older Than Days
    |--------------------------------------------------------------------------
    |
    | Only compress log files older than this many days. Set to 1 to skip
    | today's log file (recommended, as it may still be written to).
    |
    */
    'older_than_days' => 1,

    /*
    |--------------------------------------------------------------------------
    | Auto Schedule
    |--------------------------------------------------------------------------
    |
    | When enabled, the package will automatically register a daily scheduled
    | task to compress log files. Set to false if you prefer to schedule
    | the command manually in your application.
    |
    */
    'auto_schedule' => env('LOG_COMPRESS_AUTO_SCHEDULE', true),

    /*
    |--------------------------------------------------------------------------
    | Schedule Time
    |--------------------------------------------------------------------------
    |
    | The time of day to run the auto-scheduled compression (24h format).
    | Only used when auto_schedule is enabled.
    |
    */
    'schedule_time' => env('LOG_COMPRESS_SCHEDULE_TIME', '00:00'),

];
